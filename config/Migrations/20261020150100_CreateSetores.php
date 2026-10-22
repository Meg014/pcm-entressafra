<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateSetores extends BaseMigration
{
    public function change(): void
    {
        $this->table('setores')
            ->addColumn('nome', 'string', ['limit' => 150, 'null' => false])
            ->addColumn('descricao', 'text', ['null' => true])
            ->addColumn('ativo', 'boolean', ['null' => false, 'default' => true])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['nome'], ['unique' => true])
            ->addIndex(['ativo'])
            ->create();
    }
}
