<?php
declare(strict_types=1);
if (getenv('PCM_RUN_BULK_CONCURRENCY_TEST')!=='1') { fwrite(STDERR,"Defina PCM_RUN_BULK_CONCURRENCY_TEST=1 para testar no PostgreSQL exclusivo.\n"); exit(2); }
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/config/bootstrap.php';
require __DIR__.'/bulk_test_support.php';
use App\Service\Access;
$db=Access::table('Usuarios')->getConnection();
if ($db->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra') { exit(2); }
$tag='__bulk_race_'.bin2hex(random_bytes(8)); $user=$season=$sector=$notebook=null; $workers=[]; $failed=false;
try {
    $now=gmdate('Y-m-d H:i:s'); $table=Access::table('Usuarios');
    $user=$table->saveOrFail(Access::entity($table,['nome'=>$tag,'login'=>$tag,'senha_hash'=>password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT),'perfil'=>'PCM','ativo'=>true,'created'=>$now,'modified'=>$now]));
    $table=Access::table('Setores'); $area=substr($tag,-6); $sector=$table->saveOrFail($table->newEntity(['nome'=>$tag,'area_totvs'=>$area,'filial_totvs'=>'01','ativo'=>true]));
    $table=Access::table('Entressafras'); $season=$table->saveOrFail($table->newEntity(['nome'=>$tag,'data_inicio'=>'2026-01-01','data_fim'=>'2027-12-31','status'=>'planejamento']));
    $table=Access::table('Cadernos'); $notebook=$table->saveOrFail($table->newEntity(['nome'=>$tag,'setor_id'=>$sector->id,'entressafra_id'=>$season->id,'ativo'=>true]));
    do { $number='Z'.strtoupper(bin2hex(random_bytes(3))); $number=substr($number,0,6); $key=App\Integration\Totvs\Orders::key('01',$number); } while (Access::table('Atividades')->exists(['os_chave'=>$key]));
    $row=['chave'=>$key,'numero'=>$number,'filial'=>'01','codigo_area'=>$area,'codigo_servico'=>'TESTE','servico'=>'ENTRESSAFRA teste temporário','centro_custo'=>'','bem'=>'','situacao'=>'L','termino'=>'N','data_origem'=>'20260101','correspondencias_servico'=>1,'identidades'=>1,'disponivel'=>true];
    $bulk=bulkTestService($row); $tokens=[];
    foreach ([1,2] as $i) {
        $token=$bulk->create((int)$user->id,['entressafra_id'=>$season->id,'setor_id'=>$sector->id,'caderno_id'=>$notebook->id,'servicos'=>['TESTE']]);
        $bulk->prepare((int)$user->id,$token); $bulk->select((int)$user->id,$token,['todas'=>true,'selecionado'=>true,'quantidade_confirmada'=>1]); $bulk->start((int)$user->id,$token,1); $tokens[]=$token;
    }
    foreach ($tokens as $i=>$token) {
        $payload=base64_encode(json_encode(['user'=>$user->id,'token'=>$token,'row'=>$row,'tag'=>$tag,'worker'=>$i+1],JSON_THROW_ON_ERROR));
        $process=proc_open([PHP_BINARY,'-d','display_errors=stderr',__DIR__.'/bulk_concurrency_worker.php',$payload],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
        if (!is_resource($process)) { throw new RuntimeException(); }
        fclose($pipes[0]); $workers[]=[$process,$pipes];
    }
    $success=$errors=0;
    foreach ($workers as [$process,$pipes]) {
        $output=stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $code=proc_close($process);
        if ($code!==0) { throw new RuntimeException(); }
        $result=json_decode($output,true,512,JSON_THROW_ON_ERROR); $success+=$result['sucesso']; $errors+=$result['falhas'];
    }
    $workers=[];
    if ($success!==1 || $errors!==1 || Access::table('Atividades')->find()->where(['caderno_id'=>$notebook->id])->count()!==1 || Access::table('Auditorias')->find()->where(['usuario_id'=>$user->id,'acao'=>'item_vinculo_lote'])->count()!==1) { throw new RuntimeException(); }
} catch (Throwable) { $failed=true; }
finally {
    foreach ($workers as [$process,$pipes]) { if (is_resource($process)) { proc_terminate($process); proc_close($process); } }
    if ($user) {
        $ids=Access::table('VinculosLotes')->find()->select(['id'])->where(['usuario_id'=>$user->id]);
        Access::table('VinculosItens')->deleteAll(['lote_id IN'=>$ids]); Access::table('VinculosLotes')->deleteAll(['usuario_id'=>$user->id]);
        Access::table('Auditorias')->deleteAll(['usuario_id'=>$user->id]);
        if ($notebook) { Access::table('Atividades')->deleteAll(['caderno_id'=>$notebook->id,'criado_por'=>$user->id]); }
    }
    if ($notebook) { Access::table('Cadernos')->deleteAll(['id'=>$notebook->id,'nome'=>$tag]); }
    if ($season) { Access::table('Entressafras')->deleteAll(['id'=>$season->id,'nome'=>$tag]); }
    if ($sector) { Access::table('Setores')->deleteAll(['id'=>$sector->id,'nome'=>$tag]); }
    if ($user) { Access::table('Usuarios')->deleteAll(['id'=>$user->id,'login'=>$tag]); }
    foreach ([1,2] as $worker) { $marker=TMP.$tag.'_'.$worker; if (is_file($marker)) { unlink($marker); } }
}
if ($failed) { fwrite(STDERR,'Falha no teste concorrente de vínculos. Detalhes sensíveis omitidos.'.PHP_EOL); exit(1); }
echo 'Dois processos, duas prévias concorrentes da mesma O.S.: um vínculo/auditoria aceito e um rejeitado, sem duplicidade. OK.',PHP_EOL;
echo 'Rollback do item concorrente e limpeza das fixtures: OK. TOTVS não consultado neste teste.',PHP_EOL;
