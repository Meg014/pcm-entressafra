<?php
declare(strict_types=1);
if (getenv('PCM_RUN_MULTISETOR_TEST')!=='1') { fwrite(STDERR,"Defina PCM_RUN_MULTISETOR_TEST=1 para o teste temporário no PostgreSQL exclusivo.\n"); exit(2); }
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/config/bootstrap.php';
require __DIR__.'/bulk_test_support.php';
use App\Service\Access;
$db=Access::table('Usuarios')->getConnection();
if ($db->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra') { exit(2); }
$tag='__multi_'.bin2hex(random_bytes(8)); $admin=$responsavel=$season=$book=null; $sectors=[]; $failed=false; $stage='cadastros';
try {
    $now=gmdate('Y-m-d H:i:s'); $table=Access::table('Usuarios');
    foreach (['ADMIN_PCM'=>'admin','RESPONSAVEL_SETOR'=>'resp'] as $role=>$suffix) {
        $user=$table->saveOrFail(Access::entity($table,['nome'=>$tag,'login'=>$tag.'_'.$suffix,'senha_hash'=>password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT),'perfil'=>$role,'ativo'=>true,'created'=>$now,'modified'=>$now]));
        if ($role==='ADMIN_PCM') { $admin=$user; } else { $responsavel=$user; }
    }
    $table=Access::table('Entressafras'); $season=$table->saveOrFail($table->newEntity(['nome'=>$tag,'data_inicio'=>'2026-01-01','data_fim'=>'2027-12-31','status'=>'planejamento']));
    $table=Access::table('Setores');
    foreach ([1,2] as $i) { $sectors[]=$table->saveOrFail($table->newEntity(['nome'=>$tag.'_'.$i,'area_totvs'=>substr($tag,-5).$i,'filial_totvs'=>'01','ativo'=>true])); }
    $table=Access::table('Cadernos'); $book=$table->saveOrFail($table->newEntity(['nome'=>$tag,'entressafra_id'=>$season->id,'setores'=>['_ids'=>array_map(fn($s)=>$s->id,$sectors)],'ativo'=>true,'area_fisica'=>'Referência física de teste']));
    Access::table('UsuariosSetores')->saveOrFail(Access::entity(Access::table('UsuariosSetores'),['usuario_id'=>$responsavel->id,'setor_id'=>$sectors[0]->id]));
    $activities=[];
    $stage='lotes';
    foreach ($sectors as $i=>$sector) {
        $number='Z'.substr(strtoupper(bin2hex(random_bytes(3))),0,5);
        $row=['chave'=>App\Integration\Totvs\Orders::key('01',$number),'numero'=>$number,'filial'=>'01','codigo_area'=>$sector->area_totvs,'codigo_servico'=>'TESTE','servico'=>'ENTRESSAFRA teste temporário','centro_custo'=>'','bem'=>'','situacao'=>'L','termino'=>'N','data_origem'=>'20260101','correspondencias_servico'=>1,'identidades'=>1,'disponivel'=>true];
        $bulk=bulkTestService($row);
        $token=$bulk->create((int)$admin->id,['entressafra_id'=>$season->id,'setor_id'=>$sector->id,'caderno_id'=>$book->id,'servicos'=>['TESTE']]);
        $bulk->prepare((int)$admin->id,$token); $bulk->select((int)$admin->id,$token,['todas'=>true,'selecionado'=>true,'quantidade_confirmada'=>1]); $bulk->start((int)$admin->id,$token,1);
        $state=$bulk->process((int)$admin->id,$token);
        if ($state['contagens']['vinculadas']!==1) { throw new RuntimeException(); }
        $activities[]=Access::table('Atividades')->find()->where(['caderno_id'=>$book->id,'setor_executante_id'=>$sector->id])->firstOrFail();
    }
    Access::notebook($responsavel,(int)$book->id);
    $stage='permissões';
    try { Access::activity($responsavel,$activities[1]); throw new RuntimeException(); } catch (Cake\Http\Exception\ForbiddenException) {}
    $gateway=new class($tag) implements App\Integration\Totvs\GatewayInterface {
        public function __construct(private string $key) {}
        public function order(string $key): array { throw new DomainException('Fora do escopo.'); }
        public function orders(string $search,string $area='',?string $branch=null): array { return []; }
        public function employees(string $search): array { return []; }
        public function employee(string $key): array { return ['chave'=>$this->key,'nome'=>'Teste temporário','matricula'=>'000001','filial'=>'01','vinculo'=>$this->key,'ativo'=>true]; }
    };
    $entries=new App\Service\TimeEntries($gateway);
    $stage='horários';
    $input=['atividade_id'=>$activities[0]->id,'funcionario_chave'=>$tag,'inicio'=>'2026-10-21T07:00','fim'=>'2026-10-21T11:00','descricao'=>'Teste temporário'];
    $entries->save((int)$responsavel->id,$input); $input['atividade_id']=$activities[1]->id;
    try { $entries->save((int)$responsavel->id,$input); throw new RuntimeException(); } catch (Cake\Http\Exception\ForbiddenException) {}
    try { $entries->save((int)$admin->id,$input); throw new RuntimeException(); } catch (App\Service\ConflictException) {}
    $input['inicio']='2026-10-21T11:00'; $input['fim']='2026-10-21T15:00'; $entries->save((int)$admin->id,$input);
    $stage='integridade referencial';
    try { $db->execute('DELETE FROM cadernos_setores WHERE caderno_id=? AND setor_id=?',[$book->id,$sectors[1]->id]); throw new RuntimeException(); }
    catch (Cake\Database\Exception\QueryException $error) { if (!str_contains($error->getMessage(),'23503') && !str_contains($error->getMessage(),'23001')) { throw $error; } }
    if (Access::table('Atividades')->find()->where(['caderno_id'=>$book->id])->count()!==2 || Access::table('Apontamentos')->find()->where(['atividade_id IN'=>array_map(fn($a)=>$a->id,$activities)])->count()!==2) { throw new RuntimeException(); }
} catch (Throwable $error) { $failed=true; $errorType=get_class($error); }
finally {
    $users=array_values(array_filter([$admin?->id,$responsavel?->id]));
    if ($users) {
        $jobs=Access::table('VinculosLotes')->find()->select(['id'])->where(['usuario_id IN'=>$users]);
        Access::table('VinculosItens')->deleteAll(['lote_id IN'=>$jobs]); Access::table('VinculosLotes')->deleteAll(['usuario_id IN'=>$users]);
        Access::table('Auditorias')->deleteAll(['usuario_id IN'=>$users]);
        if ($book) {
            $ids=Access::table('Atividades')->find()->select(['id'])->where(['caderno_id'=>$book->id]);
            Access::table('Apontamentos')->deleteAll(['atividade_id IN'=>$ids]); Access::table('Atividades')->deleteAll(['caderno_id'=>$book->id]);
            Access::table('Cadernos')->deleteAll(['id'=>$book->id,'nome'=>$tag]);
        }
        Access::table('UsuariosSetores')->deleteAll(['usuario_id IN'=>$users]); Access::table('Usuarios')->deleteAll(['id IN'=>$users]);
    }
    if ($season) { Access::table('Entressafras')->deleteAll(['id'=>$season->id,'nome'=>$tag]); }
    foreach ($sectors as $sector) { Access::table('Setores')->deleteAll(['id'=>$sector->id,'nome'=>$sector->nome]); }
}
if ($failed) { fwrite(STDERR,'Falha no teste multissetorial PostgreSQL: '.$stage.' ('.$errorType.'). Dados sensíveis omitidos.'.PHP_EOL); exit(1); }
echo 'PostgreSQL: caderno com dois setores, dois lotes, permissões por atividade, conflito global, horários consecutivos e FK histórica: OK.',PHP_EOL;
echo 'Fixtures próprias removidas. Fonte simulada; nenhuma escrita ou consulta ao TOTVS neste teste.',PHP_EOL;
