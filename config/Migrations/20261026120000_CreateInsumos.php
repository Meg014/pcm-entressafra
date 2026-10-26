<?php
declare(strict_types=1);
use Migrations\BaseMigration;

class CreateInsumos extends BaseMigration
{
    public function change(): void
    {
        $this->table('insumos')
            ->addColumn('atividade_id','integer')
            ->addColumn('funcionario_chave','string',['limit'=>255])
            ->addColumn('funcionario_nome','string',['limit'=>255])
            ->addColumn('funcionario_matricula','string',['limit'=>100])
            ->addColumn('data','date')
            ->addColumn('material','string',['limit'=>500])
            ->addColumn('quantidade','decimal',['precision'=>18,'scale'=>4])
            ->addColumn('snapshot','text')
            ->addColumn('criado_por','integer')
            ->addColumn('created','datetime')
            ->addIndex(['atividade_id','data'])
            ->addIndex(['funcionario_chave','data'])
            ->addForeignKey('atividade_id','atividades','id',['delete'=>'RESTRICT'])
            ->addForeignKey('criado_por','usuarios','id',['delete'=>'RESTRICT'])
            ->create();
    }
}
