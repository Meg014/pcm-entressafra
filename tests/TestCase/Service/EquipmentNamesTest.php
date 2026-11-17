<?php
declare(strict_types=1);
namespace App\Test\TestCase\Service;

use App\Integration\Totvs\{Equipment, Queries};
use App\Service\EquipmentNames;
use PHPUnit\Framework\TestCase;

final class EquipmentNamesTest extends TestCase
{
    public function testBranchBatchSnapshotAndFallback(): void
    {
        $calls=[];
        $source=new Equipment(function($sql,$params)use(&$calls){
            $this->assertTrue(Queries::allows($sql));
            $calls[]=$params;
            return [
                ['codigo'=>'BCE 40 064','filial'=>$params['filial'],'nome'=>'Bomba '.$params['filial']],
                ['codigo'=>'EXA 50 001','filial'=>$params['filial'],'nome'=>' '],
                ['codigo'=>'EXA 50 001','filial'=>'99','nome'=>'Outra filial'],
            ];
        });
        $items=[
            ['bem'=>'BCE 40 064','filial'=>'01'],
            ['bem'=>'BCE 40 064','filial'=>'02'],
            ['bem'=>'EXA 50 001','filial'=>'01'],
            ['bem'=>'TOR 40 017','filial'=>'01','snapshot'=>['bem'=>'TOR 40 017','filial'=>'01','bem_nome'=>'Torno']],
            ['bem'=>'BCE 40 064','filial'=>'01'],
        ];
        $this->assertSame(['Bomba 01','Bomba 02','EXA 50 001','Torno','Bomba 01'],(new EquipmentNames($source))->resolve($items));
        $this->assertCount(2,$calls);
        $this->assertNotContains('TOR 40 017',array_values($calls[0]));
        $this->assertSame('BCE 40 064',$items[0]['bem']);
    }

    public function testChunkingAmbiguityAndUnavailableSource(): void
    {
        $calls=0;
        $source=new Equipment(function()use(&$calls){$calls++;return [];});
        $items=[];
        for($i=0;$i<251;$i++)$items[]=['bem'=>'BEM '.$i,'filial'=>'01'];
        $names=(new EquipmentNames($source))->resolve($items);
        $this->assertCount(251,$names);
        $this->assertSame(3,$calls);
        $source=new Equipment(static function(){throw new \RuntimeException('offline');});
        $this->assertSame(['BCE 40 064'],(new EquipmentNames($source))->resolve([['bem'=>'BCE 40 064','filial'=>'01']]));
        $source=new Equipment(static fn()=>[
            ['codigo'=>'B','filial'=>'01','nome'=>'Um'],['codigo'=>'B','filial'=>'01','nome'=>'Dois'],
        ]);
        $this->assertSame(['B'],(new EquipmentNames($source))->resolve([['bem'=>'B','filial'=>'01']]));
    }
}
