<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateCadernos extends BaseMigration
{
    public function change(): void
    {
        $this->table('cadernos')
            ->addColumn('entressafra_id', 'integer', ['null' => false])
            ->addColumn('setor_id', 'integer', ['null' => false])
            ->addColumn('nome', 'string', ['limit' => 150, 'null' => false])
            ->addColumn('descricao', 'text', ['null' => true])
            ->addColumn('ativo', 'boolean', ['null' => false, 'default' => true])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['entressafra_id', 'setor_id', 'nome'], ['unique' => true, 'name' => 'uq_cadernos_entressafra_setor_nome'])
            ->addIndex(['setor_id'])
            ->addIndex(['ativo'])
            ->addForeignKey('entressafra_id', 'entressafras', 'id', ['delete' => 'RESTRICT', 'update' => 'RESTRICT'])
            ->addForeignKey('setor_id', 'setores', 'id', ['delete' => 'RESTRICT', 'update' => 'RESTRICT'])
            ->create();
    }
}
