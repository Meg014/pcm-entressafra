<?php
declare(strict_types=1);
namespace App\Service;

use Cake\Datasource\ConnectionManager;

/** Presentation grouping is independent of the execution team and physical deadlines. */
final class Curva
{
    private function db() { return ConnectionManager::get('default'); }
    public function scope(int $season,string $area='',string $search=''): array
    {
        PeriodoContexto::season($season);
        $where='a.entressafra_id=?';$params=[$season];
        if($area==='pendentes')$where.=' AND COALESCE(a.area_curva_id,c.area_curva_id) IS NULL';
        elseif($area!=='') {
            if(!ctype_digit($area)||(int)$area<1)throw new \DomainException('Área de apresentação inválida.');
            Access::table('AreasFabrica')->get((int)$area);
            $where.=' AND COALESCE(a.area_curva_id,c.area_curva_id)=CAST(? AS INTEGER)';$params[]=(int)$area;
        }
        if(mb_strlen($search)>60)throw new \DomainException('Pesquisa de até 60 caracteres.');
        if($search!=='') {
            $pattern='%'.strtr(mb_strtolower($search),['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
            $where.=" AND (LOWER(a.os_numero) LIKE ? ESCAPE '!' OR LOWER(a.descricao_atividade) LIKE ? ESCAPE '!' OR LOWER(a.servico) LIKE ? ESCAPE '!' OR LOWER(a.bem) LIKE ? ESCAPE '!')";
            array_push($params,$pattern,$pattern,$pattern,$pattern);
        }
        return [$where,$params];
    }
    private function position(string $where,array $params,string $reference,bool $details=false,int $page=1): array
    {
        $fields=$details?"a.os_numero,a.servico,a.bem,s.nome AS equipe,c.nome AS caderno,a.descricao_atividade,a.descricao_origem,a.snapshot_totvs,ar.nome AS area_curva,":"";
        $sql='WITH latest AS (SELECT r.atividade_id,r.percentual,r.created,w.referencia,ROW_NUMBER() OVER (PARTITION BY r.atividade_id ORDER BY w.referencia DESC,r.revisao DESC,r.id DESC) AS rn FROM evolucao_registros r JOIN evolucao_semanas w ON w.id=r.semana_id WHERE w.referencia<=?) '.
            'SELECT '.$fields.'a.id,a.entressafra_id,a.area_fabrica_id,a.setor_executante_id,COALESCE(a.area_curva_id,c.area_curva_id) AS area_curva_id,COALESCE(l.percentual,0) AS percentual,l.created AS ultima_atualizacao,l.referencia AS ultima_referencia FROM atividades a LEFT JOIN cadernos c ON c.id=a.caderno_id LEFT JOIN setores s ON s.id=a.setor_executante_id LEFT JOIN areas_fabrica ar ON ar.id=COALESCE(a.area_curva_id,c.area_curva_id) LEFT JOIN latest l ON l.atividade_id=a.id AND l.rn=1 WHERE '.$where.' ORDER BY a.os_numero,a.id';
        if($details)$sql.=' LIMIT 10 OFFSET '.((max(1,$page)-1)*10);
        return $this->db()->execute($sql,[$reference,...$params])->fetchAll('assoc');
    }
    private function metrics(array $rows): array
    {
        $values=array_map(fn($r)=>(float)$r['percentual'],$rows);$total=count($values);
        $zero=count(array_filter($values,fn($v)=>$v===0.0));$done=count(array_filter($values,fn($v)=>$v===100.0));
        return ['total'=>$total,'sem_inicio'=>$zero,'andamento'=>$total-$zero-$done,'concluidas'=>$done,'simples'=>$total?round(array_sum($values)/$total,4):null];
    }
    public function report(int $season,string $area='',string $search='',int $page=1,bool $demo=false): array
    {
        [$where,$params]=$this->scope($season,$area,$search);
        $today=(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        $s=Access::table('Entressafras')->get($season);$reference=min($today,$s->data_fim->format('Y-m-d'));
        if($demo){$where='a.entressafra_id=? AND a.area_executante_totvs=?';$params=[$season,'ELETRI'];}
        $rows=$this->position($where,$params,$reference);$metrics=$this->metrics($rows);
        $page=max(1,min($page,max(1,(int)ceil($metrics['total']/10))));
        $details=$this->position($where,$params,$reference,true,$page);
        $weight=null;
        $points=[];$dates=Calendarios::weeks($s->data_inicio->format('Y-m-d'),$s->data_fim->format('Y-m-d'));
        $dates=array_values(array_filter($dates,fn($d)=>$d<=$reference));
        if($reference>=$s->data_inicio->format('Y-m-d'))$dates[]=$reference;
        foreach(array_unique($dates) as $date) {
            $values=$this->position($where,$params,$date);$m=$this->metrics($values);
            $points[]=['referencia'=>$date,'simples'=>$m['simples'],'ponderado'=>null];
        }
        $official=null;$programado=null;
        $hasHistory=(bool)array_filter($rows,fn($r)=>$r['ultima_atualizacao']!==null);
        if($demo) {
            $first=new \DateTimeImmutable($s->data_inicio->format('Y-m-d'));$second=$first->modify('+7 days');
            $points=[['referencia'=>$first->format('Y-m-d'),'simples'=>$rows?30.0:null,'ponderado'=>null],['referencia'=>$second->format('Y-m-d'),'simples'=>$rows?40.0:null,'ponderado'=>null]];
            foreach($details as &$row){$row['percentual']=40;$row['ultima_atualizacao']=null;$row['ultima_referencia']=null;}unset($row);
            $metrics=$this->metrics(array_map(fn($r)=>array_replace($r,['percentual'=>40]),$rows));
            $official=null;$programado=null;$weight=null;$hasHistory=(bool)$rows;
        }
        $pending=(int)$this->db()->execute('SELECT COUNT(*) AS n FROM atividades a LEFT JOIN cadernos c ON c.id=a.caderno_id WHERE a.entressafra_id=? AND COALESCE(a.area_curva_id,c.area_curva_id) IS NULL',[$season])->fetch('assoc')['n'];
        return compact('metrics','details','points','official','programado','weight','reference','page','pending','demo','hasHistory')+['pages'=>max(1,(int)ceil($metrics['total']/10))];
    }
    public function historyPage(int $activity,int $page=1): array
    {
        PeriodoContexto::activity($activity);
        $count=(int)$this->db()->execute('SELECT COUNT(*) AS n FROM evolucao_registros WHERE atividade_id=?',[$activity])->fetch('assoc')['n'];
        $pages=max(1,(int)ceil($count/10));$page=max(1,min($page,$pages));
        $rows=$this->db()->execute('SELECT r.*,w.referencia,u.nome AS responsavel FROM evolucao_registros r JOIN evolucao_semanas w ON w.id=r.semana_id JOIN usuarios u ON u.id=r.usuario_id WHERE r.atividade_id=? ORDER BY w.referencia DESC,r.revisao DESC LIMIT 10 OFFSET '.(($page-1)*10),[$activity])->fetchAll('assoc');
        return compact('rows','page','pages','count');
    }
}
