<?php
declare(strict_types=1);
namespace App\Service;

/** Cancellation of the complete, explicitly reviewed set of operational records. */
final class CancelamentoRegistrosOs
{
    private function activity(int $actor,int $id)
    {
        $user=Access::actor($actor);Access::admin($user);
        if (PeriodoContexto::id()===null) { throw new \Cake\Http\Exception\ForbiddenException('Selecione a entressafra.'); }
        PeriodoContexto::activity($id,true);
        $activity=Access::table('Atividades')->get($id);
        Access::activity($user,$activity);PeriodoContexto::writable((int)$activity->entressafra_id);
        return [$user,$activity];
    }
    private function records(int $id): array
    {
        $records=[];
        foreach (['Apontamentos','Insumos'] as $alias) {
            $records[$alias]=Access::table($alias)->find()->where(['atividade_id'=>$id,'cancelado'=>false])->orderBy(['id'=>'ASC'])->all()->toList();
        }
        return $records;
    }
    private function summary($activity,array $records): array
    {
        $state=[];$blocked=false;
        foreach ($records as $alias=>$rows) {
            $state[$alias]=[];
            foreach ($rows as $row) {
                $state[$alias][]=$row->toArray();
                $blocked=$blocked || $row->controle_pcm==='lancado';
            }
        }
        return ['apontamentos'=>count($records['Apontamentos']),'insumos'=>count($records['Insumos']),
            'bloqueado'=>$blocked,'assinatura'=>hash('sha256',json_encode([$activity->id,$activity->entressafra_id,$activity->os_numero,$state],JSON_THROW_ON_ERROR))];
    }
    public function preview(int $actor,int $activityId): array
    {
        [, $activity]=$this->activity($actor,$activityId);
        return $this->summary($activity,$this->records($activityId));
    }
    public function cancel(int $actor,int $activityId,string $reason,bool $confirmed,string $signature): array
    {
        [$user,$activity]=$this->activity($actor,$activityId);
        $reason=trim($reason);
        if (!$confirmed) { throw new \DomainException('Confirme explicitamente a exclusão dos registros.'); }
        if ($reason==='' || mb_strlen($reason)>3000) { throw new \DomainException('Informe a justificativa da exclusão (até 3000 caracteres).'); }
        $db=Access::table('Apontamentos')->getConnection();
        return $db->transactional(function()use($db,$user,$activity,$activityId,$reason,$signature): array {
            // Writers of hours, supplies and PCM controls lock the season first as well.
            if ($db->getDriver() instanceof \Cake\Database\Driver\Postgres) {
                $db->execute('SELECT id FROM entressafras WHERE id=? FOR UPDATE',[(int)$activity->entressafra_id]);
                $db->execute('SELECT id FROM atividades WHERE id=? FOR UPDATE',[$activityId]);
                foreach (['apontamentos','insumos'] as $table) {
                    $db->execute('SELECT id FROM '.$table.' WHERE atividade_id=? ORDER BY id FOR UPDATE',[$activityId])->fetchAll('assoc');
                }
            }
            [$user,$activity]=$this->activity((int)$user->id,$activityId);
            $records=$this->records($activityId);$summary=$this->summary($activity,$records);
            if ($summary['bloqueado']) { throw new \DomainException('Existem registros já confirmados no TOTVS. Nenhum registro foi cancelado. É necessário tratamento individual específico de conferência.'); }
            if ($summary['apontamentos']+$summary['insumos']===0) { throw new \DomainException('Não há registros ativos para cancelar nesta O.S. O lote já pode ter sido cancelado.'); }
            if (!hash_equals($summary['assinatura'],$signature)) { throw new \DomainException('Os registros da O.S. mudaram. Confira novamente as quantidades e confirme a exclusão.'); }
            $batch=bin2hex(random_bytes(16));$now=gmdate('Y-m-d H:i:s');$ids=['apontamentos'=>[],'insumos'=>[]];
            $context=['lote_chave'=>$batch,'atividade_id'=>$activityId,'os_numero'=>$activity->os_numero,'entressafra_id'=>(int)$activity->entressafra_id];
            foreach ($records as $alias=>$rows) {
                $table=Access::table($alias);$supplies=$alias==='Insumos';
                foreach ($rows as $row) {
                    $before=$row->toArray();$id=(int)$row->id;
                    $row->cancelado=true;$row->alterado_por=$user->id;$row->modified=$now;$row->revisao_pcm=(int)$row->revisao_pcm+1;
                    $table->saveOrFail($row);
                    Access::audit($user,$supplies?'insumo_cancelar':'cancelar',$supplies?null:$id,$before,$row->toArray()+$context,$reason,$supplies?$id:null);
                    $ids[$supplies?'insumos':'apontamentos'][]=$id;
                }
            }
            // Link the batch summary to a record so it is available in the O.S. history.
            Access::audit($user,'lote_cancelamento_os',$ids['apontamentos'][0]??null,
                $context+['situacao'=>'ativos','ids'=>$ids],
                $context+['situacao'=>'cancelados','ids'=>$ids,'apontamentos'=>$summary['apontamentos'],'insumos'=>$summary['insumos'],'cancelado_em'=>$now],
                $reason,$ids['insumos'][0]??null);
            return ['apontamentos'=>$summary['apontamentos'],'insumos'=>$summary['insumos'],'os_numero'=>$activity->os_numero];
        });
    }
}
