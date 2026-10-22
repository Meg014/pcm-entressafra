<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';require dirname(__DIR__).'/config/bootstrap.php';
use App\Integration\Totvs\Orders;
use App\Integration\Totvs\OrderQueries;
use Cake\Datasource\ConnectionManager;
try {
    $db=ConnectionManager::get('protheus'); $service=new Orders();
    $params=['inicio'=>'20260101','fim'=>'20261231','entressafra'=>'%ENTRESSAFRA%'];
    echo 'Relacionamento em amostra limitada: ',json_encode($db->execute(OrderQueries::profile(),$params)->fetch('assoc')),PHP_EOL;
    $rows=$service->search('ENTRESSAFRA');
    foreach($rows as $row) { if(!Orders::available($row)) { throw new RuntimeException(); } }
    echo 'Pesquisa real de abertas com filtros definitivos: OK; resultados limitados: ',count($rows),PHP_EOL;
    if($rows) {
        $sample=$rows[0];
        $detail=$service->find($sample['chave']); if(!$detail['disponivel']) { throw new RuntimeException(); }
        $byNumber=$service->search($sample['numero'],$sample['codigo_area'],$sample['filial']);
        if(!$byNumber) { throw new RuntimeException(); }
        foreach($byNumber as $row) { if($row['codigo_area']!==$sample['codigo_area'] || $row['filial']!==$sample['filial']) {throw new RuntimeException();} }
        echo 'Confirmação por chave e pesquisa por número/área/filial: OK.',PHP_EOL;
    }
    $closed=$db->execute(OrderQueries::historySample(),$params)->fetch('assoc');
    if($closed) {
        $detail=$service->find(Orders::key(rtrim($closed['filial']),rtrim($closed['numero'])));
        if($detail['disponivel']) { throw new RuntimeException(); }
        echo 'Consulta histórica real de O.S. fora das abertas: OK; identidade preservada.',PHP_EOL;
    } else { echo 'Sem amostra encerrada/C/P nesta consulta limitada; cenários cobertos por mocks.',PHP_EOL; }
    echo 'Somente SELECTs executados. Nenhum vínculo ou apontamento foi criado por este utilitário.',PHP_EOL;
}catch(Throwable){fwrite(STDERR,'Validação real de O.S. interrompida. Nenhuma credencial ou informação de conexão exibida.'.PHP_EOL);exit(1);}
