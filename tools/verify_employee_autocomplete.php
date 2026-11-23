<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/config/bootstrap.php';
use App\Integration\Totvs\Employees;
use App\Integration\Totvs\Queries;
use Cake\Datasource\ConnectionManager;
try {
    $service = new Employees();
    foreach ([''=>'NORMAL', 'F'=>'Férias'] as $status=>$label) {
        $sample = ConnectionManager::get('protheus')->execute(Queries::EMPLOYEE_UNIQUE_SAMPLE, ['situacao'=>$status])->fetch('assoc');
        if (!$sample) {
            echo $label, ' sem amostra de matrícula única disponível.', PHP_EOL;
            continue;
        }
        $number = rtrim($sample['RA_MAT']);
        foreach ([rtrim($sample['RA_NOME']), $number] as $term) {
            $results = $service->search($term);
            if (!in_array($number, array_column($results, 'chave'), true)) throw new RuntimeException();
        }
        $unique = $service->find($number);
        if (!$unique['ativo'] || $unique['chave'] !== $number || $unique['situacao_codigo'] !== $status) throw new RuntimeException();
        echo $label, ' com matrícula única: pesquisa por nome e matrícula e confirmação OK.', PHP_EOL;
    }
    $groups=$service->duplicateGroups();
    foreach($groups as $group) {
        $number=rtrim((string)$group['matricula']); if($number==='') { continue; }
        try { $service->find($number); throw new RuntimeException(); }
        catch(DomainException $e) { if($e->getMessage()!==Employees::AMBIGUOUS) { throw $e; } }
        foreach($service->search($number) as $person) { if($person['matricula']===$number && ($person['ativo'] || $person['chave']!=='')) { throw new RuntimeException(); } }
    }
    echo 'Bloqueio individual das matrículas pendentes: OK. Nenhum dado pessoal exibido.',PHP_EOL;
    $reference=null;
    foreach(['Maria','MARIA','maria'] as $term) {
        // Validate the SQL result itself, before the service's defense-in-depth filter.
        $raw=ConnectionManager::get('protheus')->execute(Queries::EMPLOYEE_SEARCH,['nome'=>'%'.$term.'%','matricula'=>'%'.$term.'%'])->fetchAll('assoc');
        if (!$raw) { throw new RuntimeException(); }
        foreach($raw as $row) { if (!in_array(trim((string)$row['RA_SITFOLH']),['','F'],true)) { throw new RuntimeException(); } }
        $fingerprint=hash('sha256',json_encode($raw,JSON_THROW_ON_ERROR));
        if ($reference!==null && $reference!==$fingerprint) { throw new RuntimeException(); } $reference=$fingerprint;
    }
    echo 'SQL real limitado a NORMAL/FÉRIAS e pesquisa sem diferenciar maiúsculas/minúsculas: OK.',PHP_EOL;
}catch(Throwable){fwrite(STDERR,'Validação real do autocomplete falhou. Nenhum dado pessoal ou de conexão será exibido.'.PHP_EOL);exit(1);}
