<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateMecanicaCurvaVersoes extends BaseMigration
{
    public function up(): void
    {
        // New append-only classification history; no changes to orders or percentages.
        $this->table('mecanica_curva_versoes')
            ->addColumn('entressafra_id', 'integer')
            ->addColumn('area_curva_id', 'integer')
            ->addColumn('usuario_id', 'integer', ['null'=>true])
            ->addColumn('usuario_nome', 'string', ['limit'=>150])
            ->addColumn('conteudo', 'text')
            ->addColumn('created', 'datetime')
            ->addIndex(['entressafra_id','area_curva_id','created','id'], ['name'=>'ix_mecanica_curva_corte'])
            ->addForeignKey('entressafra_id', 'entressafras', 'id', ['delete'=>'RESTRICT'])
            ->addForeignKey('area_curva_id', 'areas_fabrica', 'id', ['delete'=>'RESTRICT'])
            ->create();
        if ($this->getAdapter()->getAdapterType()==='pgsql') {
            $this->execute("CREATE FUNCTION bloquear_mecanica_curva_alteracao() RETURNS trigger AS $$ BEGIN RAISE EXCEPTION 'Histórico da classificação da Mecânica é imutável'; END; $$ LANGUAGE plpgsql");
            $this->execute('CREATE TRIGGER mecanica_curva_imutavel BEFORE UPDATE OR DELETE ON mecanica_curva_versoes FOR EACH ROW EXECUTE FUNCTION bloquear_mecanica_curva_alteracao()');
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback destrutivo bloqueado: preservar o histórico da classificação da Mecânica.');
    }
}
