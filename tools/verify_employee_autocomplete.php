<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/config/bootstrap.php';
use App\Integration\Totvs\Employees;
use App\Integration\Totvs\Queries;
use Cake\Datasource\ConnectionManager;
try {
    $sample=ConnectionManager::get('protheus')->execute(Queries::EMPLOYEE_UNIQUE_SAMPLE)->fetch('assoc');
    if(!$sample) { throw new RuntimeException(); }
    $service=new Employees();
    foreach(['nome'=>rtrim($sample['RA_NOME']),'matricula'=>rtrim($sample['RA_MAT'])] as $kind=>$term) {
        $results=$service->search($term);
        if(!$results) { throw new RuntimeException(); }
        foreach($results as $person) {
            if (!in_array($person['situacao_codigo'],['','F'],true) || ($person['situacao_codigo']==='F' && ($person['ativo'] || $person['chave']!==''))) { throw new RuntimeException(); }
        }
        echo 'Autocomplete real por ',$kind,': OK; resultados limitados, sem dados pessoais no relatório.',PHP_EOL;
    }
    $groups=$service->duplicateGroups();
    $unique=$service->find(rtrim($sample['RA_MAT']));
    if(!$unique['ativo'] || $unique['chave']!==rtrim($sample['RA_MAT'])) { throw new RuntimeException(); }
    echo 'Funcionário NORMAL único confirmado apesar de duplicidades em outras matrículas: OK.',PHP_EOL;
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
