<?php
declare(strict_types=1);
use Migrations\BaseMigration;
class ProtectEvolucaoVersions extends BaseMigration
{
    public function up(): void
    {
        $this->table('evolucao_pesos')->addColumn('versao_id','integer')->addColumn('atividade_id','integer')
            ->addColumn('peso','decimal',['precision'=>14,'scale'=>2])->addIndex(['versao_id','atividade_id'],['unique'=>true])
            ->addForeignKey('versao_id','evolucao_versoes','id',['delete'=>'RESTRICT'])->addForeignKey('atividade_id','atividades','id',['delete'=>'RESTRICT'])->create();
        $this->table('evolucao_metas')->addColumn('versao_id','integer')->addColumn('semana_id','integer')
            ->addColumn('percentual','decimal',['precision'=>7,'scale'=>2])->addIndex(['versao_id','semana_id'],['unique'=>true])
            ->addForeignKey('versao_id','evolucao_versoes','id',['delete'=>'RESTRICT'])->addForeignKey('semana_id','evolucao_semanas','id',['delete'=>'RESTRICT'])->create();
        if ($this->getAdapter()->getAdapterType()==='pgsql') {
            $this->execute('ALTER TABLE evolucao_pesos ADD CONSTRAINT ck_evolucao_peso CHECK (peso>0)');
            $this->execute('ALTER TABLE evolucao_metas ADD CONSTRAINT ck_evolucao_meta CHECK (percentual>=0 AND percentual<=100)');
            $this->execute("CREATE FUNCTION pcm_evolucao_immutable() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'Histórico de evolução imutável; registre uma nova versão.'; END; \$\$");
            foreach(['evolucao_registros','evolucao_versoes','evolucao_pesos','evolucao_metas','evolucao_lotes'] as $table) {
                $this->execute('CREATE TRIGGER trg_'.$table.'_immutable BEFORE UPDATE OR DELETE ON '.$table.' FOR EACH ROW EXECUTE FUNCTION pcm_evolucao_immutable()');
            }
        }
    }
    public function down(): void { throw new RuntimeException('Versões e históricos devem ser preservados.'); }
}
