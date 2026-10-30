<?php
declare(strict_types=1);
use Migrations\BaseMigration;

class AddCancelamentoInsumos extends BaseMigration
{
    public function up(): void
    {
        $this->table('insumos')
            ->addColumn('cancelado','boolean',['default'=>false,'null'=>false])
            ->addIndex(['cancelado','controle_pcm','atividade_id'],['name'=>'idx_insumos_ativos_pcm'])->update();
        if ($this->getAdapter()->getAdapterType()==='pgsql') {
            $this->execute("ALTER TABLE insumos ADD CONSTRAINT ck_insumo_cancelamento_totvs CHECK (NOT (cancelado AND controle_pcm='lancado'))");
        }
    }
    public function down(): void
    {
        throw new RuntimeException('Os cancelamentos e históricos de insumos devem ser preservados.');
    }
}
