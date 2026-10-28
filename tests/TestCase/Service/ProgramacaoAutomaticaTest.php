<?php
declare(strict_types=1);
namespace App\Test\TestCase\Service;

use App\Service\{CurvaSemanal,ProgramacaoAutomatica};
use PHPUnit\Framework\TestCase;

class ProgramacaoAutomaticaTest extends TestCase
{
    private function calendar(string $start,string $end,array $days=[1,2,3,4,5,6,7],array $excluded=[]): array
    {
        return ProgramacaoAutomatica::calendar($start,$end,['id'=>1,'data_inicio'=>$start,'data_fim'=>$end,'inicio_confirmado'=>true,'dias_semana'=>json_encode($days),'dias_excluidos'=>json_encode($excluded)]);
    }
    public function testHistoricalKnotsBoundsMonotonicityAndInternalPrecision(): void
    {
        foreach(ProgramacaoAutomatica::MODEL as $i=>$value)$this->assertEqualsWithDelta($value,ProgramacaoAutomatica::interpolate($i/17),1e-10);
        $previous=0.;
        for($i=0;$i<=1000;$i++){$value=ProgramacaoAutomatica::interpolate($i/1000);$this->assertGreaterThanOrEqual($previous,$value);$this->assertLessThanOrEqual(100,$value);$previous=$value;}
        $this->assertSame(0.,ProgramacaoAutomatica::interpolate(-1));$this->assertSame(100.,ProgramacaoAutomatica::interpolate(2));
        $value=ProgramacaoAutomatica::interpolate(0.123456789);$this->assertNotEquals(round($value,4),$value);
        $this->expectException(\DomainException::class);ProgramacaoAutomatica::interpolate(NAN);
    }
    public function testShortLongAndPartialWeeksUseCalendarProgressRatherThanCopyingSeventeenValues(): void
    {
        foreach([['2026-10-20','2026-10-26'],['2026-07-01','2027-02-02'],['2026-10-14','2026-11-02']] as [$start,$end]){
            $calendar=$this->calendar($start,$end);$points=[];
            foreach(CurvaSemanal::weeks($start,$end) as $week)$points[]=ProgramacaoAutomatica::position($calendar,$week['fim'])['programado'];
            $this->assertSame(100.,$points[count($points)-1]);
            $sorted=$points;sort($sorted);$this->assertSame($points,$sorted);
            $curve=ProgramacaoAutomatica::curve($calendar,$start,$end);$this->assertSame(0.,$curve[0]['programado']);$this->assertSame(100.,$curve[count($curve)-1]['programado']);
            $this->assertSame($end.'T23:59:59',$curve[count($curve)-1]['data']);
        }
        $partial=$this->calendar('2026-10-14','2026-11-02');
        $this->assertEqualsWithDelta(ProgramacaoAutomatica::interpolate(6/20),ProgramacaoAutomatica::position($partial,'2026-10-19')['programado'],1e-12);
    }
    public function testDaysExceptionsAndUnrestrictedCalendarHaveConsistentIndicators(): void
    {
        $calendar=$this->calendar('2026-10-19','2026-11-01',[1,2,3,4,5],['2026-10-23']);
        $this->assertSame(9,$calendar['total']);$position=ProgramacaoAutomatica::position($calendar,'2026-10-26');
        $this->assertSame(5,$position['decorridos']);$this->assertSame(4,$position['restantes']);
        $this->assertSame(100.,ProgramacaoAutomatica::position($calendar,'2026-11-01')['programado']);
        $this->assertSame(0.,ProgramacaoAutomatica::position($calendar,'2026-10-18')['programado']);
        $curve=array_column(ProgramacaoAutomatica::curve($calendar,'2026-10-19','2026-11-01'),'programado','referencia');
        $this->assertSame($curve['2026-10-22'],$curve['2026-10-23']);$this->assertSame($curve['2026-10-23'],$curve['2026-10-24']);$this->assertSame($curve['2026-10-24'],$curve['2026-10-25']);
        $continuous=ProgramacaoAutomatica::calendar('2026-10-19','2026-11-01',['data_inicio'=>'2026-10-19','data_fim'=>'2026-11-01','inicio_confirmado'=>true,'dias_semana'=>null]);
        $this->assertSame(14,$continuous['total']);$this->assertStringContainsString('contínuo',$continuous['regra']);
    }
    public function testSectorEndsBeforeSeasonAndInputsAreNotModified(): void
    {
        $stored=['data_inicio'=>'2026-10-20','data_fim'=>'2026-10-26','inicio_confirmado'=>true,'dias_semana'=>'[1,2,3,4,5]','dias_excluidos'=>'[]'];$before=$stored;
        $calendar=ProgramacaoAutomatica::calendar('2026-10-14','2026-11-02',$stored);
        $this->assertSame(5,$calendar['total']);$this->assertSame($before,$stored);$this->assertCount(1,$calendar['warnings']);
        $this->assertSame(100.,ProgramacaoAutomatica::position($calendar,'2026-10-26')['programado']);
        $this->assertSame(100.,ProgramacaoAutomatica::position($calendar,'2026-11-02')['programado']);
    }
    public function testMissingCalendarAndNoOperationalDaysAreNotInvented(): void
    {
        $missing=ProgramacaoAutomatica::calendar('2026-10-20','2026-10-26',null);
        $this->assertFalse($missing['configurado']);$this->assertNull(ProgramacaoAutomatica::position($missing,'2026-10-26')['programado']);
        $empty=$this->calendar('2026-10-24','2026-10-25',[1,2,3,4,5]);$this->assertFalse($empty['configurado']);$this->assertSame([],ProgramacaoAutomatica::curve($empty,'2026-10-24','2026-10-25'));
    }
    public function testMovingAverageUsesRecentThreeVariationsAndNotOnlyLastSlope(): void
    {
        $points=[];foreach([['2026-09-29',10],['2026-10-06',20],['2026-10-13',40],['2026-10-20',70],['2026-10-27',null],['2026-11-03',null]] as [$reference,$value])$points[]=['referencia'=>$reference,'realizado'=>$value,'tendencia'=>null];
        $trend=CurvaSemanal::trend($points);$this->assertSame(90.,$trend[4]['tendencia']);$this->assertSame(100.,$trend[5]['tendencia']);$this->assertNull($trend[4]['realizado']);
        $this->assertSame(array_slice($points,0,2),CurvaSemanal::trend(array_slice($points,0,2)));
        $falling=$points;foreach([60,50,30,10] as $i=>$value)$falling[$i]['realizado']=$value;
        $this->assertSame(0.,CurvaSemanal::trend($falling)[4]['tendencia']);
    }
}
