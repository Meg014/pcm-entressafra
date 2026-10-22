<?php
declare(strict_types=1);
namespace App\Test\TestCase\Service;
use App\Service\TimeEntries;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
class TimeEntryInputTest extends TestCase
{
    public function testMasksAreNotRequiredToValidateAndConvertOnServer(): void
    {
        foreach([['22102026','0730','1130'],['22/10/2026','07:30','11:30']] as [$date,$start,$end]){
            $in=TimeEntries::formInput(['data_servico'=>$date,'hora_inicial'=>$start,'hora_final'=>$end,'inicio'=>'forjado','fim'=>'forjado']);
            $this->assertSame('2026-10-22T07:30',$in['inicio']);$this->assertSame('2026-10-22T11:30',$in['fim']);
            $this->assertSame(['2026-10-22 10:30:00','2026-10-22 14:30:00',240],TimeEntries::interval($in['inicio'],$in['fim']));
        }
    }
    public function testExplicitMidnightAcrossMonthAndLeapYear(): void
    {
        foreach(['31/12/2026'=>'2027-01-01T02:00','28/02/2028'=>'2028-02-29T02:00','29/02/2028'=>'2028-03-01T02:00'] as $date=>$expected){
            $in=TimeEntries::formInput(['data_servico'=>$date,'hora_inicial'=>'23:00','hora_final'=>'02:00','termina_dia_seguinte'=>'1']);
            $this->assertSame($expected,$in['fim']);$this->assertSame(180,TimeEntries::interval($in['inicio'],$in['fim'])[2]);
        }
    }
    public static function invalid(): array
    {
        return [['31/02/2026','07:00','11:00','0'],['29/02/2026','07:00','11:00','0'],['00/10/2026','07:00','11:00','0'],['22/13/2026','07:00','11:00','0'],['22/10/0000','07:00','11:00','0'],['22/10/2026','24:00','11:00','0'],['22/10/2026','07:60','11:00','0'],['22/10/2026','07:00','99:30','0'],['22/10/2026','23:00','02:00','0'],['22/10/2026','07:00','07:00','0'],['22/10/2026','07:00','11:00','2'],['22/10/2026','7:30','11:00','0'],['','07:00','11:00','0']];
    }
    #[DataProvider('invalid')]
    public function testInvalidDatesHoursAndImplicitMidnightAreRejected(string $date,string $start,string $end,string $next): void
    {
        $this->expectException(\DomainException::class);TimeEntries::formInput(['data_servico'=>$date,'hora_inicial'=>$start,'hora_final'=>$end,'termina_dia_seguinte'=>$next]);
    }
}
