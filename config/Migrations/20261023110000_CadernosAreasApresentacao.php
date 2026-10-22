<?php
declare(strict_types=1);
use Migrations\BaseMigration;
class CadernosAreasApresentacao extends BaseMigration
{
    public function up(): void
    {
        $this->table('cadernos_areas')->addColumn('caderno_id','integer')->addColumn('area_id','integer')->addColumn('entressafra_id','integer')
            ->addIndex(['caderno_id','area_id'],['unique'=>true])->addIndex(['entressafra_id','area_id'])
            ->addForeignKey(['caderno_id','entressafra_id'],'cadernos',['id','entressafra_id'],['delete'=>'CASCADE'])
            ->addForeignKey('area_id','areas_fabrica','id',['delete'=>'RESTRICT'])->create();
        $this->execute('INSERT INTO cadernos_areas(caderno_id,area_id,entressafra_id) SELECT id,area_curva_id,entressafra_id FROM cadernos WHERE area_curva_id IS NOT NULL');
    }
    public function down(): void { throw new RuntimeException('Preservar as associações administrativas e os históricos.'); }
}
