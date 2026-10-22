<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';require dirname(__DIR__).'/config/bootstrap.php';
$db=Cake\Datasource\ConnectionManager::get('default');
foreach(['entressafras'=>'SELECT id,nome,status,data_inicio,data_fim FROM entressafras','setores'=>'SELECT id,nome,area_totvs,filial_totvs FROM setores','cadernos'=>'SELECT id,nome,area_fisica,entressafra_id FROM cadernos','005300_local'=>"SELECT id,os_numero,os_filial,caderno_id,setor_executante_id,disponivel_totvs FROM atividades WHERE os_numero='005300'"] as $label=>$sql){echo $label,': ',json_encode($db->execute($sql)->fetchAll('assoc'),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),PHP_EOL;}
try{$r=(new App\Integration\Totvs\Gateway())->order(App\Integration\Totvs\Orders::key('01','005300'));echo '005300_TOTVS: ',json_encode(array_intersect_key($r,array_flip(['numero','filial','codigo_area','codigo_servico','situacao','termino','data_origem','disponivel','correspondencias_servico'])),JSON_THROW_ON_ERROR),PHP_EOL;}catch(Throwable){echo '005300_TOTVS: consulta indisponível; detalhes sensíveis omitidos.',PHP_EOL;}
