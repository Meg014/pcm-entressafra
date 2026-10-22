<?php
declare(strict_types=1);

// Verifica integridade no banco exclusivo; todas as linhas de teste são revertidas.
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/bootstrap.php';

$db = \Cake\Datasource\ConnectionManager::get('default');
$identity = $db->execute('SELECT current_database() AS db, current_user AS usr')->fetch('assoc');
if ($identity['db'] !== 'pcm_controle_entressafra' || $identity['usr'] !== 'pcm_entressafra_app') {
    throw new RuntimeException('Conexão fora do banco/usuário exclusivo. Verificação interrompida.');
}
$before = $db->execute('SELECT (SELECT count(*) FROM entressafras) AS entressafras, (SELECT count(*) FROM setores) AS setores, (SELECT count(*) FROM cadernos) AS cadernos')->fetch('assoc');
$db->begin();
try {
    $tag = '__verificacao_temporaria_' . bin2hex(random_bytes(8));
    $id = $db->execute("INSERT INTO entressafras (nome, data_inicio, data_fim, status, created, modified) VALUES (?, '2026-11-01', '2027-03-31', 'planejamento', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) RETURNING id", [$tag])->fetch('assoc')['id'];
    $setor = $db->execute('INSERT INTO setores (nome, ativo, created, modified) VALUES (?, TRUE, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) RETURNING id', [$tag])->fetch('assoc')['id'];
    $caderno = $db->execute('INSERT INTO cadernos (nome, entressafra_id, setor_id, ativo, created, modified) VALUES (?, ?, ?, TRUE, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) RETURNING id', [$tag, $id, $setor])->fetch('assoc')['id'];
    $checks = [
        ['Datas invertidas', 'UPDATE entressafras SET data_fim = data_inicio - 1 WHERE id = ?', [$id], '23514'],
        ['Status inválido', "UPDATE entressafras SET status = 'invalido' WHERE id = ?", [$id], '23514'],
        ['Nome vazio', "UPDATE setores SET nome = '  ' WHERE id = ?", [$setor], '23514'],
        ['Entressafra inexistente', 'UPDATE cadernos SET entressafra_id = -1 WHERE id = ?', [$caderno], '23503'],
        ['Setor inexistente', 'UPDATE cadernos SET setor_id = -1 WHERE id = ?', [$caderno], '23503'],
        ['Exclusão de entressafra vinculada', 'DELETE FROM entressafras WHERE id = ?', [$id], '23001'],
        ['Exclusão de setor vinculado', 'DELETE FROM setores WHERE id = ?', [$setor], '23001'],
        ['Nome duplicado de setor', 'INSERT INTO setores (nome, ativo, created, modified) VALUES (?, TRUE, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [$tag], '23505'],
        ['Caderno duplicado no mesmo vínculo', 'INSERT INTO cadernos (nome, entressafra_id, setor_id, ativo, created, modified) VALUES (?, ?, ?, TRUE, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [$tag, $id, $setor], '23505'],
    ];
    foreach ($checks as [$label, $sql, $params, $state]) {
        $db->execute('SAVEPOINT integrity_check');
        $blocked = false;
        try {
            $db->execute($sql, $params);
        } catch (\Throwable $exception) {
            $blocked = str_contains($exception->getMessage(), $state);
            for ($cause = $exception; $cause !== null; $cause = $cause->getPrevious()) {
                $blocked = $blocked || (string)$cause->getCode() === $state;
            }
        }
        $db->execute('ROLLBACK TO SAVEPOINT integrity_check');
        $db->execute('RELEASE SAVEPOINT integrity_check');
        if (!$blocked) {
            throw new RuntimeException('Restrição não confirmada: ' . $label);
        }
        echo 'OK: ' . $label . PHP_EOL;
    }
} finally {
    $db->rollback();
}
$after = $db->execute('SELECT (SELECT count(*) FROM entressafras) AS entressafras, (SELECT count(*) FROM setores) AS setores, (SELECT count(*) FROM cadernos) AS cadernos')->fetch('assoc');
if ($before !== $after) {
    throw new RuntimeException('Contagens divergentes após rollback.');
}
echo 'Rollback confirmado. Registros preservados: ', json_encode($after), PHP_EOL;
