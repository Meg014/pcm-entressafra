<?php
declare(strict_types=1);
use Migrations\BaseMigration;

class ClassificacaoPrincipalCurva extends BaseMigration
{
    public function up(): void
    {
        foreach (['cadernos','atividades'] as $name) {
            $this->table($name)->addColumn('area_curva_id','integer',['null'=>true])
                ->addIndex(['area_curva_id'])
                ->addForeignKey('area_curva_id','areas_fabrica','id',['delete'=>'RESTRICT'])->update();
        }
        // Deliberately no inferred classification or update of existing links/history.
    }
    public function down(): void
    {
        throw new RuntimeException('Preservar classificações principais e históricos.');
    }
}
