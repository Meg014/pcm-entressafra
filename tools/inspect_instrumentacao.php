<?php
declare(strict_types=1);
// Read-only diagnosis: never creates closings or changes operational records.
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/config/bootstrap.php';
use App\Service\{Access,CurvaSemanal,Curva,PeriodoContexto};
$db=Access::table('Atividades')->getConnection();
foreach(Access::table('Entressafras')->find()->where(['status'=>'ativa'])->all() as $season){
    PeriodoContexto::set((int)$season->id);
    $service=new CurvaSemanal();
    foreach($service->areas((int)$season->id) as $area=>$name){
        if(!str_contains(mb_strtoupper($name),'INSTRUMENT'))continue;
        [$where,$params]=(new Curva())->scope((int)$season->id,(string)$area);
        $entries=$db->execute('SELECT w.referencia,COUNT(*) AS registros,MIN(r.created) AS primeiro_salvamento,MAX(r.created) AS ultimo_salvamento FROM evolucao_registros r JOIN evolucao_semanas w ON w.id=r.semana_id JOIN atividades a ON a.id=r.atividade_id LEFT JOIN cadernos c ON c.id=a.caderno_id WHERE '.$where.' GROUP BY w.referencia ORDER BY w.referencia',$params)->fetchAll('assoc');
        $report=$service->report((int)$season->id,(int)$area);
        echo json_encode(['setor'=>$name,'entressafra'=>$season->id,'lancamentos'=>$entries,'pontos'=>$report['points'],'fechamentos'=>count($report['versions'])],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
    }
}
PeriodoContexto::set(null);
