<?php
declare(strict_types=1);
use Migrations\BaseMigration;
class OsSemCaderno extends BaseMigration
{
    public function change(): void
    {
        $this->table('pendencias_consultas')
            ->addColumn('token','string',['limit'=>48])->addIndex('token',['unique'=>true])
            ->addColumn('usuario_id','integer')->addColumn('entressafra_id','integer')
            ->addColumn('estado','string',['limit'=>20,'default'=>'consultando'])
            ->addColumn('cursor_os','string',['limit'=>6,'default'=>''])
            ->addColumn('consultadas','integer',['default'=>0])
            ->addColumn('plano','text',['null'=>true])->addColumn('created','datetime')
            ->addIndex(['usuario_id','entressafra_id'])->create();
        $this->table('pendencias_ordens')
            ->addColumn('consulta_id','integer')->addColumn('numero','string',['limit'=>6])
            ->addColumn('servico_codigo','string',['limit'=>6])->addColumn('servico_nome','string',['limit'=>255])
            ->addColumn('equipe','string',['limit'=>6])->addColumn('situacao','string',['limit'=>2])
            ->addColumn('snapshot','text')->addColumn('selecionada','boolean',['default'=>false])
            ->addForeignKey('consulta_id','pendencias_consultas','id',['delete'=>'RESTRICT'])
            ->addIndex(['consulta_id','numero'],['unique'=>true])
            ->addIndex(['consulta_id','servico_codigo','equipe','situacao'])->create();
    }
}
