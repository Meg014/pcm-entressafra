<?php
declare(strict_types=1);
use Migrations\BaseMigration;

class CreateEvolucaoFisica extends BaseMigration
{
    public function up(): void
    {
        $this->table('evolucao_semanas')->addColumn('entressafra_id','integer')->addColumn('referencia','date')
            ->addIndex(['entressafra_id','referencia'],['unique'=>true])
            ->addForeignKey('entressafra_id','entressafras','id',['delete'=>'RESTRICT'])->create();
        $this->table('evolucao_lotes')->addColumn('chave','string',['limit'=>80])->addColumn('hash','string',['limit'=>64])
            ->addColumn('usuario_id','integer')->addColumn('created','datetime')->addColumn('resultado','text')
            ->addIndex(['usuario_id','chave'],['unique'=>true])->addForeignKey('usuario_id','usuarios','id',['delete'=>'RESTRICT'])->create();
        $this->table('evolucao_registros')->addColumn('atividade_id','integer')->addColumn('semana_id','integer')
            ->addColumn('percentual','decimal',['precision'=>7,'scale'=>2])->addColumn('revisao','integer')
            ->addColumn('usuario_id','integer')->addColumn('observacoes','text')->addColumn('motivo','text')->addColumn('created','datetime')
            ->addIndex(['atividade_id','semana_id','revisao'],['unique'=>true])
            ->addForeignKey('atividade_id','atividades','id',['delete'=>'RESTRICT'])
            ->addForeignKey('semana_id','evolucao_semanas','id',['delete'=>'RESTRICT'])
            ->addForeignKey('usuario_id','usuarios','id',['delete'=>'RESTRICT'])->create();
        $this->table('evolucao_versoes')->addColumn('entressafra_id','integer')->addColumn('tipo','string',['limit'=>10])
            ->addColumn('nome','string',['limit'=>150])->addColumn('conteudo','text')->addColumn('usuario_id','integer')
            ->addColumn('created','datetime')->addColumn('aprovado_por','integer',['null'=>true])->addColumn('aprovado_em','datetime',['null'=>true])
            ->addIndex(['entressafra_id','tipo'])->addForeignKey('entressafra_id','entressafras','id',['delete'=>'RESTRICT'])
            ->addForeignKey('usuario_id','usuarios','id',['delete'=>'RESTRICT'])->addForeignKey('aprovado_por','usuarios','id',['delete'=>'RESTRICT'])->create();
        foreach (['evolucao_registros'=>'percentual >= 0 AND percentual <= 100 AND revisao > 0', 'evolucao_versoes'=>"tipo IN ('pesos','metas')"] as $table=>$check) {
            if ($this->getAdapter()->getAdapterType()==='pgsql') { $this->execute('ALTER TABLE '.$table.' ADD CONSTRAINT ck_'.$table.' CHECK ('.$check.')'); }
        }
    }
    public function down(): void { throw new RuntimeException('Históricos da evolução não podem ser apagados automaticamente.'); }
}
