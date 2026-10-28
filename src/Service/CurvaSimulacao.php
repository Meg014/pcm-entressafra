<?php
declare(strict_types=1);

namespace App\Service;

/** Presentation-only transformation. No connection, session, clock or persistence. */
final class CurvaSimulacao
{
    public static function electricalArea(array $areas): ?int
    {
        foreach ($areas as $id=>$name) {
            if (strtr(mb_strtoupper(trim((string)$name)),['É'=>'E','Ê'=>'E'])==='ELETRICA') {
                return (int)$id;
            }
        }
        return null;
    }

    public static function report(array $report): array
    {
        if (!$report['days']['configurado'] || !$report['days']['total']) {
            throw new \DomainException('A simulação precisa do calendário operacional confirmado da ELÉTRICA.');
        }
        $points=$report['points'];$previous=null;
        foreach ($points as $i=>&$point) {
            $planned=(float)$point['programado'];
            // Monotone deformation of the current planned curve, alternating above/below it.
            $value=$planned+6*sin(4*M_PI*$planned/100)*sin(M_PI*$planned/100);
            if ($i===0 && count($points)>1) {$value=min(2.,$value);}
            $value=max($previous??0.,min(100.,$value));
            $point['realizado']=$value;
            $point['tendencia']=null;
            $point['diferenca']=$value-$planned;
            $point['evolucao_semanal']=$previous===null?null:$value-$previous;
            $point['versao_id']=null;
            $point['programado_apurado']=null;
            $point['origem_programado_apurado']=null;
            $point['quantidade']=null;
            $point['apurado_em']=null;
            $point['revisao_pendente']=false;
            $previous=$value;
        }
        unset($point);
        // Replay one-week-ahead forecasts using the unchanged production moving average.
        for ($i=3;$i<count($points);$i++) {
            $history=array_slice($points,0,$i);
            $future=$points[$i];$future['realizado']=null;$future['tendencia']=null;
            $history[]=$future;
            $forecast=CurvaSemanal::trend($history);
            $points[$i]['tendencia']=$forecast[$i]['tendencia'];
        }
        $report['points']=$points;
        $report['latest']=$points?end($points):null;
        $report['simulation']=true;
        return $report;
    }
}
