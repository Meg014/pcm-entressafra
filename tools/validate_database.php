<?php
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/bootstrap.php';
try {
    $db = \Cake\Datasource\ConnectionManager::get('default');
    $row = $db->execute("SELECT current_database() AS database, current_user AS username, has_database_privilege(current_user, current_database(), 'CONNECT') AS can_connect, has_schema_privilege(current_user, 'public', 'USAGE') AS can_use_schema, has_schema_privilege(current_user, 'public', 'CREATE') AS can_create")->fetch('assoc');
    if ($row['database'] !== 'pcm_controle_entressafra' || $row['username'] !== 'pcm_entressafra_app') {
        throw new RuntimeException('Banco ou usuário diferente do esperado.');
    }
    foreach (['can_connect', 'can_use_schema', 'can_create'] as $permission) {
        if (!$row[$permission]) {
            throw new RuntimeException('Permissão ausente: ' . $permission);
        }
    }
    echo json_encode($row, JSON_PRETTY_PRINT), PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Falha na validação: ' . preg_replace('/password[^\s]*/i', '[omitido]', $e->getMessage()) . PHP_EOL);
    exit(1);
}
