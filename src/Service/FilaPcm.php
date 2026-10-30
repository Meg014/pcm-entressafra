<?php
declare(strict_types=1);
namespace App\Service;
final class FilaPcm
{
    private function db() { return Access::table('Apontamentos')->getConnection(); }
    private function pendingQuery(array $filters=[]): string
    {
        $number=trim((string)($filters['os_numero']??''));
        if ($number!=='' && !preg_match('/^[0-9]{1,60}$/D',$number)) { throw new \DomainException('Número da O.S. inválido.'); }
        $sector=$number!==''?'':($filters['setor']??'');
        $sectorWhere='';
        if (ctype_digit((string)$sector) && (int)$sector>0) {
            $sectorWhere=' AND c.area_curva_id='.(int)$sector;
        } elseif (is_string($sector) && preg_match('/^equipe_(\d+)$/D',$sector,$match)) {
            $sectorWhere=' AND a.caderno_id IS NULL AND a.setor_executante_id='.(int)$match[1];
        } elseif ($sector!=='') {
            $sectorWhere=' AND 1=0';
        }
        if ($number!=='') { $sectorWhere=" AND a.os_numero='".$number."'"; }
        return "SELECT a.id,a.os_numero,a.os_filial,a.servico,a.bem,s.nome AS setor,
            COALESCE(p.quantidade,0) AS quantidade,COALESCE(p.quantidade,0) AS apontamentos_pendentes,
            COALESCE(i.quantidade,0) AS insumos_pendentes,COALESCE(p.minutos,0) AS minutos,
            CASE WHEN p.recebido IS NULL THEN i.recebido WHEN i.recebido IS NULL THEN p.recebido
                 WHEN p.recebido < i.recebido THEN p.recebido ELSE i.recebido END AS recebido
            FROM atividades a LEFT JOIN setores s ON s.id=a.setor_executante_id
            LEFT JOIN cadernos c ON c.id=a.caderno_id
            LEFT JOIN (SELECT atividade_id,COUNT(*) AS quantidade,SUM(minutos) AS minutos,MIN(created) AS recebido
                FROM apontamentos WHERE NOT cancelado AND controle_pcm IN ('pendente','conferido') GROUP BY atividade_id) p ON p.atividade_id=a.id
            LEFT JOIN (SELECT atividade_id,COUNT(*) AS quantidade,MIN(created) AS recebido
                FROM insumos WHERE NOT cancelado AND controle_pcm='pendente' GROUP BY atividade_id) i ON i.atividade_id=a.id
            WHERE (p.quantidade>0 OR i.quantidade>0)".(PeriodoContexto::id()!==null?' AND a.entressafra_id='.(int)PeriodoContexto::id():'').$sectorWhere;
    }
    public function queue($user,?int $page=null,array $filters=[]): array
    {
        Access::pcm($user);
        $progress=$filters['evolucao']??'';
        $rows=$this->db()->execute($this->pendingQuery($filters).' ORDER BY recebido,a.id'.($page===null||$progress!==''?'':' LIMIT 10 OFFSET '.((max(1,$page)-1)*10)))->fetchAll('assoc');
        foreach($rows as &$row)$row+=OrderProgress::status((int)$row['id']);
        unset($row);
        if ($progress!=='') {
            $rows=array_values(array_filter($rows,static fn(array $row): bool=>match($progress) {
                'zero'=>(float)$row['percentual_atual']===0.0,
                'andamento'=>(float)$row['percentual_atual']>0 && (float)$row['percentual_atual']<100,
                'concluidas'=>(float)$row['percentual_atual']===100.0,
                default=>false,
            }));
            if ($page!==null) { $rows=array_slice($rows,(max(1,$page)-1)*10,10); }
        }
        return $rows;
    }
    public function queueCount($user,array $filters=[]): int
    {
        Access::pcm($user);
        if (($filters['evolucao']??'')!=='') { return count($this->queue($user,null,$filters)); }
        return (int)$this->db()->execute('SELECT COUNT(*) AS total FROM ('.$this->pendingQuery($filters).') pendencias')->fetch('assoc')['total'];
    }
    public function queuePage($user,int $requested,array $filters=[]): array
    {
        // Resolve evolution once, filter on the server, then count and paginate the same result.
        $filtered=($filters['evolucao']??'')!==''?$this->queue($user,null,$filters):null;
        $total=$filtered===null?$this->queueCount($user,$filters):count($filtered);
        $pages=max(1,(int)ceil($total/10));$page=max(1,min($requested,$pages));
        $rows=$filtered===null?$this->queue($user,$page,$filters):array_slice($filtered,($page-1)*10,10);
        return compact('rows','total','page','pages');
    }
    public function sectorOptions($user): array
    {
        Access::pcm($user);$options=[];$season=PeriodoContexto::id();
        if ($season!==null) {
            foreach ((new SetoresApresentacao())->availableAreas($season)->orderBy(['nome'=>'ASC'])->all() as $area) {
                $options[(string)$area->id]=$area->nome;
            }
        }
        // Match the fallback sector already displayed for orders without a notebook.
        $query=Access::activityScope(Access::table('Atividades')->find(),$user)
            ->select(['setor_executante_id'])->where(['caderno_id IS'=>null,'setor_executante_id IS NOT'=>null]);
        $ids=$query->all()->extract('setor_executante_id')->toList();
        if ($ids) {
            foreach (Access::table('Setores')->find()->where(['id IN'=>$ids])->orderBy(['nome'=>'ASC'])->all() as $sector) {
                $options['equipe_'.$sector->id]=OsApresentacao::equipe($sector->nome).' (sem caderno)';
            }
        }
        return $options;
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
                if(!$row || $row->cancelado)throw new \DomainException('Registro cancelado ou fora da O.S. selecionada.');
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
