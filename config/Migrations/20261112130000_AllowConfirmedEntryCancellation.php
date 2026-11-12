<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AllowConfirmedEntryCancellation extends BaseMigration
{
    public function up(): void
    {
        if ($this->getAdapter()->getAdapterType()!=='pgsql') {return;}
        $this->execute(<<<'SQL'
CREATE OR REPLACE FUNCTION pcm_protect_confirmed_entry() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF OLD.controle_pcm='lancado' THEN
        IF (NEW.atividade_id,NEW.funcionario_chave,NEW.funcionario_nome,NEW.funcionario_matricula,
            NEW.funcionario_filial,NEW.funcionario_vinculo,NEW.snapshot,NEW.inicio,NEW.fim,
            NEW.inicio_manha,NEW.fim_manha,NEW.inicio_tarde,NEW.fim_tarde,NEW.minutos,NEW.descricao)
            IS DISTINCT FROM
           (OLD.atividade_id,OLD.funcionario_chave,OLD.funcionario_nome,OLD.funcionario_matricula,
            OLD.funcionario_filial,OLD.funcionario_vinculo,OLD.snapshot,OLD.inicio,OLD.fim,
            OLD.inicio_manha,OLD.fim_manha,OLD.inicio_tarde,OLD.fim_tarde,OLD.minutos,OLD.descricao) THEN
            RAISE EXCEPTION 'Reabra a confirmação pelo PCM antes de corrigir o apontamento.';
        END IF;
        IF NEW.cancelado IS DISTINCT FROM OLD.cancelado THEN
            IF NOT (OLD.cancelado=false AND NEW.cancelado=true AND NEW.controle_pcm='lancado'
                AND NEW.confirmado_por IS NOT DISTINCT FROM OLD.confirmado_por
                AND NEW.confirmado_em IS NOT DISTINCT FROM OLD.confirmado_em
                AND NEW.revisao_pcm=OLD.revisao_pcm+1
                AND EXISTS (SELECT 1 FROM usuarios WHERE id=NEW.alterado_por AND ativo AND perfil='ADMIN_PCM')) THEN
                RAISE EXCEPTION 'Cancelamento de apontamento confirmado exige ADMIN_PCM e preservação da confirmação TOTVS.';
            END IF;
        END IF;
    END IF;
    RETURN NEW;
END;
$$
SQL);
    }

    public function down(): void
    {
        if ($this->getAdapter()->getAdapterType()!=='pgsql') {return;}
        $this->execute(<<<'SQL'
CREATE OR REPLACE FUNCTION pcm_protect_confirmed_entry() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF OLD.controle_pcm='lancado' AND
       (NEW.atividade_id,NEW.funcionario_chave,NEW.funcionario_nome,NEW.funcionario_matricula,
        NEW.funcionario_filial,NEW.funcionario_vinculo,NEW.snapshot,NEW.inicio,NEW.fim,NEW.minutos,NEW.descricao,NEW.cancelado)
       IS DISTINCT FROM
       (OLD.atividade_id,OLD.funcionario_chave,OLD.funcionario_nome,OLD.funcionario_matricula,
        OLD.funcionario_filial,OLD.funcionario_vinculo,OLD.snapshot,OLD.inicio,OLD.fim,OLD.minutos,OLD.descricao,OLD.cancelado) THEN
        RAISE EXCEPTION 'Reabra a confirmação pelo PCM antes de corrigir o apontamento.';
    END IF;
    RETURN NEW;
END;
$$
SQL);
    }
}
