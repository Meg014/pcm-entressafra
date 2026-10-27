<?php
declare(strict_types=1);
namespace App\Test\TestCase\Integration\Totvs;

use App\Integration\Totvs\{ListOrders,ListOrderQueries,Orders,Queries};
use PHPUnit\Framework\TestCase;

class ListOrdersTest extends TestCase
{
    public function testBatchSharesAutocompleteDescriptionMappingAndBindsBranchAndNumbers(): void
    {
        $source=new ListOrders(function(string $sql,array $params): array {
            $this->assertSame(ListOrderQueries::numbers(),$sql);
            $this->assertTrue(Queries::allows($sql));
            $this->assertFalse(Queries::allows($sql.'; DELETE FROM STJ010'));
            $this->assertStringContainsString('TJ_OBSERVA',$sql);
            $this->assertStringNotContainsString("TJ_TERMINO = 'N'",$sql);
            $this->assertSame('02',$params['filial']);$this->assertSame('005316',$params['n0']);
            $this->assertSame('005525',$params['n1']);$this->assertSame('',$params['n99']);
            return [['numero'=>'005316 ','filial'=>'02','codigo_servico'=>'1','codigo_area'=>'A0','centro_custo'=>'CC','data_origem'=>'20260101','situacao'=>'C','termino'=>'S','bem'=>'ILM 20 001','servico'=>'ENTRESSAFRA','correspondencias_servico'=>1,'identidades'=>1,'descricao_totvs'=>' MANUTENÇÃO CORRETIVA ILUMINAÇÃO ']];
        });
        $rows=$source->find(['005316','005525'],'02');
        $this->assertSame('005316',$rows['005316']['numero']);
        $this->assertSame(Orders::key('02','005316'),$rows['005316']['chave']);
        $this->assertSame('MANUTENÇÃO CORRETIVA ILUMINAÇÃO',$rows['005316']['descricao_totvs']);
    }
    public function testInvalidBranchDoesNotReachReader(): void
    {
        $source=new ListOrders(function(){ $this->fail('Consulta inválida executada'); });
        $this->expectException(\DomainException::class);$source->find(['005316'],"01' OR 1=1");
    }
}
