<?php
declare(strict_types=1);
use Migrations\BaseMigration;

class CreateVinculosLotes extends BaseMigration
{
    public function change(): void
    {
        $this->table('vinculos_lotes')
            ->addColumn('token','string',['limit'=>64])
            ->addColumn('usuario_id','integer')->addColumn('caderno_id','integer')
            ->addColumn('filtros','text')->addColumn('estado','string',['limit'=>20,'default'=>'preparando'])
            ->addColumn('cursor_os','string',['limit'=>6,'default'=>''])
            ->addColumn('created','datetime')->addColumn('modified','datetime')
            ->addIndex(['token'],['unique'=>true])->addIndex(['usuario_id','created'])
            ->addForeignKey('usuario_id','usuarios','id',['delete'=>'RESTRICT'])
            ->addForeignKey('caderno_id','cadernos','id',['delete'=>'RESTRICT'])->create();
        $this->table('vinculos_itens')
            ->addColumn('lote_id','integer')->addColumn('os_chave','string',['limit'=>255])
            ->addColumn('numero','string',['limit'=>6])->addColumn('snapshot','text')
            ->addColumn('pesquisa','text')->addColumn('estado','string',['limit'=>20])
            ->addColumn('selecionado','boolean',['default'=>false])
            ->addColumn('motivo','text',['null'=>true])->addColumn('caderno_atual','text',['null'=>true])
            ->addColumn('atividade_id','integer',['null'=>true])
            ->addIndex(['lote_id','os_chave'],['unique'=>true])->addIndex(['lote_id','estado','selecionado'])
            ->addForeignKey('lote_id','vinculos_lotes','id',['delete'=>'RESTRICT'])
            ->addForeignKey('atividade_id','atividades','id',['delete'=>'RESTRICT'])->create();
    }
}
