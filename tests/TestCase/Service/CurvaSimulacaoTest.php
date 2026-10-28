<?php
declare(strict_types=1);
namespace App\Test\TestCase\Service;

use App\Service\{CurvaSemanal,CurvaSimulacao,ProgramacaoAutomatica};
use Cake\TestSuite\TestCase;

class CurvaSimulacaoTest extends TestCase
{
    private function report(string $end): array
    {
        $start='2026-10-19';
        $calendar=ProgramacaoAutomatica::calendar($start,$end,['data_inicio'=>$start,'data_fim'=>$end,'inicio_confirmado'=>true]);
        $points=[];
        foreach(CurvaSemanal::weeks($start,$end) as $week){
            $points[]=$week+['programado'=>ProgramacaoAutomatica::position($calendar,$week['fim'])['programado'],'realizado'=>null,'tendencia'=>null,'versao_id'=>null];
        }
        return ['points'=>$points,'latest'=>null,'days'=>['configurado'=>true,'total'=>$calendar['total']],'display_days'=>ProgramacaoAutomatica::position($calendar,'2026-10-27'),'programmed_graph'=>ProgramacaoAutomatica::curve($calendar,$start,$end)];
    }
    public function testFullProgressPreservesCalendarAndSourceAndReplaysExistingTrend(): void
    {
        foreach(['2026-12-16','2027-04-10','2027-09-03'] as $end){
            $original=$this->report($end);$copy=$original;$sim=CurvaSimulacao::report($original);
            $this->assertSame($copy,$original);
            foreach(['days','display_days','programmed_graph'] as $key)$this->assertSame($original[$key],$sim[$key]);
            $this->assertSame(count($original['points']),count($sim['points']));
            $this->assertLessThanOrEqual(2.,$sim['points'][0]['realizado']);
            $this->assertEqualsWithDelta(100.,$sim['latest']['realizado'],1e-10);
            $above=false;$below=false;$previous=0.;
            foreach($sim['points'] as $i=>$p){
                foreach(['semana','inicio','fim','referencia','programado'] as $key)$this->assertSame($original['points'][$i][$key],$p[$key]);
                $this->assertGreaterThanOrEqual($previous,$p['realizado']);$this->assertLessThanOrEqual(100.,$p['realizado']);
                $above=$above||$p['diferenca']>0.0001;$below=$below||$p['diferenca']< -0.0001;$previous=$p['realizado'];
                if($i<3){$this->assertNull($p['tendencia']);continue;}
                $history=array_slice($sim['points'],0,$i);$future=$p;$future['realizado']=null;$future['tendencia']=null;$history[]=$future;
                $expected=CurvaSemanal::trend($history);
                $this->assertSame($expected[$i]['tendencia'],$p['tendencia']);
            }
            $this->assertTrue($above);$this->assertTrue($below);
        }
    }
    public function testInsufficientWeeksNeverInventTrendAndSingleWeekWorks(): void
    {
        foreach(['2026-10-19','2026-11-02'] as $end){
            $sim=CurvaSimulacao::report($this->report($end));
            $this->assertLessThanOrEqual(3,count($sim['points']));
            $this->assertSame([],array_filter(array_column($sim['points'],'tendencia'),static fn($v)=>$v!==null));
        }
    }
    public function testMissingCalendarCannotInventProgrammed(): void
    {
        $report=$this->report('2027-04-10');$report['days']['configurado']=false;
        $this->expectException(\DomainException::class);CurvaSimulacao::report($report);
    }
    public function testElectricalSectorIsResolvedFromSeasonWithoutHardcodedId(): void
    {
        $this->assertSame(42,CurvaSimulacao::electricalArea([5=>'MOENDA',42=>' ELÉTRICA ']));
        $this->assertSame(8,CurvaSimulacao::electricalArea([8=>'eletrica']));
        $this->assertNull(CurvaSimulacao::electricalArea([5=>'MOENDA']));
    }
}
