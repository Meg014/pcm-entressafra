<?php
declare(strict_types=1);
use Migrations\BaseMigration;
class MapSetoresTotvs extends BaseMigration
{
    public function change(): void
    {
        $this->table('setores')->addColumn('area_totvs','string',['limit'=>6,'null'=>true])->addColumn('filial_totvs','string',['limit'=>2,'null'=>true])
            ->addIndex(['filial_totvs','area_totvs'],['unique'=>true,'name'=>'uq_setores_filial_area_totvs'])->update();
    }
}
