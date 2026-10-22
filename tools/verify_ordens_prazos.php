<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';require dirname(__DIR__).'/config/bootstrap.php';
use App\Service\{Access,Activities,OperacaoRapida,Calendarios,Evolucao};
$db=Cake\Datasource\ConnectionManager::get('default');if($db->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra')exit(2);
$hashes=[];foreach(['entressafras','atividades','cadernos','apontamentos','auditorias','calendarios_operacionais'] as $t)$hashes[$t]=hash('sha256',json_encode($db->execute('SELECT * FROM '.$t.' ORDER BY id')->fetchAll('assoc'),JSON_THROW_ON_ERROR));
$db->begin();$stage='preparação';$failed=false;
try{
    $tag='__prazos_'.bin2hex(random_bytes(6));$now=gmdate('Y-m-d H:i:s');$t=Access::table('Usuarios');$u=$t->saveOrFail(Access::entity($t,['nome'=>$tag,'login'=>$tag,'perfil'=>'ADMIN_PCM','ativo'=>true,'senha_hash'=>password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT),'created'=>$now,'modified'=>$now]));
    $t=Access::table('Entressafras');$season=$t->saveOrFail($t->newEntity(['nome'=>$tag,'data_inicio'=>'2026-10-19','data_fim'=>'2027-04-10','status'=>'ativa']));
    $gateway=new App\Integration\Totvs\Gateway();$stage='consulta real 005300';$result=(new OperacaoRapida($gateway))->search($u,'005300',false);
    if(!array_filter($result['results'],fn($r)=>$r['numero']==='005300'&&$r['filial']==='01'&&$r['selecionavel']))throw new RuntimeException();
    $stage='identificação sem caderno';$service=new Activities($gateway);$id=$service->identify((int)$u->id,App\Integration\Totvs\Orders::key('01','005300'));if($id!==$service->identify((int)$u->id,App\Integration\Totvs\Orders::key('01','005300')))throw new RuntimeException();$a=Access::table('Atividades')->get($id);if($a->caderno_id!==null||(int)$a->entressafra_id!==(int)$season->id)throw new RuntimeException();
    $stage='prazos e início pendente';$area=Access::table('AreasFabrica')->find()->where(['nome'=>'Moenda'])->firstOrFail();$cal=new Calendarios();$cid=$cal->save((int)$u->id,(int)$season->id,['area_fabrica_id'=>$area->id,'data_fim'=>'2027-04-10']);$cal->classify((int)$u->id,(int)$season->id,[$id],(int)$area->id);
    $resolved=(new Calendarios())->resolve(Access::table('Atividades')->get($id)->toArray());if($resolved['data_fim']!=='2027-04-10'||$resolved['inicio_confirmado'])throw new RuntimeException();
    $stage='semanas parciais';$cal->save((int)$u->id,(int)$season->id,['area_fabrica_id'=>$area->id,'data_inicio'=>'2026-10-19','data_fim'=>'2027-04-10','inicio_confirmado'=>'1']);$weeks=(new Calendarios())->references((new Calendarios())->resolve(Access::table('Atividades')->get($id)->toArray()));if(end($weeks)!=='2027-04-10'||!in_array('2027-04-05',$weeks,true))throw new RuntimeException();
    $stage='percentual sem caderno';(new Evolucao())->saveBatch((int)$u->id,(int)$season->id,'2026-10-19',[['atividade_id'=>$id,'percentual'=>20,'revisao'=>0]],bin2hex(random_bytes(16)));
    $stage='unicidade PostgreSQL';$db->execute('SAVEPOINT duplicate_order');try{$db->execute('UPDATE atividades SET os_numero=? WHERE id=?',['005300',$id]);$db->execute('INSERT INTO atividades(entressafra_id,os_numero,os_filial,os_chave,servico,bem,situacao_totvs,snapshot_totvs,criado_por,created,modified) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$season->id,'005300','01','duplicada','Teste','','L','{}',$u->id,$now,$now]);throw new RuntimeException();}catch(Cake\Database\Exception\QueryException $e){if(!str_contains($e->getMessage(),'23505'))throw $e;$db->execute('ROLLBACK TO SAVEPOINT duplicate_order');}
}catch(Throwable){$failed=true;}finally{$db->rollback();}
foreach($hashes as $t=>$hash)if(hash('sha256',json_encode($db->execute('SELECT * FROM '.$t.' ORDER BY id')->fetchAll('assoc'),JSON_THROW_ON_ERROR))!==$hash){$failed=true;$stage='preservação de dados';}
if($failed){fwrite(STDERR,'Falha na verificação de '.$stage.'; informações sensíveis omitidas.'.PHP_EOL);exit(1);}
echo 'O.S. 005300 localizada por SELECT real; identificação local sem caderno, idempotência, unicidade, prazos e percentual validados no PostgreSQL.',PHP_EOL,'Rollback e hashes confirmados: dados anteriores preservados, sem dados fictícios permanentes.',PHP_EOL;
