<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/config/bootstrap.php';

use App\Service\{Access,Activities,Evolucao,OperacaoRapida};

$db=Cake\Datasource\ConnectionManager::get('default');
if($db->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra')exit(2);
$tables=['entressafras','setores','cadernos','cadernos_setores','atividades','apontamentos','evolucao_semanas','evolucao_registros','evolucao_lotes','evolucao_versoes','evolucao_pesos','evolucao_metas','auditorias','calendarios_operacionais'];
$fingerprint=function()use($db,$tables): array {
    $result=[];
    foreach($tables as $name){
        $rows=array_map(fn($row)=>json_encode($row,JSON_THROW_ON_ERROR),$db->execute('SELECT * FROM '.$name)->fetchAll('assoc'));
        sort($rows);$result[$name]=hash('sha256',implode("\n",$rows));
    }
    return $result;
};
$before=$fingerprint();$failed=false;$stage='estrutura';
try {
    $columns=$db->execute("SELECT table_name,is_nullable FROM information_schema.columns WHERE table_schema='public' AND column_name='caderno_id' ORDER BY table_name")->fetchAll('assoc');
    echo 'Colunas caderno_id: ',json_encode($columns,JSON_THROW_ON_ERROR),PHP_EOL;
    foreach($columns as $column)if($column['table_name']==='atividades'&&$column['is_nullable']!=='YES')throw new RuntimeException();
    $db->begin();
    try {
        $stage='preparação isolada';$tag='__setores_'.bin2hex(random_bytes(5));$now=gmdate('Y-m-d H:i:s');
        $t=Access::table('Usuarios');$u=$t->saveOrFail(Access::entity($t,['nome'=>$tag,'login'=>$tag,'perfil'=>'ADMIN_PCM','ativo'=>true,'senha_hash'=>password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT),'created'=>$now,'modified'=>$now]));
        $season=Access::table('Entressafras')->find()->where(['status'=>'ativa'])->all();
        if($season->count()!==1)throw new RuntimeException();$season=$season->first();
        $gateway=new App\Integration\Totvs\Gateway();$orders=new Activities($gateway);
        $stage='consulta TOTVS e identidade local';$id=$orders->identify((int)$u->id,App\Integration\Totvs\Orders::key('01','005300'));
        if($id!==$orders->identify((int)$u->id,App\Integration\Totvs\Orders::key('01','005300')))throw new RuntimeException();
        // Existing records are only read. All new validation data remains in this rollback.
        $t=Access::table('Entressafras');$test=$t->saveOrFail($t->newEntity(['nome'=>$tag,'data_inicio'=>'2026-10-19','data_fim'=>'2027-04-10','status'=>'planejamento']));
        $original=Access::table('Atividades')->get($id)->toArray();
        unset($original['id']);$original['entressafra_id']=$test->id;$original['caderno_id']=null;$original['criado_por']=$u->id;
        $t=Access::table('Atividades');$a=$t->saveOrFail(Access::entity($t,$original));
        $stage='percentual sem caderno e sem calendário';$s=new Evolucao();[$ref,$week]=(new OperacaoRapida())->reference((int)$test->id);
        if($week!==null)throw new RuntimeException();
        $s->saveBatch((int)$u->id,(int)$test->id,$ref,[['atividade_id'=>(int)$a->id,'percentual'=>35,'revisao'=>0]],bin2hex(random_bytes(16)));
        $report=$s->report((int)$test->id,$s->activities((int)$test->id),$ref);
        if($report['simple']!==35.0||$report['realizado']!==null||$report['programado']!==null||$report['counts']['total']!==1)throw new RuntimeException();
        $stage='histórico encerrado';$a->disponivel_totvs=false;$a->situacao_totvs='F';$t->saveOrFail($a);
        if(count((new Evolucao())->history((int)$a->id))!==1)throw new RuntimeException();
    } finally { $db->rollback(); }
} catch(Throwable) { $failed=true; }
if($before!==$fingerprint()){$failed=true;$stage='integridade dos dados anteriores';}
if($failed){fwrite(STDERR,'Falha em '.$stage.'; detalhes sensíveis omitidos.'.PHP_EOL);exit(1);}
echo 'OK: SELECT real TOTVS; identidade idempotente; percentual sem caderno/calendário; evolução simples; histórico encerrado; rollback e hashes de 14 tabelas preservados.',PHP_EOL;
