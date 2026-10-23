<?php
declare(strict_types=1);
use Migrations\BaseMigration;
class CadernosSetorPrincipal extends BaseMigration
{
    public function up(): void
    {
        $this->table('cadernos')->changeColumn('setor_id','integer',['null'=>true])->update();
        $this->table('areas_fabrica')->addColumn('entressafra_id','integer',['null'=>true])
            ->addForeignKey('entressafra_id','entressafras','id',['delete'=>'RESTRICT'])->update();
        // Preserve existing identifiers and names; attach only unambiguous scope.
        $this->execute('UPDATE areas_fabrica SET entressafra_id=(SELECT MIN(id) FROM entressafras) WHERE (SELECT COUNT(*) FROM entressafras)=1');
        $this->execute('UPDATE areas_fabrica SET entressafra_id=(SELECT MIN(c.entressafra_id) FROM calendarios_operacionais c WHERE c.area_fabrica_id=areas_fabrica.id) WHERE entressafra_id IS NULL AND (SELECT COUNT(DISTINCT c.entressafra_id) FROM calendarios_operacionais c WHERE c.area_fabrica_id=areas_fabrica.id)=1');
    }
    public function down(): void { throw new RuntimeException('Preservar cadernos sem equipe legada e setores existentes.'); }
}
