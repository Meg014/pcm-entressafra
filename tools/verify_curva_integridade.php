<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/config/bootstrap.php';
$db=Cake\Datasource\ConnectionManager::get('default');
if($db->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra')exit(2);
$file=TMP.'curva_integridade.json';
$tables=['entressafras','setores','cadernos','cadernos_setores','atividades','apontamentos','evolucao_registros','evolucao_semanas','evolucao_lotes','evolucao_versoes','evolucao_pesos','evolucao_metas','auditorias','areas_fabrica','calendarios_operacionais','vinculos_lotes','vinculos_itens'];
if(($argv[1]??'')==='--before') {
    $baseline=[];
    foreach($tables as $table) {
        $columns=$db->getSchemaCollection()->describe($table)->columns();
        $rows=$db->execute('SELECT * FROM '.$table)->fetchAll('assoc');$hashes=[];
        foreach($rows as $row)$hashes[]=hash('sha256',json_encode($row,JSON_THROW_ON_ERROR));sort($hashes);
        $baseline[$table]=compact('columns','hashes');
    }
    file_put_contents($file,json_encode($baseline,JSON_THROW_ON_ERROR));
    echo 'Estrutura e hashes de 17 tabelas registrados, sem credenciais ou dados pessoais.',PHP_EOL;
} else {
    $baseline=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
    foreach($baseline as $table=>$info) {
        $columns=implode(',',array_map(fn($c)=>'"'.$c.'"',$info['columns']));$hashes=[];
        foreach($db->execute('SELECT '.$columns.' FROM '.$table)->fetchAll('assoc') as $row)$hashes[]=hash('sha256',json_encode($row,JSON_THROW_ON_ERROR));sort($hashes);
        if($hashes!==$info['hashes'])throw new RuntimeException('Integridade divergente em '.$table);
    }
    echo 'OK: estrutura anterior e todos os registros das 17 tabelas preservados.',PHP_EOL;
}
