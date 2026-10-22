<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/bootstrap.php';
try {
    $groups=(new \App\Integration\Totvs\Employees())->duplicateGroups();
    echo 'Grupos de matrículas NORMAL duplicadas/vazias: ',count($groups), count($groups)===51?' (resultado limitado; pode haver mais)':'',PHP_EOL;
    foreach($groups as $group) {
        $number=rtrim((string)$group['matricula']);
        echo 'Matrícula mascarada: ', $number===''?'(vazia)':str_repeat('*',max(1,strlen($number)-2)).substr($number,-2),'; registros NORMAL: ',(int)$group['quantidade'],PHP_EOL;
    }
    echo $groups ? 'Somente as matrículas pendentes estão bloqueadas. As demais são confirmadas individualmente ao salvar.' : 'Sem pendências nesta leitura; validação individual será repetida ao salvar.',PHP_EOL;
    exit($groups?1:0);
}catch(Throwable){fwrite(STDERR,'Não foi possível verificar a unicidade no TOTVS. Habilitação bloqueada; nenhum dado de conexão exibido.'.PHP_EOL);exit(1);}
