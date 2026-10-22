<?php
declare(strict_types=1);
use Migrations\BaseMigration;

class ProtectApontamentos extends BaseMigration
{
    public function up(): void
    {
        if ($this->getAdapter()->getAdapterType() !== 'pgsql') { return; }
        $this->execute('CREATE EXTENSION IF NOT EXISTS btree_gist');
        $this->execute("ALTER TABLE apontamentos ADD CONSTRAINT ck_apontamento_intervalo CHECK (fim > inicio AND minutos > 0 AND minutos * 60 = EXTRACT(EPOCH FROM (fim - inicio))), ADD CONSTRAINT ck_apontamento_descricao CHECK (length(trim(descricao)) > 0), ADD CONSTRAINT ck_funcionario_identidade CHECK (length(trim(funcionario_chave)) > 0 AND length(trim(funcionario_matricula)) > 0 AND length(trim(funcionario_vinculo)) > 0), ADD CONSTRAINT ck_controle_pcm CHECK (controle_pcm IN ('pendente', 'conferido', 'lancado'))");
        $this->execute("ALTER TABLE apontamentos ADD CONSTRAINT ex_apontamento_funcionario_horario EXCLUDE USING gist (funcionario_chave WITH =, tsrange(inicio, fim, '[)') WITH &&) WHERE (NOT cancelado)");
        $this->execute("ALTER TABLE usuarios ADD CONSTRAINT ck_usuario_perfil CHECK (perfil IN ('ADMIN_PCM', 'PCM', 'RESPONSAVEL_SETOR'))");
    }
    public function down(): void
    {
        if ($this->getAdapter()->getAdapterType() !== 'pgsql') { return; }
        $this->execute('ALTER TABLE apontamentos DROP CONSTRAINT ex_apontamento_funcionario_horario, DROP CONSTRAINT ck_apontamento_intervalo, DROP CONSTRAINT ck_apontamento_descricao, DROP CONSTRAINT ck_funcionario_identidade, DROP CONSTRAINT ck_controle_pcm');
        $this->execute('ALTER TABLE usuarios DROP CONSTRAINT ck_usuario_perfil');
        // Extensão pode estar em uso: não é removida pelo rollback.
    }
}
