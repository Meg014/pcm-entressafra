<?php
declare(strict_types=1);
namespace App\Test\TestCase\Integration\Totvs;
use App\Integration\Totvs\Orders;
use App\Integration\Totvs\OrderQueries;
use App\Integration\Totvs\Queries;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
class OrdersTest extends TestCase
{
    private function row(): array
    { return ['numero'=>'000123','filial'=>'01','codigo_servico'=>'ANTIGO','codigo_area'=>'000001','centro_custo'=>'0000000001','data_origem'=>'20260101','situacao'=>'L','termino'=>'N','bem'=>'BEM','servico'=>'MANUTENÇÃO ENTRESSAFRA','correspondencias_servico'=>1]; }
    public static function scenarios(): array
    { return [[[],true],[['situacao'=>'C'],false],[['situacao'=>'P'],false],[['termino'=>'S'],false],[['data_origem'=>'20251231'],false],[['data_origem'=>'20270101'],false],[['servico'=>'MANUTENÇÃO ROTINA'],false],[['correspondencias_servico'=>2],false],[['codigo_servico'=>'NOVO'],true],[['codigo_servico'=>'ANTIGO'],true],[['data_origem'=>'20261231'],true],[['data_origem'=>'20260230'],false]]; }
    #[DataProvider('scenarios')]
    public function testDefinitivePolicy(array $changes,bool $available): void
    { $this->assertSame($available,Orders::available(array_replace($this->row(),$changes))); }
    public function testSearchByNumberAndAreaIsBoundAndIncludesAllServiceCodes(): void
    {
        $lookup=new Orders(function($sql,$params) {
            $this->assertSame(OrderQueries::search(),$sql);
            $this->assertSame('20260101',$params['inicio']); $this->assertSame('20261231',$params['fim']);
            $this->assertSame('%ENTRESSAFRA%',$params['entressafra']); $this->assertSame('%001%',$params['numero']);
            $this->assertSame('000001',$params['area']); $this->assertStringNotContainsString("TJ_SERVICO IN",$sql);
            return [$this->row()];
        });
        $result=$lookup->search('001','000001');
        $this->assertSame('000123',$result[0]['numero']); $this->assertTrue($result[0]['disponivel']);
    }
    public function testHistoryDetailIncludesFinishedOrdersWithoutOpenPredicate(): void
    {
        $lookup=new Orders(function($sql,$params) {
            $this->assertSame(OrderQueries::detail(),$sql); $this->assertSame(['numero'=>'000123','filial'=>'01'],$params);
            $this->assertStringNotContainsString("TJ_TERMINO = 'N'",$sql);
            $this->assertStringNotContainsString(':inicio',$sql);
            return [array_replace($this->row(),['termino'=>'S','situacao'=>'C'])];
        });
        $row=$lookup->find(Orders::key('01','000123'));
        $this->assertFalse($row['disponivel']); $this->assertSame('S',$row['termino']);
    }
    public function testAmbiguousIdentityCannotBeLinked(): void
    {
        $this->expectException(\DomainException::class);
        (new Orders(fn()=>[$this->row(),$this->row()]))->find(Orders::key('01','000123'));
    }
    public function testSqlAllowlistAndInjection(): void
    {
        foreach([OrderQueries::search(),OrderQueries::detail(),OrderQueries::profile()] as $sql) {
            $this->assertTrue(Queries::allows($sql)); $this->assertFalse(Queries::allows($sql.'; DELETE FROM dbo.STJ010'));
            $this->assertStringContainsString('DADOSP12.dbo.STJ010',$sql);
        }
        $term="' OR 1=1 --";
        $lookup=new Orders(function($sql,$params) use($term) { $this->assertStringNotContainsString($term,$sql); $this->assertSame('%'.$term.'%',$params['numero']); return []; });
        $this->assertSame([],$lookup->search($term));
    }
}
