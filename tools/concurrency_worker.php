<?php
declare(strict_types=1);
// Chamado exclusivamente por verify_concurrency.php com fixtures identificadas.
if (getenv('PCM_RUN_CONCURRENCY_TEST') !== '1') { exit(2); }
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/bootstrap.php';
use App\Service\Access;
use App\Service\TimeEntries;
use App\Service\ConflictException;
use App\Test\Support\FakeTotvs;
$data = json_decode(base64_decode($argv[1] ?? '', true), true, 512, JSON_THROW_ON_ERROR);
$user = Access::actor((int)$data['user']);
if (!str_starts_with($user->login, '__concurrency_')) { exit(2); }
$db = Access::table('Apontamentos')->getConnection();
$db->execute("SET lock_timeout = '8s'");
try {
    if ($data['hold']) { $db->begin(); }
    echo "STARTED\n"; flush();
    $service = new TimeEntries(new FakeTotvs());
    $id = $service->save((int)$user->id, ['atividade_id' => $data['activity'], 'funcionario_chave' => $data['employee'], 'inicio' => '2026-10-21T07:00', 'fim' => '2026-10-21T11:00', 'descricao' => 'Teste concorrente temporário']);
    if ($data['hold']) {
        echo "READY\n"; flush();
        $release = trim((string)fgets(STDIN));
        if ($release !== 'COMMIT') { $db->rollback(); exit(2); }
        $db->commit();
    }
    echo "SAVED\n";
} catch (ConflictException $e) {
    echo 'BLOCKED ' . count($e->conflicts) . PHP_EOL;
} catch (Throwable) {
    if ($db->inTransaction()) { $db->rollback(); }
    fwrite(STDERR, "Falha no teste concorrente, sem detalhes de conexão.\n"); exit(1);
}
