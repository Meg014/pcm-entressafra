<?php
declare(strict_types=1);
use Migrations\BaseMigration;

class CurvaSemanalTercas extends BaseMigration
{
    public function up(): void
    {
        $this->table('evolucao_versoes')
            ->addColumn('area_curva_id','integer',['null'=>true])
            ->addColumn('referencia_semanal','date',['null'=>true])
            ->addColumn('revisao_semanal','integer',['null'=>true])
            ->addForeignKey('area_curva_id','areas_fabrica','id',['delete'=>'RESTRICT'])
            ->addIndex(['entressafra_id','area_curva_id','tipo','referencia_semanal','revisao_semanal'],['unique'=>true,'name'=>'uq_curva_semana_revisao'])
            ->update();
        $this->table('calendarios_operacionais')
            ->addColumn('dias_semana','text',['null'=>true])
            ->addColumn('dias_excluidos','text',['null'=>true])->update();
        if ($this->getAdapter()->getAdapterType()!=='pgsql') { return; }
        $this->execute('ALTER TABLE evolucao_versoes DROP CONSTRAINT ck_evolucao_versoes');
        $this->execute("ALTER TABLE evolucao_versoes ADD CONSTRAINT ck_evolucao_versoes CHECK (tipo IN ('pesos','metas','curva_meta','curva_sem'))");
        $this->execute("ALTER TABLE evolucao_versoes ADD CONSTRAINT ck_curva_semanal CHECK ((tipo NOT IN ('curva_meta','curva_sem')) OR (area_curva_id IS NOT NULL AND aprovado_por IS NOT NULL AND aprovado_em IS NOT NULL AND (tipo='curva_meta' OR (referencia_semanal IS NOT NULL AND revisao_semanal IS NOT NULL AND revisao_semanal>0 AND EXTRACT(ISODOW FROM referencia_semanal)=2))))");
        // Existing immutable triggers also protect these new version types.
    }
    public function down(): void { throw new RuntimeException('Preservar metas e fechamentos semanais; rollback destrutivo bloqueado.'); }
}
