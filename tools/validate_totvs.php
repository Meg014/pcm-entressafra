<?php
declare(strict_types=1);
// Somente SELECTs agregados: não exibe linhas cadastrais nem credenciais.
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/bootstrap.php';
use App\Integration\Totvs\Queries;
use Cake\Datasource\ConnectionManager;
try {
    $db = ConnectionManager::get('protheus');
    foreach ([Queries::PROFILE, Queries::PERMISSIONS] as $sql) {
        echo json_encode($db->execute($sql)->fetchAll('assoc'), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
    }
} catch (Throwable $error) {
    // Não imprimir a mensagem original: pode conter servidor, banco ou usuário.
    $columns = [];
    for ($cause = $error; $cause !== null; $cause = $cause->getPrevious()) {
        if (preg_match_all("/(?:column|coluna) '([A-Za-z0-9_]+)'/i", $cause->getMessage(), $matches)) {
            $columns = array_merge($columns, $matches[1]);
        }
    }
    fwrite(STDERR, 'Validação TOTVS interrompida. ' . ($columns ? 'Verifique a permissão SELECT nas colunas: ' . implode(', ', array_unique($columns)) . '.' : 'Verifique conexão e permissões SELECT com o administrador.') . ' Nenhum dado cadastral ou credencial foi exibido.' . PHP_EOL);
    exit(1);
}
