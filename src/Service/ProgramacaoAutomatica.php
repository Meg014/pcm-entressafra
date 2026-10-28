<?php
declare(strict_types=1);
namespace App\Service;

/** Historical S-shape normalized by operational calendar progress, independent of work orders. */
final class ProgramacaoAutomatica
{
    public const MODEL = [0,1,2,3,4,5,10,20,31,43,56,69,79,84,90,95,97,100];
    public const VERSION = 'historico-17-linear-v1';

    public static function interpolate(float $progress): float
    {
        if (!is_finite($progress)) { throw new \DomainException('Progresso do calendário inválido.'); }
        if ($progress<=0) { return 0.; }
        if ($progress>=1) { return 100.; }
        $position=$progress*(count(self::MODEL)-1);$left=(int)floor($position);$fraction=$position-$left;
        return self::MODEL[$left]+(self::MODEL[$left+1]-self::MODEL[$left])*$fraction;
    }
    private static function date(string $value): \DateTimeImmutable
    {
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value,new \DateTimeZone('America/Sao_Paulo'));
        if (!$date || $date->format('Y-m-d')!==$value) { throw new \DomainException('Data do calendário inválida.'); }
        return $date;
    }
    public static function calendar(string $seasonStart,string $seasonEnd,?array $calendar): array
    {
        $base=['configurado'=>false,'motivo'=>null,'datas'=>[],'inicio'=>null,'fim'=>null,'total'=>null,'warnings'=>[],'calendario_id'=>$calendar['id']??null];
        if (!$calendar || empty($calendar['inicio_confirmado']) || empty($calendar['data_inicio'])) {
            $base['motivo']='Confirme o início e o término no calendário operacional existente.';return $base;
        }
        $start=(string)$calendar['data_inicio'];$end=(string)$calendar['data_fim'];
        self::date($seasonStart);self::date($seasonEnd);self::date($start);self::date($end);
        if ($start!==$seasonStart || $end!==$seasonEnd) {
            $base['warnings'][]='Calendário operacional: '.$start.' a '.$end.'; entressafra: '.$seasonStart.' a '.$seasonEnd.'. Contagem na interseção dos períodos, sem alterar cadastros.';
        }
        $start=max($start,$seasonStart);$end=min($end,$seasonEnd);
        if ($start>$end) { $base['motivo']='O calendário operacional não possui dias dentro da entressafra.';return $base; }
        $restricted=!empty($calendar['dias_semana']);
        $weekdays=$restricted?json_decode($calendar['dias_semana'],true,512,JSON_THROW_ON_ERROR):range(1,7);
        $excluded=json_decode($calendar['dias_excluidos']??'[]',true,512,JSON_THROW_ON_ERROR);
        if (!is_array($weekdays)||!$weekdays||!is_array($excluded)) { throw new \DomainException('Regras do calendário operacional inválidas.'); }
        $weekdays=array_map('intval',$weekdays);$dates=[];
        foreach($weekdays as $day)if($day<1||$day>7)throw new \DomainException('Dia da semana inválido no calendário.');
        foreach($excluded as $date){if(!is_string($date))throw new \DomainException('Exceção inválida no calendário.');self::date($date);}
        for ($date=self::date($start);$date<=self::date($end);$date=$date->modify('+1 day')) {
            $value=$date->format('Y-m-d');
            if(in_array((int)$date->format('N'),$weekdays,true)&&!in_array($value,$excluded,true))$dates[]=$value;
        }
        if(!$dates){$base['motivo']='Não há dias operacionais no período configurado.';return $base;}
        return array_replace($base,['configurado'=>true,'datas'=>$dates,'inicio'=>$start,'fim'=>$end,'total'=>count($dates),'dias_semana'=>$weekdays,'dias_excluidos'=>$excluded,'regra'=>$restricted?'Dias da semana e exceções cadastrados':'Calendário contínuo: sem restrição de dias da semana cadastrada']);
    }
    public static function position(array $calendar,?string $through): array
    {
        if(!$calendar['configurado'])return ['programado'=>null,'progresso'=>null,'total'=>null,'decorridos'=>null,'restantes'=>null];
        if($through!==null)self::date($through);
        $elapsed=$through===null?0:count(array_filter($calendar['datas'],fn($date)=>$date<=$through));
        $progress=$elapsed/$calendar['total'];
        return ['programado'=>self::interpolate($progress),'progresso'=>$progress,'total'=>$calendar['total'],'decorridos'=>$elapsed,'restantes'=>$calendar['total']-$elapsed];
    }
    public static function metadata(array $calendar,?string $through): array
    {
        $metadata=$calendar;unset($metadata['datas']);
        return ['modelo'=>self::VERSION,'pontos'=>self::MODEL,'interpolacao'=>'Linear monotônica entre x=i/17, y=pontos[i]','calendario'=>$metadata,'posicao'=>self::position($calendar,$through)];
    }
    public static function curve(array $calendar,string $seasonStart,string $seasonEnd): array
    {
        if(!$calendar['configurado'])return [];
        $points=[];
        $add=static function(string $stamp,float $value)use(&$points):void {$points[$stamp]=['data'=>$stamp,'referencia'=>substr($stamp,0,10),'programado'=>$value];};
        $add($seasonStart.'T00:00:00',0.);$elapsed=0;
        for($date=self::date($seasonStart);$date<=self::date($seasonEnd);$date=$date->modify('+1 day')) {
            $day=$date->format('Y-m-d');
            while(isset($calendar['datas'][$elapsed])&&$calendar['datas'][$elapsed]<=$day)$elapsed++;
            $add($day.'T23:59:59',self::interpolate($elapsed/$calendar['total']));
        }
        return array_values($points);
    }
}
