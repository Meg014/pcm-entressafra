<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/bootstrap.php';
use App\Integration\Totvs\Queries;
use Cake\Datasource\ConnectionManager;
try {
    $db = ConnectionManager::get('protheus');
    if (isset($argv[1], $argv[2])) {
        if (!Queries::supportsTable($argv[1], $argv[2])) {
            fwrite(STDERR, 'Consulta bloqueada: utilize somente tabelas dbo da família 010 autorizadas neste projeto.' . PHP_EOL);
            exit(1);
        }
        $rows = $db->execute(Queries::COLUMNS, ['schema' => $argv[1], 'table' => $argv[2]])->fetchAll('assoc');
    } else {
        $rows = $db->execute(Queries::TABLES, ['kind' => 'BASE TABLE'])->fetchAll('assoc');
    }
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, 'Não foi possível consultar o catálogo TOTVS. Verifique as variáveis PROTHEUS_DB_*, o driver e a permissão SELECT da conta. Nenhuma credencial será exibida.' . PHP_EOL);
    exit(1);
}
