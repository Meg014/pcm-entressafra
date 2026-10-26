<?php
declare(strict_types=1);
namespace App\Service;
final class FilaPcm
{
    private function db() { return Access::table('Apontamentos')->getConnection(); }
    private function pendingQuery(): string
    {
        return "SELECT a.id,a.os_numero,a.os_filial,a.servico,a.bem,s.nome AS setor,
            COALESCE(p.quantidade,0) AS quantidade,COALESCE(p.quantidade,0) AS apontamentos_pendentes,
            COALESCE(i.quantidade,0) AS insumos_pendentes,COALESCE(p.minutos,0) AS minutos,
            CASE WHEN p.recebido IS NULL THEN i.recebido WHEN i.recebido IS NULL THEN p.recebido
                 WHEN p.recebido < i.recebido THEN p.recebido ELSE i.recebido END AS recebido
            FROM atividades a LEFT JOIN setores s ON s.id=a.setor_executante_id
            LEFT JOIN (SELECT atividade_id,COUNT(*) AS quantidade,SUM(minutos) AS minutos,MIN(created) AS recebido
                FROM apontamentos WHERE NOT cancelado AND controle_pcm IN ('pendente','conferido') GROUP BY atividade_id) p ON p.atividade_id=a.id
            LEFT JOIN (SELECT atividade_id,COUNT(*) AS quantidade,MIN(created) AS recebido
                FROM insumos WHERE controle_pcm='pendente' GROUP BY atividade_id) i ON i.atividade_id=a.id
            WHERE (p.quantidade>0 OR i.quantidade>0)".(PeriodoContexto::id()!==null?' AND a.entressafra_id='.(int)PeriodoContexto::id():'');
    }
    public function queue($user,?int $page=null): array
    {
        Access::pcm($user);
        $rows=$this->db()->execute($this->pendingQuery().' ORDER BY recebido,a.id'.($page===null?'':' LIMIT 10 OFFSET '.((max(1,$page)-1)*10)))->fetchAll('assoc');
        foreach($rows as &$row)$row+=OrderProgress::status((int)$row['id']);
        unset($row);
        return $rows;
    }
    public function queueCount($user): int
    {
        Access::pcm($user);
        return (int)$this->db()->execute('SELECT COUNT(*) AS total FROM ('.$this->pendingQuery().') pendencias')->fetch('assoc')['total'];
    }
    public function metrics(array $ids): array
    {
        if(!$ids)return []; $ids=array_map('intval',$ids);$out=[];
        $sql="SELECT atividade_id,COUNT(*) AS quantidade,SUM(CASE WHEN NOT cancelado THEN minutos ELSE 0 END) AS minutos,SUM(CASE WHEN NOT cancelado AND controle_pcm IN ('pendente','conferido') THEN minutos ELSE 0 END) AS pendentes,SUM(CASE WHEN NOT cancelado AND controle_pcm='lancado' THEN minutos ELSE 0 END) AS tratados,MAX(created) AS recebido FROM apontamentos WHERE atividade_id IN (".implode(',',array_fill(0,count($ids),'?')).') GROUP BY atividade_id';
        foreach($this->db()->execute($sql,$ids)->fetchAll('assoc') as $r){$out[$r['atividade_id']]=$r;}return $out;
    }
    public function confirm(int $actor,int $activity,array $selection,string $action,string $reason,bool $confirmed): int
    {
        return $this->control($actor,$activity,$selection,$action,$reason,$confirmed,false);
    }
    public function confirmSupplies(int $actor,int $activity,array $selection,string $action,string $reason,bool $confirmed): int
    {
        return $this->control($actor,$activity,$selection,$action,$reason,$confirmed,true);
    }
    private function control(int $actor,int $activity,array $selection,string $action,string $reason,bool $confirmed,bool $supplies): int
    {
        $u=Access::actor($actor);Access::pcm($u);$a=Access::table('Atividades')->get($activity);Access::activity($u,$a);PeriodoContexto::writable((int)$a->entressafra_id);
        if(!$confirmed || !in_array($action,['lancado','pendente'],true) || !$selection || count($selection)>200) { throw new \DomainException('Selecione de 1 a 200 registros desta categoria e confirme a operação.'); }
        if($action==='pendente' && trim($reason)==='') { throw new \DomainException('Reabrir uma confirmação exige justificativa do PCM.'); }
        if(mb_strlen($reason)>3000){throw new \DomainException('Justificativa muito extensa.');}
        $ids=[];foreach($selection as $row){if(!is_array($row))throw new \DomainException('Seleção inválida.');$id=filter_var($row['id']??null,FILTER_VALIDATE_INT);$rev=filter_var($row['revisao']??null,FILTER_VALIDATE_INT);if(!$id||$id<1||$rev===false||$rev<0||isset($ids[$id]))throw new \DomainException('Seleção inválida ou repetida.');$ids[$id]=$rev;}ksort($ids);
        return $this->db()->transactional(function() use($u,$activity,$ids,$action,$reason,$supplies,$a): int {
            $table=Access::table($supplies?'Insumos':'Apontamentos');$sqlTable=$supplies?'insumos':'apontamentos';$statuses=[];$now=gmdate('Y-m-d H:i:s');
            if($this->db()->getDriver() instanceof \Cake\Database\Driver\Postgres)$this->db()->execute('SELECT id FROM entressafras WHERE id=? FOR UPDATE',[(int)$a->entressafra_id]);
            PeriodoContexto::writable((int)$a->entressafra_id);
            foreach($ids as $id=>$rev){
                if($this->db()->getDriver() instanceof \Cake\Database\Driver\Postgres){$this->db()->execute('SELECT id FROM '.$sqlTable.' WHERE id=? FOR UPDATE',[$id]);}
                $row=$table->find()->where(['id'=>$id,'atividade_id'=>$activity])->first();
                if(!$row || (!$supplies && $row->cancelado))throw new \DomainException('Registro cancelado ou fora da O.S. selecionada.');
                if((int)$row->revisao_pcm!==$rev)throw new \DomainException('Registro alterado por outra pessoa. Atualize a tela.');
                if(($action==='lancado'&&$row->controle_pcm==='lancado')||($action==='pendente'&&$row->controle_pcm!=='lancado'))throw new \DomainException('Registro já tratado ou confirmação não corresponde ao estado atual.');
                $before=$row->toArray();$statuses[]=['id'=>$id,'anterior'=>$row->controle_pcm,'novo'=>$action];
                $row->controle_pcm=$action;$row->revisao_pcm=$rev+1;$row->alterado_por=$u->id;$row->modified=$now;
                $row->confirmado_por=$action==='lancado'?$u->id:null;$row->confirmado_em=$action==='lancado'?$now:null;
                $table->saveOrFail($row);Access::audit($u,($supplies?'insumo_':'').($action==='lancado'?'confirmacao_totvs':'reabertura_totvs'),$supplies?null:$id,$before,$row->toArray(),trim($reason)?:'Confirmação de lançamento manual no TOTVS.',$supplies?$id:null);
            }
            Access::audit($u,$supplies?'lote_insumos_totvs':'lote_controle_totvs',null,null,['atividade_id'=>$activity,($supplies?'insumos':'apontamentos')=>$statuses,'confirmado_em'=>$now],trim($reason)?:null);
            return count($ids);
        });
    }
}
