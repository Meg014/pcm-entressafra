<?php
declare(strict_types=1);
namespace App\Service;
final class Calendarios
{
    private array $cache=[];private array $plans=[];
    public static function weeks(string $start,string $end): array
    {
        $a=self::date($start);$b=self::date($end);if($a>$b)throw new \DomainException('O início deve ser anterior ou igual ao encerramento.');
        $dates=[$start];for($d=$a->modify('next monday');$d<$b;$d=$d->modify('+7 days'))$dates[]=$d->format('Y-m-d');$dates[]=$end;return array_values(array_unique($dates));
    }
    private static function date(string $v): \DateTimeImmutable
    { $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v,new \DateTimeZone('America/Sao_Paulo'));if(!$d||$d->format('Y-m-d')!==$v)throw new \DomainException('Data inválida. Use AAAA-MM-DD.');return $d; }
    public function rows(int $season): array { PeriodoContexto::season($season); if(isset($this->cache[$season]))return $this->cache[$season];$rows=Access::table('CalendariosOperacionais')->find()->where(['entressafra_id'=>$season])->orderBy(['id'=>'ASC'])->disableHydration()->all()->toList();foreach($rows as &$r){foreach(['data_inicio','data_fim'] as $key){if(is_object($r[$key]))$r[$key]=$r[$key]->format('Y-m-d');}}unset($r);return $this->cache[$season]=$rows; }
    public function resolve(array $a): ?array
    {
        $rows=$this->rows((int)$a['entressafra_id']);$default=null;$sector=null;
        foreach($rows as $r){if($r['chave']==='padrao')$default=$r;if($r['setor_id']!==null&&(int)$r['setor_id']===(int)$a['setor_executante_id'])$sector=$r;if(!empty($a['area_fabrica_id'])&&(int)$r['area_fabrica_id']===(int)$a['area_fabrica_id'])return $r;}
        return $sector??(!empty($a['area_fabrica_id'])?$default:null);
    }
    public function references(array $row): array
    {return $row['inicio_confirmado']&&$row['data_inicio']?self::weeks((string)$row['data_inicio'],(string)$row['data_fim']):[];}
    public function save(int $user,int $season,array $input): int
    {
        $this->cache=[];$this->plans=[];
        $u=Access::actor($user);Access::pcm($u);$s=Access::table('Entressafras')->get($season);$t=Access::table('CalendariosOperacionais');
        $area=(int)($input['area_fabrica_id']??0);$sector=(int)($input['setor_id']??0);if($area&&$sector)throw new \DomainException('Escolha área da fábrica OU setor executante.');
        if($area)Access::table('AreasFabrica')->get($area);if($sector)Access::table('Setores')->get($sector);
        $key=$area?'area:'.$area:($sector?'setor:'.$sector:'padrao');$start=trim((string)($input['data_inicio']??''));$end=(string)($input['data_fim']??'');self::date($end);
        if($end>$s->data_fim->format('Y-m-d')||$end<$s->data_inicio->format('Y-m-d'))throw new \DomainException('Encerramento fora do período geral da entressafra.');
        $confirmed=($input['inicio_confirmado']??'0')==='1';if($start!==''){self::date($start);if($start<$s->data_inicio->format('Y-m-d')||$start>$end)throw new \DomainException('Início fora do período válido.');}elseif($confirmed)throw new \DomainException('Informe e confira o início antes de gerar semanas.');
        return $t->getConnection()->transactional(function()use($u,$season,$t,$area,$sector,$key,$start,$end,$confirmed){
            if($t->getConnection()->getDriver() instanceof \Cake\Database\Driver\Postgres)$t->getConnection()->execute('SELECT id FROM entressafras WHERE id=? FOR UPDATE',[$season]);
            $old=$t->find()->where(['entressafra_id'=>$season,'chave'=>$key])->first();$before=$old?->toArray();$e=$old??Access::entity($t,[]);
            foreach(['entressafra_id'=>$season,'chave'=>$key,'area_fabrica_id'=>$area?:null,'setor_id'=>$sector?:null,'data_inicio'=>$start?:null,'data_fim'=>$end,'inicio_confirmado'=>$confirmed] as $k=>$v)$e->set($k,$v);$t->saveOrFail($e);
            if($confirmed)(new Evolucao())->configureCalendar((int)$u->id,$season,self::weeks($start,$end));
            Access::audit($u,'calendario_operacional',null,$before,$e->toArray(),'Prazos por área física ou por escopo executante explicitamente configurado.');return (int)$e->id;
        });
    }
    public function classify(int $user,int $season,array $ids,int $area,?int $book=null): int
    {
        $u=Access::actor($user);Access::pcm($u);Access::table('AreasFabrica')->get($area);if(!$ids||count($ids)>200)throw new \DomainException('Selecione de 1 a 200 O.S.');$t=Access::table('Atividades');
        $ids=array_values(array_unique(array_map('intval',$ids)));sort($ids);
        return $t->getConnection()->transactional(function()use($u,$season,$ids,$area,$book,$t){
            $pg=$t->getConnection()->getDriver() instanceof \Cake\Database\Driver\Postgres;
            if($pg)$t->getConnection()->execute('SELECT id FROM entressafras WHERE id=? FOR UPDATE',[$season]);
            foreach($ids as $id){
                if($pg)$t->getConnection()->execute('SELECT id FROM atividades WHERE id=? FOR UPDATE',[$id]);
                $a=$t->get($id);if((int)$a->entressafra_id!==$season)throw new \DomainException('O.S. de outra entressafra.');$before=$a->toArray();
                if($book){if($a->caderno_id && (int)$a->caderno_id!==$book)throw new \DomainException('O.S. já vinculada; não será transferida.');if(!$a->caderno_id){(new Activities(new \App\Integration\Totvs\Gateway()))->classify((int)$u->id,(int)$a->id,$book);$a=$t->get($id);}}
                $a->area_fabrica_id=$area;$t->saveOrFail($a);Access::audit($u,'area_fisica_os',null,$before,$a->toArray(),'Classificação física conferida pelo PCM. Nenhuma alteração de setor executante.');
            }return count($ids);
        });
    }
    public function planned(array $a,string $date,?string $asOf=null): ?float
    {
        $calendar=$this->resolve($a);if(!$calendar||!$this->references($calendar))return null;
        $key=$calendar['id'].'|'.$asOf;if(array_key_exists($key,$this->plans))$v=$this->plans[$key];else{$q=Access::table('EvolucaoVersoes')->find()->where(['entressafra_id'=>$a['entressafra_id'],'tipo'=>'metas','calendario_id'=>$calendar['id'],'aprovado_em IS NOT'=>null]);if($asOf)$q->where(['aprovado_em <='=>$asOf]);$v=$this->plans[$key]=$q->orderBy(['id'=>'DESC'])->first();}if(!$v)return null;
        $targets=json_decode($v->conteudo,true,512,JSON_THROW_ON_ERROR);$out=0.;ksort($targets);foreach($targets as $ref=>$p){if($ref<=$date)$out=(float)$p;}return $out;
    }
}
