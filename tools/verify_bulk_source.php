<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/config/bootstrap.php';
try {
    $source=new App\Integration\Totvs\BulkOrders();
    foreach (['MECANI','ELETRI'] as $area) {
        $services=$source->services($area,'01');
        if (!$services) { throw new RuntimeException(); }
        $count=0; $old=0;
        foreach (array_chunk(array_column($services,'codigo'),20) as $codes) {
            $cursor=''; $seen=[];
            do {
                $page=$source->page(['area'=>$area,'filial'=>'01','servicos'=>$codes],$cursor);
                foreach ($page['rows'] as $row) {
                    if (!App\Integration\Totvs\Orders::available($row) || $row['codigo_area']!==$area || $row['filial']!=='01') { throw new RuntimeException(); }
                    if (isset($seen[$row['chave']]) && (int)$row['identidades']===1) { throw new RuntimeException(); }
                    $seen[$row['chave']]=true; ++$count;
                    if (in_array($row['codigo_servico'],['2425ME','2425EL'],true)) { ++$old; }
                }
                if ($page['has_more'] && $cursor===$page['cursor']) { throw new RuntimeException(); }
                $cursor=$page['cursor'];
            } while ($page['has_more']);
        }
        echo $area,': serviços dinâmicos=',count($services),'; O.S. paginadas=',$count,'; O.S. com códigos antigos=',$old,PHP_EOL;
    }
    echo 'SELECTs reais de serviços, filtros e paginação aprovados; nenhum vínculo gravado.',PHP_EOL;
} catch (Throwable) { fwrite(STDERR,'Falha na validação real da consulta em lote. Dados de conexão omitidos.'.PHP_EOL); exit(1); }
