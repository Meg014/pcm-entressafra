<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class MecanicaListaManualBombas extends BaseMigration
{
    public function up(): void
    {
        // Existing immutable snapshots are lists of REDUTORES, never lists of BOMBAS.
        // DDL adds metadata without rewriting their content or firing UPDATE triggers.
        $this->table('mecanica_curva_versoes')
            ->addColumn('grupo_manual','string',['limit'=>16,'default'=>'redutores','null'=>false])
            ->update();
        if($this->getAdapter()->getAdapterType()==='pgsql'){
            $this->execute("ALTER TABLE mecanica_curva_versoes ADD CONSTRAINT ck_mecanica_grupo_manual CHECK (grupo_manual IN ('redutores','bombas'))");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Preservar o significado e o histórico das listas de Bombas e Redutores.');
    }
}
