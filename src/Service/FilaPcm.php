<?php
declare(strict_types=1);
namespace App\Service;
final class FilaPcm
{
    private function db() { return Access::table('Apontamentos')->getConnection(); }
    public function queue($user,?int $page=null): array
    {
        Access::pcm($user);
        return $this->db()->execute("SELECT a.id,a.os_numero,a.os_filial,a.servico,a.bem,s.nome AS setor,COUNT(p.id) AS quantidade,SUM(p.minutos) AS minutos,MIN(p.created) AS recebido FROM apontamentos p JOIN atividades a ON a.id=p.atividade_id LEFT JOIN setores s ON s.id=a.setor_executante_id WHERE NOT p.cancelado AND p.controle_pcm IN ('pendente','conferido') ".(PeriodoContexto::id()!==null?' AND a.entressafra_id='.(int)PeriodoContexto::id():'')." GROUP BY a.id,a.os_numero,a.os_filial,a.servico,a.bem,s.nome ORDER BY MIN(p.created),a.id".($page===null?'':' LIMIT 10 OFFSET '.((max(1,$page)-1)*10)))->fetchAll('assoc');
    }
    public function metrics(array $ids): array
    {
        if(!$ids)return []; $ids=array_map('intval',$ids);$out=[];
        $sql="SELECT atividade_id,COUNT(*) AS quantidade,SUM(CASE WHEN NOT cancelado THEN minutos ELSE 0 END) AS minutos,SUM(CASE WHEN NOT cancelado AND controle_pcm IN ('pendente','conferido') THEN minutos ELSE 0 END) AS pendentes,SUM(CASE WHEN NOT cancelado AND controle_pcm='lancado' THEN minutos ELSE 0 END) AS tratados,MAX(created) AS recebido FROM apontamentos WHERE atividade_id IN (".implode(',',array_fill(0,count($ids),'?')).') GROUP BY atividade_id';
        foreach($this->db()->execute($sql,$ids)->fetchAll('assoc') as $r){$out[$r['atividade_id']]=$r;}return $out;
    }
    public function confirm(int $actor,int $activity,array $selection,string $action,string $reason,bool $confirmed): int
    {
        $u=Access::actor($actor);Access::pcm($u);$a=Access::table('Atividades')->get($activity);Access::activity($u,$a);
        if(!$confirmed || !in_array($action,['lancado','pendente'],true) || !$selection || count($selection)>200) { throw new \DomainException('Selecione de 1 a 200 apontamentos e confirme a operação.'); }
        if($action==='pendente' && trim($reason)==='') { throw new \DomainException('Reabrir uma confirmação exige justificativa do PCM.'); }
        if(mb_strlen($reason)>3000){throw new \DomainException('Justificativa muito extensa.');}
        $ids=[];foreach($selection as $row){$id=filter_var($row['id']??null,FILTER_VALIDATE_INT);$rev=filter_var($row['revisao']??null,FILTER_VALIDATE_INT);if(!$id||$rev===false||$rev<0||isset($ids[$id]))throw new \DomainException('Seleção inválida ou repetida.');$ids[$id]=$rev;}ksort($ids);
        return $this->db()->transactional(function() use($u,$activity,$ids,$action,$reason): int {
            $table=Access::table('Apontamentos');$statuses=[];$now=gmdate('Y-m-d H:i:s');
            foreach($ids as $id=>$rev){
                if($this->db()->getDriver() instanceof \Cake\Database\Driver\Postgres){$this->db()->execute('SELECT id FROM apontamentos WHERE id=? FOR UPDATE',[$id]);}
                $row=$table->get($id);
                if((int)$row->atividade_id!==$activity || $row->cancelado)throw new \DomainException('Apontamento cancelado ou fora da O.S. selecionada.');
                if((int)$row->revisao_pcm!==$rev)throw new \DomainException('Apontamento alterado por outra pessoa. Atualize a tela.');
                if(($action==='lancado'&&$row->controle_pcm==='lancado')||($action==='pendente'&&$row->controle_pcm!=='lancado'))throw new \DomainException('Apontamento já tratado ou confirmação não corresponde ao estado atual.');
                $before=$row->toArray();$statuses[]=['id'=>$id,'anterior'=>$row->controle_pcm,'novo'=>$action];
                $row->controle_pcm=$action;$row->revisao_pcm=$rev+1;$row->alterado_por=$u->id;$row->modified=$now;
                $row->confirmado_por=$action==='lancado'?$u->id:null;$row->confirmado_em=$action==='lancado'?$now:null;
                $table->saveOrFail($row);Access::audit($u,$action==='lancado'?'confirmacao_totvs':'reabertura_totvs',$id,$before,$row->toArray(),trim($reason)?:'Confirmação de lançamento manual no TOTVS.');
            }
            Access::audit($u,'lote_controle_totvs',null,null,['atividade_id'=>$activity,'apontamentos'=>$statuses,'confirmado_em'=>$now],trim($reason)?:null);
            return count($ids);
        });
    }
}
