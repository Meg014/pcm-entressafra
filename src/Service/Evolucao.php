<?php
declare(strict_types=1);
namespace App\Service;

use Cake\Datasource\ConnectionManager;

/** PostgreSQL/SQLite only. Never opens a TOTVS connection. Percentages use 0..100. */
final class Evolucao
{
    private array $histories=[];
    public function loadHistory(int $season): void
    {
        PeriodoContexto::season($season);
        foreach ($this->activities($season) as $a) { $this->histories[$a['id']]=[]; }
        $sql='SELECT r.*,s.referencia,u.nome AS responsavel FROM evolucao_registros r JOIN evolucao_semanas s ON s.id=r.semana_id JOIN usuarios u ON u.id=r.usuario_id WHERE s.entressafra_id=? ORDER BY s.referencia,r.revisao';
        foreach ($this->db()->execute($sql,[$season])->fetchAll('assoc') as $r) { $this->histories[$r['atividade_id']][]=$r; }
    }
    private function db() { return ConnectionManager::get('default'); }
    public static function number(mixed $value, float $max=100): float
    {
        if (!is_scalar($value) || !preg_match('/^\d+(?:[.,]\d{1,2})?$/D',trim((string)$value))) { throw new \DomainException('Informe um número não negativo, com até duas casas decimais.'); }
        $n=(float)str_replace(',','.',trim((string)$value));
        if (!is_finite($n) || $n>$max) { throw new \DomainException('Valor fora do intervalo permitido.'); }
        return $n;
    }
    private function lock(int $season): void
    {
        if ($this->db()->getDriver() instanceof \Cake\Database\Driver\Postgres) { $this->db()->execute('SELECT id FROM entressafras WHERE id=? FOR UPDATE',[$season]); }
    }
    public function calendar(int $season): array
    {
        PeriodoContexto::season($season);
        $s=Access::table('Entressafras')->get($season);
        $saved=$this->db()->execute('SELECT * FROM evolucao_semanas WHERE entressafra_id=? ORDER BY referencia',[$season])->fetchAll('assoc');
        $start=new \DateTimeImmutable($s->data_inicio->format('Y-m-d')); $end=new \DateTimeImmutable($s->data_fim->format('Y-m-d'));
        $days=[$start->format('Y-m-d')];
        for ($d=$start->modify('next monday');$d<=$end;$d=$d->modify('+7 days')) { $days[]=$d->format('Y-m-d'); }
        $days[]=$end->format('Y-m-d');
        $weeks=array_column($saved,null,'referencia');
        foreach (array_unique($days) as $day) { $weeks[$day]??=['id'=>null,'referencia'=>$day]; }
        ksort($weeks);
        return array_values($weeks);
    }
    public function configureCalendar(int $userId,int $season,array $dates): void
    {
        PeriodoContexto::writable($season);
        $u=Access::actor($userId); Access::pcm($u);
        $this->db()->transactional(function() use($u,$season,$dates) {
            $this->lock($season); $s=Access::table('Entressafras')->get($season);
            $start=$s->data_inicio->format('Y-m-d'); $end=$s->data_fim->format('Y-m-d');
            if (!$dates || count($dates)>530) { throw new \DomainException('Calendário vazio ou muito extenso.'); }
            foreach (array_unique($dates) as $d) {
                $date=\DateTimeImmutable::createFromFormat('!Y-m-d',(string)$d);
                if (!$date || $date->format('Y-m-d')!==$d || $d<$start || $d>$end) { throw new \DomainException('Referência fora das datas da entressafra.'); }
                if (!$this->db()->execute('SELECT id FROM evolucao_semanas WHERE entressafra_id=? AND referencia=?',[$season,$d])->fetch('assoc')) {
                    $this->db()->insert('evolucao_semanas',['entressafra_id'=>$season,'referencia'=>$d]);
                }
            }
            // Existing references are immutable: never delete a reference with history or baseline.
            Access::audit($u,'calendario_evolucao',null,null,['entressafra_id'=>$season,'referencias'=>$dates],null);
        });
    }
    public function activities(int $season): array
    {
        PeriodoContexto::season($season);
        return $this->db()->execute("SELECT a.*,COALESCE(c.nome,'Sem caderno') AS caderno_nome,s.nome AS setor_nome FROM atividades a LEFT JOIN cadernos c ON c.id=a.caderno_id LEFT JOIN setores s ON s.id=a.setor_executante_id WHERE a.entressafra_id=? ORDER BY a.os_numero,a.id",[$season])->fetchAll('assoc');
    }
    public function history(int $activity): array
    {
        if (isset($this->histories[$activity])) { return $this->histories[$activity]; }
        PeriodoContexto::activity($activity);
        return $this->db()->execute('SELECT r.*,s.referencia,u.nome AS responsavel FROM evolucao_registros r JOIN evolucao_semanas s ON s.id=r.semana_id JOIN usuarios u ON u.id=r.usuario_id WHERE r.atividade_id=? ORDER BY s.referencia,r.revisao',[$activity])->fetchAll('assoc');
    }
    public function state(int $activity,string $reference,?string $asOf=null): array
    {
        $rows=$this->history($activity); $official=[];
        foreach ($rows as $r) { if ($r['referencia']<=$reference && ($asOf===null || $r['created']<=$asOf)) { $official[$r['referencia']]=$r; } }
        ksort($official); $last=$official?end($official):null; $current=$official[$reference]??null;
        $before=array_filter($official,fn($r)=>$r['referencia']<$reference); $previous=$before?end($before):null;
        $percentual=(float)($last['percentual']??0);$anterior=(float)($previous['percentual']??0);
        return ['percentual'=>$percentual,'anterior'=>$anterior,'variacao'=>round($percentual-$anterior,2),'revisao'=>(int)($current['revisao']??0),'pendente'=>$current===null,'registro'=>$current];
    }
    public function saveBatch(int $userId,int $season,string $reference,array $rows,string $key,bool $allowAdvance=false): array
    {
        PeriodoContexto::writable($season);
        $u=Access::actor($userId);
        if (!preg_match('/^[a-zA-Z0-9-]{16,80}$/D',$key) || !$rows || count($rows)>200) { throw new \DomainException('Lote inválido (1 a 200 alterações).'); }
        $hash=hash('sha256',json_encode([$season,$reference,$rows],JSON_THROW_ON_ERROR));
        return $this->db()->transactional(function() use($u,$season,$reference,$rows,$key,$hash,$allowAdvance) {
            $this->lock($season);
            $this->histories=[];
            $old=$this->db()->execute('SELECT * FROM evolucao_lotes WHERE usuario_id=? AND chave=?',[$u->id,$key])->fetch('assoc');
            if ($old) { if ($old['hash']!==$hash) { throw new \DomainException('Chave de lote já utilizada com conteúdo diferente.'); } return json_decode($old['resultado'],true,512,JSON_THROW_ON_ERROR); }
            $week=$this->db()->execute('SELECT id FROM evolucao_semanas WHERE entressafra_id=? AND referencia=?',[$season,$reference])->fetch('assoc');
            if (!$week) {
                if (!in_array($reference,array_column($this->calendar($season),'referencia'),true)) {
                    throw new \DomainException('Referência fora das semanas da entressafra.');
                }
                $this->db()->insert('evolucao_semanas',['entressafra_id'=>$season,'referencia'=>$reference]);
                $week=$this->db()->execute('SELECT id FROM evolucao_semanas WHERE entressafra_id=? AND referencia=?',[$season,$reference])->fetch('assoc');
            }
            $today=(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
            if ($reference>$today) { throw new \DomainException('Não é permitido registrar execução futura.'); }
            $seen=[]; $ids=[];
            foreach ($rows as $row) {
                $id=filter_var($row['atividade_id']??null,FILTER_VALIDATE_INT);
                if (!$id || isset($seen[$id])) { throw new \DomainException('Atividade inválida ou repetida no lote.'); } $seen[$id]=true;
                $a=Access::table('Atividades')->get($id); Access::activity($u,$a);
                if ((int)$a->entressafra_id!==$season || $a->setor_executante_id===null) { throw new \DomainException('Atividade fora da entressafra ou setor pendente.'); }
                $state=$this->state($id,$reference); $value=self::number($row['percentual']??null);
                if (!array_key_exists('revisao',$row) || (string)$row['revisao']!==(string)$state['revisao']) { throw new \DomainException('Outra pessoa alterou esta O.S. Atualize a tela antes de salvar.'); }
                $reason=trim((string)($row['motivo']??'')); $note=trim((string)($row['observacoes']??''));
                if (mb_strlen($note)>3000 || mb_strlen($reason)>3000) { throw new \DomainException('Observação ou justificativa muito extensa.'); }
                $correction=($state['revisao']>0 && (!$allowAdvance || $value<$state['percentual'])) || $value<$state['anterior'];
                // A retroactive insertion must not silently invalidate a later official value.
                foreach ($this->history($id) as $r) {
                    if ($r['referencia']>$reference) {
                        $later=$this->state($id,$r['referencia']);
                        if ($value>$later['percentual']) { throw new \DomainException('O valor retroativo supera um percentual posterior. Corrija primeiro o histórico posterior pelo PCM.'); }
                    }
                }
                if ($correction) { Access::pcm($u); if ($reason==='') { throw new \DomainException('Retificações e reduções exigem justificativa do PCM.'); } }
                $this->db()->insert('evolucao_registros',['atividade_id'=>$id,'semana_id'=>$week['id'],'percentual'=>$value,'revisao'=>$state['revisao']+1,'usuario_id'=>$u->id,'observacoes'=>$note,'motivo'=>$reason,'created'=>gmdate('Y-m-d H:i:s')]);
                unset($this->histories[$id]);
                Access::audit($u,$correction?'evolucao_correcao':'evolucao_lancamento',null,$state,['atividade_id'=>$id,'referencia'=>$reference,'percentual'=>$value,'retroativo'=>$reference<$today],$reason?:null); $ids[]=$id;
            }
            $result=['quantidade'=>count($ids),'atividades'=>$ids];
            $this->db()->insert('evolucao_lotes',['chave'=>$key,'hash'=>$hash,'usuario_id'=>$u->id,'created'=>gmdate('Y-m-d H:i:s'),'resultado'=>json_encode($result,JSON_THROW_ON_ERROR)]);
            return $result;
        });
    }
    public function versions(int $season,string $type): array
    { PeriodoContexto::season($season); return $this->db()->execute('SELECT * FROM evolucao_versoes WHERE entressafra_id=? AND tipo=? ORDER BY id DESC',[$season,$type])->fetchAll('assoc'); }
    public function version(int $userId,int $season,string $type,string $name,array $content,bool $approve=false,?int $calendarId=null,?int $baseVersion=null): int
    {
        PeriodoContexto::writable($season);
        $u=Access::actor($userId); Access::pcm($u); if ($approve) { Access::admin($u); }
        if (!in_array($type,['pesos','metas'],true) || trim($name)==='' || mb_strlen($name)>150) { throw new \DomainException('Tipo ou nome de versão inválido.'); }
        if($type==='metas'&&$approve&&!$calendarId&&(new Calendarios())->rows($season))throw new \DomainException('Selecione o calendário de área/prazo para aprovar a programação. A linha geral legada não substitui os prazos operacionais.');
        return $this->db()->transactional(function() use($u,$season,$type,$name,$content,$approve,$calendarId,$baseVersion) {
            $this->lock($season); $clean=[];
            if($baseVersion!==null) {
                $latest=$this->versions($season,$type)[0]??null;
                if((int)($latest['id']??0)!==$baseVersion)throw new \DomainException('Outra pessoa alterou os pesos. Atualize a página antes de salvar.');
            }
            if ($type==='pesos') {
                $activities=array_column($this->activities($season),null,'id');
                foreach ($content as $id=>$v) {
                    if (!isset($activities[$id])) { throw new \DomainException('Peso de atividade fora da entressafra.'); }
                    $n=self::number($v,999999999); if ($n<=0) { throw new \DomainException('Pesos informados devem ser positivos. Deixe as pendências vazias.'); } $clean[(int)$id]=$n;
                }
                if ($approve && (!$activities || count($clean)!==count($activities))) { throw new \DomainException('Aprovação exige peso conferido para todas as O.S. do escopo.'); }
            } else {
                if($calendarId){$c=array_values(array_filter((new Calendarios())->rows($season),fn($r)=>(int)$r['id']===$calendarId));if(!$c)throw new \DomainException('Calendário de outra entressafra.');$dates=(new Calendarios())->references($c[0]);if(!$dates)throw new \DomainException('Confira o início do calendário antes de programar.');}else{$dates=array_column($this->calendar($season),'referencia');} $last=0;
                ksort($content);
                foreach ($content as $date=>$v) {
                    if (!in_array($date,$dates,true)) { throw new \DomainException('Meta fora do calendário confirmado.'); }
                    $n=self::number($v); if ($n<$last) { throw new \DomainException('Metas devem ser acumuladas e não decrescentes.'); } $clean[$date]=$last=$n;
                }
                if ($approve && (count($clean)!==count($dates) || $last!==100.0)) { throw new \DomainException('A linha de base deve conter todas as semanas e terminar em 100%.'); }
            }
            $now=gmdate('Y-m-d H:i:s');
            $t=Access::table('EvolucaoVersoes');
            $e=Access::entity($t,['entressafra_id'=>$season,'tipo'=>$type,'calendario_id'=>$type==='metas'?$calendarId:null,'nome'=>trim($name),'conteudo'=>json_encode($clean,JSON_THROW_ON_ERROR),'usuario_id'=>$u->id,'created'=>$now,'aprovado_por'=>$approve?$u->id:null,'aprovado_em'=>$approve?$now:null]); $t->saveOrFail($e);
            foreach ($clean as $key=>$value) {
                if ($type==='pesos') { $this->db()->insert('evolucao_pesos',['versao_id'=>$e->id,'atividade_id'=>$key,'peso'=>$value]); }
                else {
                    $week=$this->db()->execute('SELECT id FROM evolucao_semanas WHERE entressafra_id=? AND referencia=?',[$season,$key])->fetch('assoc');
                    if (!$week) { throw new \DomainException('Confirme o calendário antes de gravar metas.'); }
                    $this->db()->insert('evolucao_metas',['versao_id'=>$e->id,'semana_id'=>$week['id'],'percentual'=>$value]);
                }
            }
            Access::audit($u,$approve?'evolucao_aprovacao':'evolucao_versao',null,null,['versao_id'=>$e->id,'tipo'=>$type,'entressafra_id'=>$season],null); return (int)$e->id;
        });
    }
    public static function weighted(array $values,array $weights): ?float
    {
        $sum=0.;$total=0.;
        foreach ($values as $id=>$value) { if (!isset($weights[$id]) || $weights[$id]<=0) { return null; } $sum+=$weights[$id]*$value; $total+=$weights[$id]; }
        return $total>0?round($sum/$total,4):null;
    }
    public function report(int $season,array $activities,string $reference,?int $weightId=null,?int $planId=null,?string $asOf=null): array
    {
        PeriodoContexto::season($season);
        if ($asOf!==null) { $activities=array_values(array_filter($activities,fn($a)=>$a['created']<=$asOf)); }
        $this->loadHistory($season);
        $choose=function(string $type,?int $id) use($season,$asOf): ?array {
            foreach ($this->versions($season,$type) as $v) {
                if ($v['aprovado_em']!==null && ($asOf===null || $v['aprovado_em']<=$asOf) && ($id===null || (int)$v['id']===$id)) { return $v; }
            } return null;
        };
        $weight=$choose('pesos',$weightId); $plan=$choose('metas',$planId);
        $scopedCalendars=(new Calendarios())->rows($season);$calendarService=new Calendarios();
        $weights=$weight?json_decode($weight['conteudo'],true,512,JSON_THROW_ON_ERROR):[];
        $targets=$plan?json_decode($plan['conteudo'],true,512,JSON_THROW_ON_ERROR):[];
        $points=[]; $counts=['total'=>count($activities),'sem_inicio'=>0,'andamento'=>0,'concluidas'=>0,'pendentes'=>0]; $details=[];
        foreach ($activities as $a) {
            $s=$this->state((int)$a['id'],$reference,$asOf); $counts[$s['percentual']==0?'sem_inicio':($s['percentual']==100?'concluidas':'andamento')]++;
            $counts['pendentes']+=(int)$s['pendente'];$period=$calendarService->resolve($a); $details[]=$a+['estado'=>$s,'peso'=>$weights[$a['id']]??null,'prazo'=>$period['data_fim']??null,'programado_individual'=>$scopedCalendars?$calendarService->planned($a,$reference,$asOf):null];
        }
        foreach ($this->calendar($season) as $week) {
            $date=$week['referencia']; $values=[];$planned=[];$allPlanned=true;
            foreach ($activities as $a) { $values[$a['id']]=$this->state((int)$a['id'],$date,$asOf)['percentual'];if($scopedCalendars){$p=$calendarService->planned($a,$date,$asOf);if($p===null)$allPlanned=false;else$planned[$a['id']]=$p;} }
            $points[]=['simples'=>$date<=$reference&&$values?round(array_sum($values)/count($values),4):null,'referencia'=>$date,'programado'=>$scopedCalendars?($allPlanned?self::weighted($planned,$weights):null):($targets[$date]??null),'realizado'=>$date<=$reference?self::weighted($values,$weights):null,'tendencia'=>null];
        }
        $past=array_values(array_filter($points,fn($p)=>$p['referencia']<=$reference && $p['realizado']!==null));
        $observed=array_values(array_filter($past,function($p)use($activities,$asOf) {
            foreach($activities as $a) { if (!$this->state((int)$a['id'],$p['referencia'],$asOf)['pendente']) { return true; } } return false;
        }));
        if (count($observed)>=2) {
            $first=$observed[max(0,count($observed)-4)]; $last=end($observed);
            $days=(strtotime($last['referencia'])-strtotime($first['referencia']))/86400;
            if ($days>0) { foreach ($points as &$p) { if ($p['referencia']>=$last['referencia']) { $p['tendencia']=round(max(0,min(100,$last['realizado']+($last['realizado']-$first['realizado'])/$days*(strtotime($p['referencia'])-strtotime($last['referencia']))/86400)),2); } } unset($p); }
        }
        $last=$past?end($past):null; $previous=count($past)>1?$past[count($past)-2]:null;
        $actual=$last['realizado']??null; $target=$scopedCalendars?($last['programado']??null):($targets[$reference]??null);
        // The approved plan is for the complete season, never silently reused as a sector target.
        $whole=$this->activities($season);
        if ($asOf!==null) { $whole=array_values(array_filter($whole,fn($a)=>$a['created']<=$asOf)); }
        $full=count($activities)===count($whole);
        if (!$full && !$scopedCalendars) { $target=null; foreach($points as &$p) { $p['programado']=null; } unset($p); }
        $groups=['setores'=>[],'cadernos'=>[]];
        foreach($details as $a) {
            foreach(['setores'=>['setor_executante_id','setor_nome'],'cadernos'=>['caderno_id','caderno_nome']] as $type=>[$id,$label]) {
                $key=$a[$id]??0; $groups[$type][$key]['nome']=$a[$label]??'Pendente';
                $groups[$type][$key]['valores'][$a['id']]=$a['estado']['percentual'];
            }
        }
        foreach($groups as &$items) { foreach($items as &$g) { $g['realizado']=self::weighted($g['valores'],$weights); $g['quantidade']=count($g['valores']); $g['simples']=round(array_sum($g['valores'])/$g['quantidade'],4); $g['soma_pesos']=array_sum(array_intersect_key($weights,$g['valores'])); } unset($g); } unset($items);
        $simpleValues=array_column(array_column($details,'estado'),'percentual');
        $simple=$simpleValues?round(array_sum($simpleValues)/count($simpleValues),4):null;
        return compact('counts','details','points','weight','plan','groups','simple')+['realizado'=>$actual,'programado'=>$target,'desvio'=>$actual!==null&&$target!==null?$actual-$target:null,'variacao'=>$actual!==null&&$previous!==null?$actual-$previous['realizado']:null,'escopo_completo'=>$full];
    }
}
