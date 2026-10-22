<?php
declare(strict_types=1);
if (getenv('PCM_RUN_BULK_CONCURRENCY_TEST')!=='1') { exit(2); }
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/config/bootstrap.php';
require __DIR__.'/bulk_test_support.php';
try {
    $input=json_decode(base64_decode($argv[1]),true,512,JSON_THROW_ON_ERROR);
    if (!preg_match('/^__bulk_race_[a-f0-9]{16}$/D',$input['tag']) || !in_array($input['worker'],[1,2],true)) { exit(2); }
    $table=App\Service\Access::table('Atividades');
    $table->getEventManager()->on('Model.beforeSave',function($event,$entity) use($input) {
        if ($entity->os_chave!==$input['row']['chave']) { return; }
        $own=TMP.$input['tag'].'_'.$input['worker']; $other=TMP.$input['tag'].'_'.($input['worker']===1?2:1);
        file_put_contents($own,'ready'); $until=microtime(true)+10;
        while (!is_file($other) && microtime(true)<$until) { clearstatcache(true,$other); usleep(50000); }
        if (!is_file($other)) { throw new RuntimeException('Barreira de concorrência não alcançada.'); }
    });
    $state=bulkTestService($input['row'])->process((int)$input['user'],$input['token']);
    echo json_encode(['sucesso'=>$state['contagens']['vinculadas'],'falhas'=>$state['contagens']['falhas']],JSON_THROW_ON_ERROR);
} catch (Throwable) { fwrite(STDERR,'Falha protegida no worker de lote.'.PHP_EOL); exit(1); }
