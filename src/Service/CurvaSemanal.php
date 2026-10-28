<?php
declare(strict_types=1);
namespace App\Service;

/** Tuesday closing, immutable snapshots and equal-weight physical evolution. No TOTVS access. */
final class CurvaSemanal
{
    private function db() { return Access::table('EvolucaoVersoes')->getConnection(); }
    private static function date(string $value): \DateTimeImmutable
    {
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value,new \DateTimeZone('America/Sao_Paulo'));
        if (!$date || $date->format('Y-m-d')!==$value) { throw new \DomainException('Data inválida: use AAAA-MM-DD.'); }
        return $date;
    }
    public static function weeks(string $start,string $end): array
    {
        $from=self::date($start);$last=self::date($end);
        if ($from>$last) { throw new \DomainException('Período da entressafra inválido.'); }
        $weeks=[];
        for ($analysis=$from->modify('next tuesday');$from<=$last;$analysis=$analysis->modify('+7 days')) {
            $to=min($analysis->modify('-1 day'),$last);
            $weeks[]=['semana'=>count($weeks)+1,'inicio'=>$from->format('Y-m-d'),'fim'=>$to->format('Y-m-d'),'referencia'=>$analysis->format('Y-m-d')];
            $from=$analysis;
        }
        return $weeks;
    }
    public function areas(int $season): array
    {
        PeriodoContexto::season($season);
        $areas=(new SetoresApresentacao())->availableAreas($season)->find('list')->all()->toArray();
        // Include already classified legacy sectors, without changing their registrations.
        $rows=$this->db()->execute('SELECT DISTINCT ar.id,ar.nome FROM atividades a LEFT JOIN cadernos c ON c.id=a.caderno_id JOIN areas_fabrica ar ON ar.id=COALESCE(a.area_curva_id,c.area_curva_id) WHERE a.entressafra_id=?',[$season])->fetchAll('assoc');
        foreach ($rows as $row) { $areas[$row['id']]=$row['nome']; }
        asort($areas,SORT_NATURAL|SORT_FLAG_CASE);return $areas;
    }
    private function context(int $season,int $area): array
    {
        PeriodoContexto::season($season);
        if (!array_key_exists($area,$this->areas($season))) { throw new \DomainException('Setor fora da entressafra selecionada.'); }
        $row=Access::table('Entressafras')->get($season);
        return [$row,self::weeks($row->data_inicio->format('Y-m-d'),$row->data_fim->format('Y-m-d'))];
    }
    private function lock(int $season): void
    {
        if ($this->db()->getDriver() instanceof \Cake\Database\Driver\Postgres) {
            $this->db()->execute('SELECT id FROM entressafras WHERE id=? FOR UPDATE',[$season]);
        }
    }
    public function versions(int $season,int $area,string $type): array
    {
        $this->context($season,$area);
        return $this->db()->execute('SELECT * FROM evolucao_versoes WHERE entressafra_id=? AND area_curva_id=? AND tipo=? ORDER BY id DESC',[$season,$area,$type])->fetchAll('assoc');
    }
    private function insert($actor,int $season,int $area,string $type,array $content,string $reason,?string $reference=null,?int $revision=null): int
    {
        $now=gmdate('Y-m-d H:i:s');$table=Access::table('EvolucaoVersoes');
        $entity=Access::entity($table,['entressafra_id'=>$season,'area_curva_id'=>$area,'tipo'=>$type,'nome'=>($type==='curva_meta'?'Programação semanal':'Fechamento').' '.$reference,'conteudo'=>json_encode($content,JSON_THROW_ON_ERROR),'usuario_id'=>$actor->id,'created'=>$now,'aprovado_por'=>$actor->id,'aprovado_em'=>$now,'referencia_semanal'=>$reference,'revisao_semanal'=>$revision]);
        $table->saveOrFail($entity);
        $action=$type==='curva_meta'?'curva_metas':(!empty($content['anterior'])?'curva_fechamento_correcao':'curva_fechamento');
        $before=empty($content['anterior'])?null:['versao_id'=>$content['anterior']];
        Access::audit($actor,$action,null,$before,['versao_id'=>(int)$entity->id,'entressafra_id'=>$season,'area_curva_id'=>$area,'referencia'=>$reference,'revisao'=>$revision,'conteudo'=>$content],$reason);
        return (int)$entity->id;
    }
    /** @deprecated Legacy version compatibility only. Automatic reports/closings never consume these goals. */
    public function savePlan(int $actorId,int $season,int $area,array $values,string $reason,int $base): int
    {
        $actor=Access::actor($actorId);Access::admin($actor);PeriodoContexto::writable($season);
        [, $weeks]=$this->context($season,$area);$valid=array_column($weeks,'referencia');$clean=[];
        if (trim($reason)==='' || mb_strlen($reason)>3000) { throw new \DomainException('Informe a justificativa da programação, até 3.000 caracteres.'); }
        foreach ($values as $date=>$value) {
            if (!in_array($date,$valid,true)) { throw new \DomainException('Semana fora da entressafra.'); }
            if ($value!=='' && $value!==null) { $clean[$date]=Evolucao::number($value); }
        }
        ksort($clean);$previous=0.;foreach ($clean as $value) { if ($value<$previous) { throw new \DomainException('As metas acumuladas não podem diminuir.'); } $previous=$value; }
        return $this->db()->transactional(function()use($actor,$season,$area,$clean,$reason,$base):int {
            $this->lock($season);PeriodoContexto::writable($season);$old=$this->versions($season,$area,'curva_meta')[0]??null;
            if ((int)($old['id']??0)!==$base) { throw new \DomainException('Programação alterada por outra pessoa. Atualize a tela.'); }
            return $this->insert($actor,$season,$area,'curva_meta',['metas'=>$clean,'anterior'=>$base?:null],$reason);
        });
    }
    public function close(int $actorId,int $season,int $area,string $reference,?string $reason=null,?int $base=null,?\DateTimeImmutable $now=null): int
    {
        $actor=Access::actor($actorId);Access::admin($actor);[$seasonRow,$weeks]=$this->context($season,$area);
        $matches=array_values(array_filter($weeks,fn($week)=>$week['referencia']===$reference));
        if (!$matches) { throw new \DomainException('Referência de análise fora das terças-feiras da entressafra.'); }
        $week=$matches[0];$now=($now??new \DateTimeImmutable('now'))->setTimezone(new \DateTimeZone('America/Sao_Paulo'));
        if ($now<self::date($reference)) { throw new \DomainException('A semana ainda não encerrou.'); }
        return $this->db()->transactional(function()use($actor,$season,$area,$reference,$week,$now,$reason,$base):int {
            $this->lock($season);
            $prior=null;
            foreach($this->versions($season,$area,'curva_sem') as $row) { if ($row['referencia_semanal']===$reference) { $prior=$row;break; } }
            if ($prior && $base===null) { return (int)$prior['id']; }
            if ($base!==null && (!$prior || (int)$prior['id']!==$base || trim($reason??'')==='' || mb_strlen($reason??'')>3000)) {
                throw new \DomainException('Correção exige a versão atual e uma justificativa válida.');
            }
            $cutoff=self::date($week['fim'])->setTime(23,59,59)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            // Initial consolidation uses only records already present at Monday's cutoff.
            // An explicit correction may accept later retroactive records for the same effective week.
            $asOf=$prior?$now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'):$cutoff;
            if ($prior) {
                $old=json_decode($prior['conteudo'],true,512,JSON_THROW_ON_ERROR);$roster=$old['ordens'];
            } else {
                [$where,$params]=(new Curva())->scope($season,(string)$area);
                $roster=$this->db()->execute('SELECT a.id,a.os_numero,a.os_filial,a.os_chave,a.created AS os_criada_em,COALESCE(a.area_curva_id,c.area_curva_id) AS area_curva_id FROM atividades a LEFT JOIN cadernos c ON c.id=a.caderno_id WHERE '.$where.' AND a.created<=? ORDER BY a.id',[...$params,$cutoff])->fetchAll('assoc');
            }
            $ids=array_column($roster,'id');$states=[];
            if ($ids) {
                $sql='SELECT * FROM (SELECT r.*,w.referencia,ROW_NUMBER() OVER (PARTITION BY r.atividade_id ORDER BY w.referencia DESC,r.revisao DESC,r.id DESC) AS rn FROM evolucao_registros r JOIN evolucao_semanas w ON w.id=r.semana_id WHERE w.entressafra_id=? AND w.referencia<=? AND r.created<=? AND r.atividade_id IN ('.implode(',',array_fill(0,count($ids),'?')).')) positions WHERE rn=1';
                $states=array_column($this->db()->execute($sql,[$season,$week['fim'],$asOf,...$ids])->fetchAll('assoc'),null,'atividade_id');
            }
            $sum=0.;foreach ($roster as &$order) {
                $state=$states[$order['id']]??null;$order['percentual']=(float)($state['percentual']??0);$order['registro_id']=$state['id']??null;$order['registro_revisao']=$state['revisao']??null;
                $order['registro_referencia']=$state['referencia']??null;$order['registro_em_utc']=$state['created']??null;
                $sum+=$order['percentual'];
            }unset($order);
            $automatic=ProgramacaoAutomatica::metadata($this->automaticCalendar($season,$area),$week['fim']);
            $content=$week+['entressafra_id'=>$season,'area_curva_id'=>$area,'corte_utc'=>$cutoff,'registros_ate_utc'=>$asOf,'apurado_em'=>$now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),'programado'=>$automatic['posicao']['programado'],'programacao_automatica'=>$automatic,'realizado'=>$roster?$sum/count($roster):null,'quantidade'=>count($roster),'ordens'=>$roster,'plano_id'=>null,'anterior'=>$prior['id']??null,'calendario_operacional'=>$this->calendar($season,$area),'dias_operacionais'=>$this->operatingDays($season,$area,$week['fim']),'motivo'=>$reason,'criterio'=>'O.S. únicas criadas até o corte; setor principal na primeira apuração; lista congelada nas correções. Peso igual.'];
            return $this->insert($actor,$season,$area,'curva_sem',$content,$reason??'Fechamento semanal idempotente.',$reference,(int)($prior['revisao_semanal']??0)+1);
        });
    }
    public static function trend(array $points): array
    {
        $past=array_values(array_filter($points,fn($point)=>$point['realizado']!==null));
        if(count($past)<3)return $points;
        $recent=array_slice($past,-4);$rates=[];
        for($i=1;$i<count($recent);$i++){
            $days=(int)self::date($recent[$i-1]['referencia'])->diff(self::date($recent[$i]['referencia']))->format('%a');
            if($days>0)$rates[]=($recent[$i]['realizado']-$recent[$i-1]['realizado'])*7/$days;
        }
        if(count($rates)<2)return $points;
        $average=array_sum($rates)/count($rates);$last=$past[count($past)-1];
        foreach($points as &$point){
            if($point['referencia']<$last['referencia'])continue;
            $days=(int)self::date($last['referencia'])->diff(self::date($point['referencia']))->format('%a');
            $point['tendencia']=(float)max(0.,min(100.,$last['realizado']+$average*$days/7));
        }unset($point);return $points;
    }
    public function automaticCalendar(int $season,int $area): array
    {
        $row=Access::table('Entressafras')->get($season);PeriodoContexto::season($season);
        return ProgramacaoAutomatica::calendar($row->data_inicio->format('Y-m-d'),$row->data_fim->format('Y-m-d'),$this->calendar($season,$area));
    }
    public function report(int $season,int $area,?\DateTimeImmutable $now=null): array
    {
        [$seasonRow,$weeks]=$this->context($season,$area);
        $today=($now??new \DateTimeImmutable('now'))->setTimezone(new \DateTimeZone('America/Sao_Paulo'))->format('Y-m-d');
        $plans=$this->versions($season,$area,'curva_meta'); // Legacy versions remain inspectable, never used for the automatic line.
        $versions=$this->versions($season,$area,'curva_sem');$closed=[];
        foreach($versions as $version)$closed[$version['referencia_semanal']]??=$version;
        $calendar=$this->automaticCalendar($season,$area);$points=[];$latest=null;$due=[];$realized=[];
        foreach($weeks as $week){
            $snapshot=isset($closed[$week['referencia']])&&$week['referencia']<=$today?json_decode($closed[$week['referencia']]['conteudo'],true,512,JSON_THROW_ON_ERROR):null;
            $position=ProgramacaoAutomatica::position($calendar,$week['fim']);
            $point=$week+['programado'=>$position['programado'],'programado_apurado'=>$snapshot['programado']??null,'realizado'=>$snapshot['realizado']??null,'quantidade'=>$snapshot['quantidade']??null,'versao_id'=>$snapshot?$closed[$week['referencia']]['id']:null,'tendencia'=>null,'apurado_em'=>$snapshot['apurado_em']??null,'origem_programado_apurado'=>$snapshot?(isset($snapshot['programacao_automatica'])?'automatico':'legado'):null];
            $point['diferenca']=$point['programado_apurado']!==null&&$point['realizado']!==null?$point['realizado']-$point['programado_apurado']:null;
            $previous=self::date($week['referencia'])->modify('-7 days')->format('Y-m-d');
            $point['evolucao_semanal']=$point['realizado']!==null&&isset($realized[$previous])?$point['realizado']-$realized[$previous]:null;
            if($point['realizado']!==null)$realized[$week['referencia']]=$point['realizado'];
            $points[]=$point;
            if($week['referencia']<=$today){$latest=$point;if($snapshot)$latest['programado']=$point['programado_apurado'];else $due[]=$week;}
        }
        return ['points'=>self::trend($points),'latest'=>$latest,'due'=>$due,'plans'=>$plans,'targets'=>[],'base'=>0,'versions'=>$versions,'days'=>$this->operatingDays($season,$area,$latest['fim']??null),'automatic'=>ProgramacaoAutomatica::metadata($calendar,$latest['fim']??null),'programmed_graph'=>ProgramacaoAutomatica::curve($calendar,$seasonRow->data_inicio->format('Y-m-d'),$seasonRow->data_fim->format('Y-m-d')),'graph_start'=>$seasonRow->data_inicio->format('Y-m-d').'T00:00:00','graph_end'=>$seasonRow->data_fim->format('Y-m-d').'T23:59:59'];
    }
    public function calendar(int $season,int $area): ?array
    {
        $fallback=null;foreach ((new Calendarios())->rows($season) as $calendar) {
            if ((int)$calendar['area_fabrica_id']===$area) { return $calendar; }
            if ($calendar['chave']==='padrao') { $fallback=$calendar; }
        }return $fallback;
    }
    public function saveOperatingDays(int $actorId,int $season,int $area,array $days,array $excluded,string $reason): void
    {
        $actor=Access::actor($actorId);Access::admin($actor);PeriodoContexto::writable($season);$this->context($season,$area);
        $calendar=$this->calendar($season,$area);
        if (!$calendar) { throw new \DomainException('Configure primeiro o calendário operacional em Prazos/Setores.'); }
        $clean=[];foreach($days as $day) { if(!is_scalar($day)||!ctype_digit((string)$day)||(int)$day<1||(int)$day>7)throw new \DomainException('Dia operacional inválido.');$clean[]=(int)$day; }
        $clean=array_values(array_unique($clean));sort($clean);
        if (!$clean || trim($reason)==='' || mb_strlen($reason)>3000 || count($excluded)>1000) { throw new \DomainException('Informe os dias operacionais e a justificativa (até 3.000 caracteres).'); }
        foreach($excluded as $date) { if(!is_string($date))throw new \DomainException('Exceção inválida.');self::date($date); }
        $this->db()->transactional(function()use($actor,$season,$calendar,$clean,$excluded,$reason){
            $this->lock($season);PeriodoContexto::writable($season);$table=Access::table('CalendariosOperacionais');$row=$table->get($calendar['id']);$before=$row->toArray();
            $row->dias_semana=json_encode($clean,JSON_THROW_ON_ERROR);$row->dias_excluidos=json_encode(array_values(array_unique($excluded)),JSON_THROW_ON_ERROR);$table->saveOrFail($row);
            Access::audit($actor,'calendario_dias_operacionais',null,$before,$row->toArray(),$reason);
        });
    }
    public function operatingDays(int $season,int $area,?string $cutoff): array
    {
        $calendar=$this->automaticCalendar($season,$area);$position=ProgramacaoAutomatica::position($calendar,$cutoff);
        return ['total'=>$position['total'],'realizados'=>$position['decorridos'],'restantes'=>$position['restantes'],'warnings'=>$calendar['warnings'],'configurado'=>$calendar['configurado'],'motivo'=>$calendar['motivo'],'regra'=>$calendar['regra']??null];
    }
}
