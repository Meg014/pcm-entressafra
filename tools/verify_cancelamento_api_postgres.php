<?php
declare(strict_types=1);
// Run the real HTTP middleware/controller tests on PostgreSQL in a disposable schema.
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/config/bootstrap.php';

use Cake\Datasource\ConnectionManager;

$config=ConnectionManager::getConfig('default');
if (!in_array($config['host']??'',['127.0.0.1','localhost','::1'],true)) {throw new RuntimeException('Somente PostgreSQL local.');}
$db=ConnectionManager::get('default');
if ($db->execute('SELECT current_database()')->fetch()[0]!=='pcm_controle_entressafra') {throw new RuntimeException('Banco inesperado.');}
$hash=static function()use($db):array {
    $result=[];
    foreach (['usuarios','entressafras','atividades','apontamentos','insumos','auditorias'] as $table) {
        $result[$table]=hash('sha256',json_encode($db->execute('SELECT row_to_json(t)::text FROM public.'.$table.' t ORDER BY id')->fetchAll('assoc'),JSON_THROW_ON_ERROR));
    }
    return $result;
};
$before=$hash();$schema='pcm_cancel_api_test_'.bin2hex(random_bytes(8));
$bootstrap=TMP.$schema.'_bootstrap.php';
$source=file_get_contents(ROOT.'/tests/bootstrap.php');
$hook=<<<'PHP'
$schema=getenv('PCM_CANCEL_API_TEST_SCHEMA');
if (!is_string($schema) || !preg_match('/^pcm_cancel_api_test_[a-f0-9]{16}$/D',$schema)) {throw new RuntimeException('Schema isolado obrigatório.');}
$isolated=\Cake\Datasource\ConnectionManager::getConfig('default');
$isolated['schema']=$schema;$isolated['cacheMetadata']=false;unset($isolated['url']);
\Cake\Datasource\ConnectionManager::drop('test');
\Cake\Datasource\ConnectionManager::setConfig('test',$isolated);
$test=\Cake\Datasource\ConnectionManager::get('test');
if ($test->execute('SELECT current_schema()')->fetch()[0]!==$schema) {throw new RuntimeException('Conexão fora do schema de teste.');}

PHP;
$source=str_replace('// Connection aliasing needs to happen before migrations are run.',$hook."\n// Connection aliasing needs to happen before migrations are run.",$source);
$db->execute('CREATE SCHEMA "'.$schema.'"');
$exitCode=1;
try {
    file_put_contents($bootstrap,$source);
    putenv('PCM_CANCEL_API_TEST_SCHEMA='.$schema);
    $process=proc_open([PHP_BINARY,ROOT.'/vendor/bin/phpunit','--bootstrap',$bootstrap,
        'tests/TestCase/Controller/FilaPcmUnificadaTest.php','--filter','testCancellationApi','--colors=never'],
        [0=>['pipe','r'],1=>STDOUT,2=>STDERR],$pipes,ROOT,null,['bypass_shell'=>true]);
    if (!is_resource($process)) {throw new RuntimeException('Não foi possível iniciar os testes.');}
    fclose($pipes[0]);$exitCode=proc_close($process);
} finally {
    putenv('PCM_CANCEL_API_TEST_SCHEMA');
    if (is_file($bootstrap)) {unlink($bootstrap);}
    if (!preg_match('/^pcm_cancel_api_test_[a-f0-9]{16}$/D',$schema)) {throw new RuntimeException('Schema de limpeza inválido.');}
    $db->execute('DROP SCHEMA "'.$schema.'" CASCADE');
    if ($before!==$hash()) {throw new RuntimeException('Os dados públicos mudaram durante a verificação.');}
    echo 'Schema de teste removido; dados reais preservados (checksums conferidos).',PHP_EOL;
}
exit($exitCode);
