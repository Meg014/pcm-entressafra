<?php
declare(strict_types=1);
if(getenv('PCM_RUN_OPERATIONAL_TEST')!=='1'||!preg_match('/^pcm_op_test_[a-f0-9]{16}$/D',(string)getenv('PCM_OPERATIONAL_SCHEMA')))exit(2);
require dirname(__DIR__).'/vendor/autoload.php';require __DIR__.'/operational_test_support.php';
class OperationalTestApplication extends \App\Application
{
    public function bootstrap(): void
    {
        parent::bootstrap();operationalTestConnect(getenv('PCM_OPERATIONAL_SCHEMA'));
        \Cake\Core\Configure::write('Session.cookie','pcm_operational_test');
        \Cake\Core\Configure::write('Session.ini',['session.save_path'=>TMP.getenv('PCM_OPERATIONAL_SCHEMA')]);
    }
    public function services(\Cake\Core\ContainerInterface $container): void
    {
        parent::services($container);$container->add(\App\Integration\Totvs\GatewayInterface::class,\App\Test\Support\OperationalTotvs::class,true);
    }
}
$server=new \Cake\Http\Server(new OperationalTestApplication(dirname(__DIR__).'/config'));
$server->emit($server->run());
