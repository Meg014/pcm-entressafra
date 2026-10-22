<?php
declare(strict_types=1);
namespace App\Test\TestCase\Integration\Totvs;
use App\Integration\Totvs\BulkOrderQueries;
use App\Integration\Totvs\BulkOrders;
use App\Integration\Totvs\Queries;
use PHPUnit\Framework\TestCase;

class BulkOrdersTest extends TestCase
{
    public function testReadOnlyAllowlistAndSourcePolicies(): void
    {
        foreach ([BulkOrderQueries::services(),BulkOrderQueries::page()] as $sql) {
            $this->assertTrue(Queries::allows($sql)); $this->assertStringStartsWith('SELECT',$sql);
            foreach (['STJ010','ST4010',"TJ_SITUACA = 'L'","TJ_TERMINO = 'N'",'j.TJ_CODAREA=:area','j.TJ_FILIAL=:filial'] as $part) { $this->assertStringContainsString($part,$sql); }
            foreach (['2425ME','2425EL','STJ020','ST4020','SELECT *'] as $part) { $this->assertStringNotContainsString($part,$sql); }
        }
        $this->assertFalse(Queries::allows(BulkOrderQueries::page().'; DELETE FROM dbo.STJ010'));
    }
    public function testMultipleOldAndCurrentServicesAndFiltersAreParameters(): void
    {
        $source=new BulkOrders(function($sql,$params) {
            $this->assertSame(BulkOrderQueries::page(),$sql);
            $this->assertSame('2425ME',$params['s0']); $this->assertSame('ESMECA',$params['s1']); $this->assertSame('',$params['s19']);
            $this->assertSame('20260101',$params['inicio']); $this->assertSame('20261231',$params['fim']);
            $this->assertSame('MECANI',$params['area']); $this->assertSame('01',$params['filial']);
            $this->assertSame('%10[%][_]%', $params['cc_busca']);
            $this->assertStringNotContainsString("' OR 1=1",$sql);
            $this->assertSame("%' OR 1=1 --%",$params['numero']);
            $this->assertSame('005523',$params['cursor']);
            return [];
        });
        $page=$source->page(['area'=>'MECANI','filial'=>'01','servicos'=>['2425ME','ESMECA'],'cc'=>'10%_','bem'=>'BEM','busca'=>"' OR 1=1 --"],'005523');
        $this->assertSame([],$page['rows']); $this->assertFalse($page['has_more']);
    }
    public function testUnavailableConnectionHasSanitizedError(): void
    {
        $source=new BulkOrders(fn()=>throw new \RuntimeException('secret connection details'));
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Não foi possível consultar');
        $source->services('MECANI','01');
    }
}
