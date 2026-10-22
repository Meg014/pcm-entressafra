<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/bootstrap.php';
use App\Integration\Totvs\Queries;
use Cake\Datasource\ConnectionManager;
$failed = false;
foreach (Queries::EMPLOYEE_COLUMNS as $column) {
    try {
        ConnectionManager::get('protheus')->execute(Queries::probeColumn($column))->fetch('assoc');
        echo $column, ': SELECT autorizado', PHP_EOL;
    } catch (Throwable $error) {
        $failed = true;
        $denied = false;
        for ($cause = $error; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof PDOException && (int)($cause->errorInfo[1] ?? 0) === 230) { $denied = true; }
        }
        echo $column, $denied ? ': SELECT negado (SQL Server 230)' : ': SELECT indisponível (verificar permissão/conexão)', PHP_EOL;
    }
}
exit($failed ? 1 : 0);
