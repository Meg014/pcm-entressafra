<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';require dirname(__DIR__).'/config/bootstrap.php';
use App\Service\{Access,CurvaSemanal,PeriodoContexto};
$db=Access::table('EvolucaoVersoes')->getConnection();
if($db->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra')throw new RuntimeException('Banco inesperado.');
$hashes=static function()use($db):array {
    $out=[];foreach(['atividades','apontamentos','insumos','evolucao_registros','evolucao_versoes','auditorias','calendarios_operacionais'] as $table) {
        $out[$table]=hash('sha256',json_encode($db->execute('SELECT row_to_json(t)::text AS r FROM '.$table.' t ORDER BY id')->fetchAll('assoc'),JSON_THROW_ON_ERROR));
    }return $out;
};
$before=$hashes();$db->begin();
try {
    $admin=Access::table('Usuarios')->find()->where(['perfil'=>'ADMIN_PCM','ativo'=>true])->firstOrFail();
    $season=Access::table('Entressafras')->find()->where(['status'=>'ativa'])->firstOrFail();PeriodoContexto::set((int)$season->id);
    $service=new CurvaSemanal();$areas=$service->areas((int)$season->id);$area=(int)array_key_first($areas);
    if(!$area)throw new RuntimeException('Nenhum setor disponível para validação.');
    $report=$service->report((int)$season->id,$area);$last=$report['latest'];if(!$last)throw new RuntimeException('Nenhuma semana encerrada.');
    $curve=$report['programmed_graph'];
    if($curve&&($curve[0]['programado']!==0.||$curve[count($curve)-1]['programado']!==100.))throw new RuntimeException('Extremos do modelo automático inválidos.');
    $id=$service->close((int)$admin->id,(int)$season->id,$area,$last['referencia']);
    if($id!==$service->close((int)$admin->id,(int)$season->id,$area,$last['referencia']))throw new RuntimeException('Idempotência inválida.');
    $version=Access::table('EvolucaoVersoes')->get($id);$data=json_decode($version->conteudo,true,512,JSON_THROW_ON_ERROR);
    if(isset($data['programacao_automatica'])&&$data['programacao_automatica']['modelo']!==\App\Service\ProgramacaoAutomatica::VERSION)throw new RuntimeException('Modelo automático não foi preservado.');
    $average=$data['ordens']?array_sum(array_column($data['ordens'],'percentual'))/count($data['ordens']):null;
    if($average!==$data['realizado'] && (float)$average!==(float)$data['realizado'])throw new RuntimeException('Média inválida.');
    foreach([
        ['UPDATE evolucao_versoes SET nome=nome WHERE id=?',[$id],'P0001'],
        ['DELETE FROM evolucao_versoes WHERE id=?',[$id],'P0001'],
        ["INSERT INTO evolucao_versoes(entressafra_id,area_curva_id,tipo,nome,conteudo,usuario_id,created,aprovado_por,aprovado_em,referencia_semanal,revisao_semanal) SELECT entressafra_id,area_curva_id,tipo,nome,conteudo,usuario_id,created,aprovado_por,aprovado_em,referencia_semanal,revisao_semanal FROM evolucao_versoes WHERE id=?",[$id],'23505'],
    ] as [$sql,$params,$state]) {
        $db->execute('SAVEPOINT curva_check');
        try{$db->execute($sql,$params);throw new RuntimeException('Integridade não bloqueou '.$state);}
        catch(\Cake\Database\Exception\QueryException $error){if(!str_contains($error->getMessage(),$state))throw $error;}
        finally{$db->execute('ROLLBACK TO SAVEPOINT curva_check');}
    }
    $checks=3;
} finally { $db->rollback();PeriodoContexto::set(null); }
if($before!==$hashes())throw new RuntimeException('Dados alterados após rollback.');
echo json_encode(['status'=>'OK','media_simples'=>true,'idempotencia'=>true,'restricoes_validadas'=>$checks,'tabelas_preservadas'=>7,'totvs'=>'não acessado'],JSON_UNESCAPED_UNICODE).PHP_EOL;
