<?php
declare(strict_types=1);

// Local browser fixture only: reads the SQLite database populated by PHPUnit.
if (getenv('PCM_EXPORT_BROWSER_TEST') !== '1' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)) {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__, 2);
$prefix = '/pcm-entressafra';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (!str_starts_with($path, $prefix.'/')) {http_response_code(404);exit;}
$local = substr($path, strlen($prefix));
if (str_starts_with($local, '/js/') || str_starts_with($local, '/css/') || str_starts_with($local, '/img/')) {
    $file = realpath($root.'/webroot'.$local);
    if (!$file || !str_starts_with($file, realpath($root.'/webroot').DIRECTORY_SEPARATOR)) {http_response_code(404);exit;}
    header('Content-Type: '.(str_ends_with($file, '.js') ? 'application/javascript' : (str_ends_with($file, '.css') ? 'text/css' : 'image/png')));
    readfile($file);
    exit;
}
require $root.'/vendor/autoload.php';
$_SERVER['SCRIPT_NAME'] = $prefix.'/index.php';
$_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];

class ExportDownloadBrowserApplication extends \App\Application
{
    public function bootstrap(): void
    {
        parent::bootstrap();
        \Cake\Core\Configure::write('App.fullBaseUrl', 'http://127.0.0.1:8879');
        \Cake\Core\Configure::write('Session.cookie', 'pcm_export_download_test');
        $config = \Cake\Datasource\ConnectionManager::getConfig('test');
        if (($config['driver'] ?? '') !== \Cake\Database\Driver\Sqlite::class) throw new \RuntimeException('SQLite de teste obrigatório.');
        $config['cacheMetadata'] = false;
        \Cake\Datasource\ConnectionManager::drop('default');
        \Cake\Datasource\ConnectionManager::setConfig('default', $config);
    }

    public function middleware(\Cake\Http\MiddlewareQueue $queue): \Cake\Http\MiddlewareQueue
    {
        $queue->add(function ($request, $handler) {
            $user = \App\Service\Access::table('Usuarios')->find()->where(['perfil'=>'ADMIN_PCM','ativo'=>true])->firstOrFail();
            $period = \App\Service\Access::table('Entressafras')->find()->where(['status'=>'ativa'])->firstOrFail();
            $request->getSession()->write('Auth', ['id'=>$user->id,'stamp'=>hash('sha256',$user->senha_hash),'last'=>time()]);
            $request->getSession()->write('Periodo', ['id'=>$period->id,'usuario_id'=>$user->id]);
            return $handler->handle($request);
        });
        return parent::middleware($queue);
    }
}
$server = new \Cake\Http\Server(new ExportDownloadBrowserApplication($root.'/config'));
$server->emit($server->run());
