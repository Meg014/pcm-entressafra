<?php
declare(strict_types=1);
namespace App\Test\TestCase\Service;
use App\Service\EntryPeriods;
use App\Service\TimeEntries;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EntryPeriodsTest extends TestCase
{
    public static function valid(): array
    {
        return [ ['07:00','11:00','','',240], ['','','12:00','14:00',120], ['07:00','11:00','12:00','17:00',540], ['07:00','11:00','11:00','12:00',300], ['19:00','20:00','21:00','23:00',180] ];
    }
    #[DataProvider('valid')]
    public function testTotals(string $a,string $b,string $c,string $d,int $total): void
    {
        $input=TimeEntries::formInput($this->input($a,$b,$c,$d));
        $this->assertSame($total,array_sum(array_column(EntryPeriods::intervals($input),2)));
    }
    public static function invalid(): array
    {
        return [['','','',''],['07:00','','',''],['','11:00','',''],['','','12:00',''],['','','','14:00'],['11:00','07:00','',''],['','','23:00','02:00'],['07:00','13:00','12:00','17:00'],['07:00','11:00','12:00','12:00'],['07:00','11:00','24:00','25:00']];
    }
    #[DataProvider('invalid')]
    public function testInvalidPeriods(string $a,string $b,string $c,string $d): void
    {
        $this->expectException(\DomainException::class);
        TimeEntries::formInput($this->input($a,$b,$c,$d));
    }
    private function input(string $a,string $b,string $c,string $d): array
    {
        return ['data_servico'=>'27/10/2026','hora_inicio_manha'=>$a,'hora_fim_manha'=>$b,'hora_inicio_tarde'=>$c,'hora_fim_tarde'=>$d];
    }
}
