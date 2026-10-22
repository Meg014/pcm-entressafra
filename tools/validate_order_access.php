<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';require dirname(__DIR__).'/config/bootstrap.php';
use App\Integration\Totvs\Queries;
use Cake\Datasource\ConnectionManager;
$failed=false;
foreach(Queries::ORDER_COLUMNS as $table=>$columns) {
 foreach($columns as $column) {
  try { ConnectionManager::get('protheus')->execute(Queries::probeOrderColumn($table,$column))->fetch('assoc'); echo "$table.$column: SELECT autorizado\n"; }
  catch(Throwable $error) {
   $failed=true; $denied=false;
   for($cause=$error;$cause;$cause=$cause->getPrevious()) { if($cause instanceof PDOException && (int)($cause->errorInfo[1]??0)===230) {$denied=true;} }
   echo "$table.$column: ", $denied?'SELECT negado (SQL Server 230)':'consulta indisponível',PHP_EOL;
  }
 }
}
exit($failed?1:0);
