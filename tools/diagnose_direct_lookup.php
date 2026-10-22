<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/config/bootstrap.php';
try {
    $employees = new App\Integration\Totvs\Employees();
    foreach (['Maria', 'MARIA', 'maria'] as $term) {
        $rows = $employees->search($term);
        echo json_encode(['pesquisa'=>$term,'resultados'=>count($rows),'elegiveis'=>count(array_filter($rows,fn($r)=>$r['ativo']))]), PHP_EOL;
    }
    $orders = new App\Integration\Totvs\Orders();
    $rows = $orders->search('005523');
    foreach ($rows as $row) {
        echo json_encode(array_intersect_key($row,array_flip(['numero','filial','codigo_area','centro_custo','situacao','termino']))), PHP_EOL;
    }
    echo 'OS encontradas: ',count($rows),PHP_EOL;
    $areas=Cake\Datasource\ConnectionManager::get('protheus')->execute(App\Integration\Totvs\OrderQueries::areas(),['inicio'=>'20260101','fim'=>'20261231','entressafra'=>'%ENTRESSAFRA%'])->fetchAll('assoc');
    echo json_encode(['areas_filiais_disponiveis'=>$areas],JSON_UNESCAPED_UNICODE),PHP_EOL;
    $sectors=Cake\Datasource\ConnectionManager::get('default')->execute('SELECT nome, area_totvs, filial_totvs FROM setores ORDER BY nome')->fetchAll('assoc');
    echo json_encode(['mapeamentos_locais'=>$sectors],JSON_UNESCAPED_UNICODE),PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR,'Falha no diagnóstico; detalhes sensíveis omitidos. Tipo: '.get_class($e).PHP_EOL);
    exit(1);
}
