<?php
declare(strict_types=1);
// Exercise the actual PostgreSQL transaction in an isolated schema, rolled back in full.
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/config/bootstrap.php';

use App\Service\{Access,CancelamentoRegistrosOs,FilaPcm,PeriodoContexto};
use Cake\Datasource\{ConnectionManager,FactoryLocator};

$config=ConnectionManager::getConfig('default');
if (!in_array($config['host']??'', ['127.0.0.1','localhost','::1'],true)) { throw new RuntimeException('Somente PostgreSQL local.'); }
$public=ConnectionManager::get('default');
if ($public->execute('SELECT current_database()')->fetch()[0]!=='pcm_controle_entressafra') { throw new RuntimeException('Banco inesperado.'); }
$hash=static function()use($public):array {
    $result=[];
    foreach (['usuarios','entressafras','atividades','apontamentos','insumos','auditorias'] as $table) {
        $result[$table]=hash('sha256',json_encode($public->execute('SELECT row_to_json(t)::text FROM public.'.$table.' t ORDER BY id')->fetchAll('assoc'),JSON_THROW_ON_ERROR));
    }
    return $result;
};
$before=$hash();$schema='cancel_os_test_'.bin2hex(random_bytes(8));$config['schema']=$schema;$config['cacheMetadata']=false;unset($config['url']);
ConnectionManager::drop('default');ConnectionManager::setConfig('default',$config);FactoryLocator::get('Table')->clear();
$db=ConnectionManager::get('default');$db->cacheMetadata(false);$db->enableSavePoints(true);$db->begin();$checks=0;
$check=static function(bool $ok,string $message)use(&$checks):void { if(!$ok)throw new RuntimeException($message);$checks++; };
try {
    $db->execute('CREATE SCHEMA '.$schema);$db->execute('SET LOCAL search_path TO '.$schema);
    foreach (['usuarios','entressafras','setores','cadernos','atividades','apontamentos','insumos','auditorias'] as $table) {
        $db->execute('CREATE TABLE '.$table.' (LIKE public.'.$table.' INCLUDING DEFAULTS INCLUDING CONSTRAINTS)');
        $db->execute('CREATE SEQUENCE '.$schema.'.seq_'.$table);
        $db->execute('ALTER TABLE '.$table." ALTER COLUMN id SET DEFAULT nextval('".$schema.'.seq_'.$table."')");
    }
    if (!$db->execute("SELECT column_name FROM information_schema.columns WHERE table_schema=? AND table_name='insumos' AND column_name='cancelado'",[$schema])->fetch()) {
        require dirname(__DIR__).'/config/Migrations/20261030150000_AddCancelamentoInsumos.php';
        $migration=new AddCancelamentoInsumos(20261030150000);
        $migration->setAdapter(new \Migrations\Db\Adapter\PostgresAdapter(['adapter'=>'pgsql','connection'=>$db]));$migration->up();
    }
    $save=static function(string $alias,array $data):int { $t=Access::table($alias);return (int)$t->saveOrFail(Access::entity($t,$data))->id; };
    $now=gmdate('Y-m-d H:i:s');
    $admin=$save('Usuarios',['nome'=>'Admin teste','login'=>'admin-cancel-os','senha_hash'=>password_hash('fixture-Test123',PASSWORD_DEFAULT),'perfil'=>'ADMIN_PCM','ativo'=>true,'created'=>$now,'modified'=>$now]);
    $season=$save('Entressafras',['nome'=>'Teste isolado','data_inicio'=>'2026-01-01','data_fim'=>'2027-12-31','status'=>'ativa']);
    $activity=$save('Atividades',['entressafra_id'=>$season,'os_chave'=>'01|006101','os_numero'=>'006101','os_filial'=>'01','servico'=>'Teste cancelamento','bem'=>'Teste','situacao_totvs'=>'L','disponivel_totvs'=>true,'snapshot_totvs'=>'{}','criado_por'=>$admin,'created'=>$now,'modified'=>$now]);
    $hours=[];$supplies=[];
    for($i=0;$i<11;$i++) {
        $hours[]=$save('Apontamentos',['atividade_id'=>$activity,'funcionario_chave'=>'teste-'.$i,'funcionario_nome'=>'Teste','funcionario_matricula'=>(string)$i,'funcionario_filial'=>'01','funcionario_vinculo'=>'teste-'.$i,'snapshot'=>'{}','inicio'=>'2026-10-30 10:00:00','fim'=>'2026-10-30 11:00:00','minutos'=>60,'descricao'=>'Serviço preservado','criado_por'=>$admin,'alterado_por'=>$admin,'created'=>$now,'modified'=>$now]);
    }
    for($i=0;$i<2;$i++) {
        $supplies[]=$save('Insumos',['atividade_id'=>$activity,'funcionario_chave'=>'teste','funcionario_nome'=>'Teste','funcionario_matricula'=>'001','data'=>'2026-10-30','material'=>'Insumo preservado','quantidade'=>'2.5','snapshot'=>'{}','criado_por'=>$admin,'created'=>$now]);
    }
    PeriodoContexto::set($season);$cancel=new CancelamentoRegistrosOs();$preview=$cancel->preview($admin,$activity);
    $check($preview['apontamentos']===11 && $preview['insumos']===2,'Contagem além da primeira página.');
    $originalHours=Access::table('Apontamentos')->get($hours[0])->toArray();$originalSupply=Access::table('Insumos')->get($supplies[0])->toArray();
    $audits=Access::table('Auditorias');
    $listener=static function($event,$row):void { if($row->acao==='lote_cancelamento_os')throw new RuntimeException('Falha de auditoria simulada'); };
    $audits->getEventManager()->on('Model.beforeSave',$listener);
    try { $cancel->cancel($admin,$activity,'Teste de atomicidade',true,$preview['assinatura']);throw new LogicException('Deveria falhar.'); }
    catch (RuntimeException $e) { $check($e->getMessage()==='Falha de auditoria simulada','Falha de auditoria propagada.'); }
    finally { $audits->getEventManager()->off('Model.beforeSave',$listener); }
    $check(Access::table('Apontamentos')->find()->where(['cancelado'=>true])->count()===0 && Access::table('Insumos')->find()->where(['cancelado'=>true])->count()===0 && $audits->find()->count()===0,'Rollback de ambas as categorias e auditorias.');
    Access::table('Insumos')->updateAll(['controle_pcm'=>'lancado'],['id'=>$supplies[0]]);
    try { $cancel->cancel($admin,$activity,'Bloqueio TOTVS',true,$preview['assinatura']);throw new LogicException('TOTVS deveria bloquear.'); }
    catch (DomainException $e) { $check(str_contains($e->getMessage(),'TOTVS'),'Bloqueio integral com insumo confirmado.'); }
    $check(Access::table('Apontamentos')->find()->where(['cancelado'=>true])->count()===0,'Nenhum cancelamento parcial.');
    Access::table('Insumos')->updateAll(['controle_pcm'=>'pendente'],['id'=>$supplies[0]]);
    $preview=$cancel->preview($admin,$activity);$result=$cancel->cancel($admin,$activity,'Justificativa PostgreSQL',true,$preview['assinatura']);
    $check($result['apontamentos']===11 && $result['insumos']===2,'Cancelamento completo.');
    $check($audits->find()->count()===14,'Uma auditoria por registro e resumo do lote.');
    $check((new FilaPcm())->queueCount(Access::actor($admin))===0,'Remoção da fila.');
    $check((int)(new FilaPcm())->metrics([$activity])[$activity]['minutos']===0,'Totais operacionais zerados.');
    $audit=$audits->find()->where(['acao'=>'cancelar','apontamento_id'=>$hours[0]])->firstOrFail();
    $check(json_decode($audit->antes,true)===json_decode(json_encode($originalHours),true),'Apontamento original preservado na auditoria.');
    $audit=$audits->find()->where(['acao'=>'insumo_cancelar','insumo_id'=>$supplies[0]])->firstOrFail();
    $check(json_decode($audit->antes,true)===json_decode(json_encode($originalSupply),true),'Insumo original preservado na auditoria.');
    $check(Access::table('Atividades')->exists(['id'=>$activity]),'O.S. preservada.');
    try { $cancel->cancel($admin,$activity,'Repetição',true,$preview['assinatura']);throw new LogicException('Repetição deveria bloquear.'); }
    catch (DomainException $e) { $check(str_contains($e->getMessage(),'Não há registros ativos'),'Repetição bloqueada.'); }
} finally { PeriodoContexto::set(null);$db->rollback(); }
$check($before===$hash(),'Dados reais preservados.');
echo 'OK: '.$checks.' verificações PostgreSQL; schema e fixtures revertidos; dados reais preservados.',PHP_EOL;
