<?php
declare(strict_types=1);
// Testa somente a pesquisa diagnóstica; não cria vínculos nem grava apontamentos.
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/bootstrap.php';
use App\Integration\Totvs\Queries;
use Cake\Datasource\ConnectionManager;
try {
    $db = ConnectionManager::get('protheus');
    $sample = $db->execute(Queries::EMPLOYEE_SAMPLE)->fetch('assoc');
    if (!$sample || trim((string)$sample['RA_NOME']) === '' || trim((string)$sample['RA_MAT']) === '') {
        fwrite(STDERR, 'Sem amostra suficiente para confirmar as duas pesquisas.' . PHP_EOL); exit(1);
    }
    $name = rtrim($sample['RA_NOME']); $number = rtrim($sample['RA_MAT']);
    $escaped = strtr($name, ['[' => '[[]', '%' => '[%]', '_' => '[_]']);
    $byName = $db->execute(Queries::EMPLOYEE_BY_NAME, ['nome' => $escaped . '%'])->fetchAll('assoc');
    $byNumber = $db->execute(Queries::EMPLOYEE_BY_NUMBER, ['matricula' => $number])->fetchAll('assoc');
    if (!$byName || !$byNumber) { throw new RuntimeException(); }
    echo 'OK: pesquisa por nome parametrizada (limite 5), sem exibir dados pessoais.', PHP_EOL;
    echo 'OK: pesquisa por matrícula parametrizada (limite 5), sem exibir dados pessoais.', PHP_EOL;
    $duplicates=$db->execute(Queries::EMPLOYEE_DUPLICATES)->fetchAll('assoc');
    echo $duplicates ? 'Há pendências de identificação; bloqueio restrito às matrículas afetadas.' : 'Matrículas NORMAL sem duplicidades nesta verificação.', PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, 'Não foi possível validar a pesquisa diagnóstica. Nenhum dado pessoal ou detalhe de conexão será exibido.' . PHP_EOL);
    exit(1);
}
