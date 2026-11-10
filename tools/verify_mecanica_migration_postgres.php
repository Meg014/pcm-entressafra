<?php
declare(strict_types=1);

// Validate the actual migration in an isolated schema, leaving application tables untouched.
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/config/bootstrap.php';

use Cake\Datasource\ConnectionManager;
use Migrations\Db\Adapter\PostgresAdapter;

$source=ConnectionManager::get('default');
if(!$source->getDriver() instanceof \Cake\Database\Driver\Postgres)throw new RuntimeException('Esta verificação requer PostgreSQL.');
$schema='pcm_mecanica_verify_'.bin2hex(random_bytes(6));
if(!preg_match('/^pcm_mecanica_verify_[a-f0-9]{12}$/D',$schema))throw new RuntimeException('Esquema de teste inválido.');
$tables=['atividades','apontamentos','insumos','evolucao_registros','evolucao_versoes','auditorias','calendarios_operacionais'];
$hashes=static function()use($source,$tables):array{
    $result=[];foreach($tables as $table)$result[$table]=hash('sha256',json_encode($source->execute('SELECT row_to_json(t)::text AS r FROM public.'.$table.' t ORDER BY id')->fetchAll('assoc'),JSON_THROW_ON_ERROR));
    return $result;
};
$before=$hashes();$source->execute('CREATE SCHEMA "'.$schema.'"');
try{
    $config=ConnectionManager::getConfig('default');$config['className']=get_class($source);$config['schema']=$schema;$config['cacheMetadata']=false;
    ConnectionManager::setConfig('mecanica_validation',$config);$db=ConnectionManager::get('mecanica_validation');
    if($db->execute('SELECT current_schema() AS s')->fetch('assoc')['s']!==$schema)throw new RuntimeException('A conexão não está isolada.');
    $db->execute('CREATE TABLE entressafras (id integer PRIMARY KEY)');$db->execute('CREATE TABLE areas_fabrica (id integer PRIMARY KEY)');
    $db->execute('INSERT INTO entressafras (id) VALUES (1)');$db->execute('INSERT INTO areas_fabrica (id) VALUES (1)');
    require dirname(__DIR__).'/config/Migrations/20261110120000_CreateMecanicaCurvaVersoes.php';
    $migration=new CreateMecanicaCurvaVersoes(20261110120000);$migration->setAdapter(new PostgresAdapter(['connection'=>$db]));$migration->up();
    $members=[['atividade_id'=>1,'entressafra_id'=>1,'os_filial'=>'01','os_chave'=>'01|000123','os_numero'=>'000123']];
    foreach([['2026-11-09 15:00:00',$members],['2026-11-10 15:00:00',[]]] as [$created,$content]){
        $db->insert('mecanica_curva_versoes',['entressafra_id'=>1,'area_curva_id'=>1,'usuario_id'=>1,'usuario_nome'=>'Teste isolado','conteudo'=>json_encode($content,JSON_THROW_ON_ERROR),'created'=>$created]);
    }
    $past=$db->execute('SELECT * FROM mecanica_curva_versoes WHERE entressafra_id=? AND area_curva_id=? AND created<=? ORDER BY id DESC LIMIT 1',[1,1,'2026-11-10 02:59:59'])->fetch('assoc');
    if(json_decode($past['conteudo'],true,512,JSON_THROW_ON_ERROR)!==$members)throw new RuntimeException('Histórico ou zeros à esquerda não preservados.');
    foreach(['UPDATE mecanica_curva_versoes SET conteudo=conteudo','DELETE FROM mecanica_curva_versoes'] as $sql){
        $db->begin();
        try{$db->execute($sql);throw new RuntimeException('Histórico não protegido contra alteração.');}
        catch(\Cake\Database\Exception\QueryException $e){if(!str_contains($e->getMessage(),'P0001'))throw $e;}
        finally{$db->rollback();}
    }
    $versions=$db->execute('SELECT COUNT(*) AS n FROM mecanica_curva_versoes')->fetch('assoc')['n'];
    if((int)$versions!==2)throw new RuntimeException('Versões alteradas.');
}finally{
    ConnectionManager::drop('mecanica_validation');
    // Only this freshly created, validated scratch schema can be removed.
    $source->execute('DROP SCHEMA "'.$schema.'" CASCADE');
}
if($before!==$hashes())throw new RuntimeException('Tabelas existentes alteradas.');
echo json_encode(['status'=>'OK','migration_postgresql'=>true,'corte_historico'=>true,'zeros_preservados'=>true,'update_delete_bloqueados'=>true,'esquema_isolado_removido'=>true,'tabelas_existentes_preservadas'=>count($tables)],JSON_UNESCAPED_UNICODE).PHP_EOL;
