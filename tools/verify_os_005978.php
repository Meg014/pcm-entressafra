<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/config/bootstrap.php';
use App\Integration\Totvs\{Orders,Queries};
use Cake\Datasource\ConnectionManager;
$os=(new Orders())->find(Orders::key('01','005978'));
$notes=ConnectionManager::get('protheus')->execute(Queries::ORDER_NOTES,['numero'=>'005978','filial'=>'01'])->fetchAll('assoc');
$db=ConnectionManager::get('default');
$books=$db->execute("SELECT c.id,c.nome,c.entressafra_id,ar.nome AS setor_curva FROM cadernos c JOIN entressafras e ON e.id=c.entressafra_id LEFT JOIN areas_fabrica ar ON ar.id=c.area_curva_id WHERE e.status='ativa' AND UPPER(c.nome) LIKE '%CALDEIRARIA%' AND UPPER(c.nome) LIKE '%MOENDA%'")->fetchAll('assoc');
$linked=$db->execute("SELECT a.id,a.os_numero,a.os_filial,a.entressafra_id,c.nome AS caderno,ar.nome AS setor_curva FROM atividades a LEFT JOIN cadernos c ON c.id=a.caderno_id LEFT JOIN areas_fabrica ar ON ar.id=COALESCE(a.area_curva_id,c.area_curva_id) WHERE a.os_numero=? AND a.os_filial=?",['005978','01'])->fetchAll('assoc');
echo json_encode(['os'=>$os,'observacoes_totvs_bytes'=>strlen((string)($notes[0]['observacoes']??'')),'cadernos_destino'=>$books,'vinculos_existentes'=>$linked],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
