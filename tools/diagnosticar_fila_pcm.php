<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';require dirname(__DIR__).'/config/bootstrap.php';

use App\Service\{Access,FilaPcm,PeriodoContexto};

$db=\Cake\Datasource\ConnectionManager::get('default');
if(!$db->getDriver() instanceof \Cake\Database\Driver\Postgres)throw new RuntimeException('Diagnóstico destinado ao PostgreSQL exclusivo do projeto.');
$result=$db->transactional(function()use($db):array{
    $db->execute('SET TRANSACTION READ ONLY');
    if($db->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra')throw new RuntimeException('Banco diferente do esperado.');
    $user=Access::table('Usuarios')->find()->where(['perfil'=>'ADMIN_PCM','ativo'=>true])->firstOrFail();
    $rows=$db->execute("SELECT e.id,e.nome,e.status,
        (SELECT COUNT(*) FROM atividades a WHERE a.entressafra_id=e.id) AS ordens,
        (SELECT COUNT(*) FROM apontamentos p JOIN atividades a ON a.id=p.atividade_id WHERE a.entressafra_id=e.id) AS horas_registros,
        (SELECT COUNT(*) FROM apontamentos p JOIN atividades a ON a.id=p.atividade_id WHERE a.entressafra_id=e.id AND NOT p.cancelado AND p.controle_pcm IN ('pendente','conferido')) AS horas_pendentes,
        (SELECT COUNT(*) FROM apontamentos p JOIN atividades a ON a.id=p.atividade_id WHERE a.entressafra_id=e.id AND NOT p.cancelado AND p.controle_pcm='lancado') AS horas_confirmadas,
        (SELECT COUNT(*) FROM apontamentos p JOIN atividades a ON a.id=p.atividade_id WHERE a.entressafra_id=e.id AND p.cancelado) AS horas_canceladas,
        (SELECT COUNT(*) FROM insumos i JOIN atividades a ON a.id=i.atividade_id WHERE a.entressafra_id=e.id) AS insumos_registros,
        (SELECT COUNT(*) FROM insumos i JOIN atividades a ON a.id=i.atividade_id WHERE a.entressafra_id=e.id AND i.controle_pcm='pendente') AS insumos_pendentes,
        (SELECT COUNT(*) FROM insumos i JOIN atividades a ON a.id=i.atividade_id WHERE a.entressafra_id=e.id AND i.controle_pcm='lancado') AS insumos_confirmados
        FROM entressafras e ORDER BY e.id")->fetchAll('assoc');
    foreach($rows as &$row){PeriodoContexto::set((int)$row['id']);$row['ordens_na_fila']=(new FilaPcm())->queueCount($user);}
    unset($row);PeriodoContexto::set(null);
    return $rows;
});
echo json_encode(['somente_leitura'=>true,'entressafras'=>$result],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),PHP_EOL;
