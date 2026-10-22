<?php
declare(strict_types=1);
use Migrations\BaseMigration;
class OperacaoSimplificada extends BaseMigration
{
    public function up(): void
    {
        $this->table('usuarios')->addColumn('computador_operacional','boolean',['default'=>false])->update();
        $this->table('apontamentos')->addColumn('revisao_pcm','integer',['default'=>0])
            ->addColumn('confirmado_por','integer',['null'=>true])->addColumn('confirmado_em','datetime',['null'=>true])
            ->addForeignKey('confirmado_por','usuarios','id',['delete'=>'RESTRICT'])
            ->addIndex(['cancelado','controle_pcm','created','atividade_id'],['name'=>'idx_fila_pcm'])->update();
        if($this->getAdapter()->getAdapterType()==='pgsql') {
            $this->execute("ALTER TABLE usuarios ADD CONSTRAINT ck_computador_perfil CHECK (NOT computador_operacional OR perfil='RESPONSAVEL_SETOR')");
            $this->execute("CREATE FUNCTION pcm_protect_confirmed_entry() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN IF OLD.controle_pcm='lancado' AND (NEW.atividade_id,NEW.funcionario_chave,NEW.funcionario_nome,NEW.funcionario_matricula,NEW.funcionario_filial,NEW.funcionario_vinculo,NEW.snapshot,NEW.inicio,NEW.fim,NEW.minutos,NEW.descricao,NEW.cancelado) IS DISTINCT FROM (OLD.atividade_id,OLD.funcionario_chave,OLD.funcionario_nome,OLD.funcionario_matricula,OLD.funcionario_filial,OLD.funcionario_vinculo,OLD.snapshot,OLD.inicio,OLD.fim,OLD.minutos,OLD.descricao,OLD.cancelado) THEN RAISE EXCEPTION 'Reabra a confirmação pelo PCM antes de corrigir o apontamento.'; END IF; RETURN NEW; END; \$\$");
            $this->execute('CREATE TRIGGER trg_protect_confirmed_entry BEFORE UPDATE ON apontamentos FOR EACH ROW EXECUTE FUNCTION pcm_protect_confirmed_entry()');
        }
    }
    public function down(): void { throw new RuntimeException('Reversão automática não permitida: preservar confirmações e contas.'); }
}
