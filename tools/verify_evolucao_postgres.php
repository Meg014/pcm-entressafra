<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/config/bootstrap.php';
use App\Service\{Access,Activities,Evolucao};
use App\Test\Support\FakeTotvs;
use Cake\Datasource\ConnectionManager;
$db=ConnectionManager::get('default');
if ($db->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra') { exit(2); }
$counts=[];foreach(['entressafras','setores','cadernos','atividades','apontamentos','auditorias','evolucao_registros','evolucao_versoes','evolucao_semanas'] as $t){$counts[$t]=(int)$db->execute('SELECT COUNT(*) AS n FROM '.$t)->fetch('assoc')['n'];}
$hashes=[];foreach(['cadernos','atividades','apontamentos','auditorias'] as $t){$hashes[$t]=hash('sha256',json_encode($db->execute('SELECT * FROM '.$t.' ORDER BY id')->fetchAll('assoc'),JSON_THROW_ON_ERROR));}
$tag='__evolucao_'.bin2hex(random_bytes(6));$stage='fixtures';$error=null;
$db->begin();
try {
    $now=gmdate('Y-m-d H:i:s');$t=Access::table('Usuarios');$u=$t->saveOrFail(Access::entity($t,['nome'=>$tag,'login'=>$tag,'senha_hash'=>password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),'perfil'=>'ADMIN_PCM','ativo'=>true,'created'=>$now,'modified'=>$now]));
    $t=Access::table('Entressafras');$season=$t->saveOrFail($t->newEntity(['nome'=>$tag,'data_inicio'=>'2026-09-01','data_fim'=>'2026-10-20','status'=>'planejamento']));
    $t=Access::table('Setores');$sector=$t->saveOrFail($t->newEntity(['nome'=>$tag,'ativo'=>true,'area_totvs'=>substr($tag,-6),'filial_totvs'=>'01']));
    $t=Access::table('Cadernos');$book=$t->saveOrFail($t->newEntity(['nome'=>$tag,'entressafra_id'=>$season->id,'setor_id'=>$sector->id,'ativo'=>true]));
    $activity=(new Activities(new FakeTotvs($sector->area_totvs)))->link((int)$u->id,(int)$book->id,$tag);
    $s=new Evolucao();$s->configureCalendar((int)$u->id,(int)$season->id,array_column($s->calendar((int)$season->id),'referencia'));
    $stage='lote e idempotência';$key=bin2hex(random_bytes(16));$rows=[['atividade_id'=>$activity,'revisao'=>0,'percentual'=>50]];
    $first=$s->saveBatch((int)$u->id,(int)$season->id,'2026-09-07',$rows,$key);
    if ($first!==$s->saveBatch((int)$u->id,(int)$season->id,'2026-09-07',$rows,$key)) {throw new RuntimeException();}
    $stage='pesos e metas oficiais';$s->version((int)$u->id,(int)$season->id,'pesos','Conferência temporária',[$activity=>40],true);
    $targets=[];$dates=array_column($s->calendar((int)$season->id),'referencia');foreach($dates as $i=>$d){$targets[$d]=round($i/(count($dates)-1)*100,2);}
    $s->version((int)$u->id,(int)$season->id,'metas','Conferência temporária',$targets,true);
    $r=$s->report((int)$season->id,$s->activities((int)$season->id),'2026-09-07');if($r['realizado']!==50.0){throw new RuntimeException();}
    $stage='revisão concorrente obsoleta';try{$s->saveBatch((int)$u->id,(int)$season->id,'2026-09-07',[['atividade_id'=>$activity,'revisao'=>0,'percentual'=>60]],bin2hex(random_bytes(16)));throw new RuntimeException();}catch(DomainException){}
    $stage='imutabilidade PostgreSQL';
    $db->execute('SAVEPOINT check_immutable');
    try{$db->execute('UPDATE evolucao_registros SET percentual=0 WHERE atividade_id=?',[$activity]);throw new RuntimeException();}catch(Cake\Database\Exception\QueryException){$db->execute('ROLLBACK TO SAVEPOINT check_immutable');}
    $stage='fila e confirmação individual';
    $entry=(new App\Service\TimeEntries(new FakeTotvs($sector->area_totvs)))->save((int)$u->id,['atividade_id'=>$activity,'funcionario_chave'=>$tag,'inicio'=>'2026-10-01T07:00','fim'=>'2026-10-01T08:00','descricao'=>'Conferência temporária em rollback']);
    $control=new App\Service\FilaPcm();$control->confirm((int)$u->id,$activity,[['id'=>$entry,'revisao'=>0]],'lancado','Teste em rollback',true);
    if(Access::table('Apontamentos')->get($entry)->controle_pcm!=='lancado')throw new RuntimeException();
    $stage='proteção de apontamento confirmado no PostgreSQL';$db->execute('SAVEPOINT check_confirmed');
    try{$db->execute('UPDATE apontamentos SET descricao=? WHERE id=?',['Mudança vedada',$entry]);throw new RuntimeException();}catch(Cake\Database\Exception\QueryException){$db->execute('ROLLBACK TO SAVEPOINT check_confirmed');}
    $stage='reabertura auditada';$control->confirm((int)$u->id,$activity,[['id'=>$entry,'revisao'=>1]],'pendente','Conferência corrigida em teste',true);
    if(Access::table('Apontamentos')->get($entry)->controle_pcm!=='pendente')throw new RuntimeException();
    $stage='serialização entre conexões';
    $existing=$db->execute('SELECT id FROM entressafras WHERE id<>? ORDER BY id LIMIT 1',[$season->id])->fetch('assoc');
    if($existing){
        $db->execute('SELECT id FROM entressafras WHERE id=? FOR UPDATE',[$existing['id']]);
        ConnectionManager::setConfig('evolucao_probe',ConnectionManager::getConfig('default'));$other=ConnectionManager::get('evolucao_probe');
        $other->begin();try{$other->execute('SELECT id FROM entressafras WHERE id=? FOR UPDATE NOWAIT',[$existing['id']]);throw new RuntimeException();}catch(Cake\Database\Exception\QueryException $e){if(!str_contains($e->getMessage(),'55P03'))throw $e;}finally{$other->rollback();ConnectionManager::drop('evolucao_probe');}
    }
} catch(Throwable $e){$error=$stage;}
finally {$db->rollback();}
foreach($counts as $t=>$before){if((int)$db->execute('SELECT COUNT(*) AS n FROM '.$t)->fetch('assoc')['n']!==$before){$error='preservação de dados';}}
foreach($hashes as $t=>$before){if(hash('sha256',json_encode($db->execute('SELECT * FROM '.$t.' ORDER BY id')->fetchAll('assoc'),JSON_THROW_ON_ERROR))!==$before){$error='integridade dos dados anteriores';}}
if($error!==null){fwrite(STDERR,'Falha em '.$error.'. Detalhes sensíveis omitidos.'.PHP_EOL);exit(1);}
echo 'PostgreSQL: evolução, versões, idempotência, fila, confirmação, proteção contra alteração, reabertura e bloqueio entre conexões: OK.',PHP_EOL;
echo 'Rollback confirmado; nenhum percentual, peso, meta ou cadastro fictício permaneceu.',PHP_EOL;
