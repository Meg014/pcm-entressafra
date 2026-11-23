<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddUnidadeInsumos extends BaseMigration
{
    public function up(): void
    {
        $this->table('insumos')->addColumn('unidade', 'string', ['limit'=>3, 'null'=>true, 'default'=>null])
            ->addIndex(['data', 'id'], ['name'=>'idx_insumos_data_id'])->update();
        if ($this->getAdapter()->getAdapterType() === 'pgsql') {
            $this->execute("ALTER TABLE insumos ADD CONSTRAINT ck_insumos_unidade CHECK (unidade IS NULL OR unidade IN ('m','kg','und','PSI'))");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('As unidades e o histórico de insumos devem ser preservados.');
    }
}
