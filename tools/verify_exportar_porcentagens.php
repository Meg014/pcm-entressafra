<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/config/bootstrap.php';
use App\Service\{Access, EvolucaoSemanal, ExportarPorcentagens, PeriodoContexto};

$db=Access::table('Atividades')->getConnection();
if (!($db->getDriver() instanceof \Cake\Database\Driver\Postgres)) throw new RuntimeException('PostgreSQL obrigatório.');
$check=static function(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);};
// Server-enforced read-only snapshot: the probe cannot modify production records.
$db->begin();
try {
    $db->execute('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
    $user=Access::table('Usuarios')->find()->where(['perfil'=>'ADMIN_PCM','ativo'=>true])->firstOrFail();
    $season=(int)Access::table('Entressafras')->find()->where(['status'=>'ativa'])->firstOrFail()->id;
    PeriodoContexto::set($season);
    $areas=(new EvolucaoSemanal())->areas($user,$season);
    $weeks=(new EvolucaoSemanal())->weeks($season);
    foreach($areas as $area=>$name) {
        $book=(new ExportarPorcentagens())->workbook($user,$season,(int)$area);
        $sheet=$book->getActiveSheet();
        $orders=$db->execute("SELECT a.* FROM atividades a JOIN cadernos c ON c.id=a.caderno_id AND c.entressafra_id=a.entressafra_id WHERE a.entressafra_id=? AND a.tipo_planejamento='PROGRAMADA' AND c.area_curva_id=? ORDER BY a.os_numero,a.id",[$season,$area])->fetchAll('assoc');
        $seen=[];$row=6;$empty=0;$zeros=0;
        foreach($orders as $a) {
            $key=$a['os_chave']?:$a['os_filial'].':'.$a['os_numero'];if(isset($seen[$key]))continue;$seen[$key]=true;
            $check($sheet->getCell('A'.$row)->getValue()===$a['os_numero'],'Número divergente');
            $latest=null;
            foreach($weeks as $i=>$week) {
                $record=$db->execute('SELECT r.percentual FROM evolucao_registros r JOIN evolucao_semanas s ON s.id=r.semana_id WHERE r.atividade_id=? AND s.entressafra_id=? AND s.referencia>=? AND s.referencia<=? ORDER BY s.referencia DESC,r.revisao DESC LIMIT 1',[$a['id'],$season,$week['inicio'],$week['fim']])->fetch('assoc');
                $actual=$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5+$i).$row)->getValue();
                if(!$record){$check($actual===null,'Vazio substituído');$empty++;}
                else {$latest=(float)$record['percentual']/100;$check(abs((float)$actual-$latest)<0.0000001,'Percentual divergente do PostgreSQL');if($latest===0.0)$zeros++;}
            }
            $check($sheet->getCell('D'.$row)->getValue()===$latest,'Último percentual divergente');
            $row++;
        }
        $check($sheet->getHighestRow()===max(5,$row-1),'Quantidade de O.S. divergente');
        $stream=fopen('php://temp','w+b');(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($stream);rewind($stream);$check(fread($stream,2)==='PK','XLSX inválido');fclose($stream);
        echo $name.': '.count($seen).' O.S.; '.$empty.' células vazias; '.$zeros." zeros explícitos; valores conferidos.\n";
        $book->disconnectWorksheets();
    }
} finally {$db->rollback();PeriodoContexto::set(null);}
