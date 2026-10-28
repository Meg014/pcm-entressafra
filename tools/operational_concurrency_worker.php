<?php
declare(strict_types=1);
if(getenv('PCM_RUN_OPERATIONAL_TEST')!=='1')exit(2);
require dirname(__DIR__).'/vendor/autoload.php';require dirname(__DIR__).'/config/bootstrap.php';require __DIR__.'/operational_test_support.php';
$data=json_decode(base64_decode($argv[1]??'',true),true,512,JSON_THROW_ON_ERROR);operationalTestConnect($data['schema']);
$db=\App\Service\Access::table('Usuarios')->getConnection();$db->execute("SET lock_timeout='10s'");
try{
    if($data['hold'])$db->begin();
    echo "STARTED\n";flush();
    $gateway=new \App\Test\Support\OperationalTotvs();
    $ids=$data['module']==='insumos'?(new \App\Service\Insumos($gateway))->saveBatch($data['user'],$data['input']):(new \App\Service\TimeEntries($gateway))->saveBatch($data['user'],$data['input']);
    if($data['hold']){echo "READY\n";flush();if(trim((string)fgets(STDIN))!=='COMMIT')throw new RuntimeException('Liberação inválida.');$db->commit();}
    echo 'SAVED '.json_encode($ids).PHP_EOL;
}catch(\App\Service\ConflictException|\App\Service\BatchEntryException $e){if($db->inTransaction())$db->rollback();echo 'CONFLICT '.$e->getMessage().PHP_EOL;}
catch(Throwable $e){if($db->inTransaction())$db->rollback();fwrite(STDERR,get_class($e).': '.$e->getMessage());exit(1);}
