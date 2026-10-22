<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddPostgresIntegrityChecks extends BaseMigration
{
    public function up(): void
    {
        if ($this->getAdapter()->getAdapterType() !== 'pgsql') {
            return; // SQLite é usado somente na suíte local; validações ORM continuam ativas.
        }
        $this->execute("ALTER TABLE entressafras ADD CONSTRAINT ck_entressafras_datas CHECK (data_fim >= data_inicio), ADD CONSTRAINT ck_entressafras_status CHECK (status IN ('planejamento', 'ativa', 'encerrada')), ADD CONSTRAINT ck_entressafras_nome CHECK (length(trim(nome)) > 0)");
        $this->execute("ALTER TABLE setores ADD CONSTRAINT ck_setores_nome CHECK (length(trim(nome)) > 0)");
        $this->execute("ALTER TABLE cadernos ADD CONSTRAINT ck_cadernos_nome CHECK (length(trim(nome)) > 0)");
    }

    public function down(): void
    {
        if ($this->getAdapter()->getAdapterType() !== 'pgsql') {
            return;
        }
        $this->execute('ALTER TABLE cadernos DROP CONSTRAINT ck_cadernos_nome');
        $this->execute('ALTER TABLE setores DROP CONSTRAINT ck_setores_nome');
        $this->execute('ALTER TABLE entressafras DROP CONSTRAINT ck_entressafras_datas, DROP CONSTRAINT ck_entressafras_status, DROP CONSTRAINT ck_entressafras_nome');
    }
}
