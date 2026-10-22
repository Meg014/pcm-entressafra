<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateEntressafras extends BaseMigration
{
    public function change(): void
    {
        $this->table('entressafras')
            ->addColumn('nome', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('data_inicio', 'date', ['null' => false])
            ->addColumn('data_fim', 'date', ['null' => false])
            ->addColumn('status', 'string', ['limit' => 20, 'null' => false, 'default' => 'planejamento'])
            ->addColumn('observacoes', 'text', ['null' => true])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addColumn('modified', 'datetime', ['null' => false])
            ->addIndex(['nome'], ['unique' => true])
            ->addIndex(['status'])
            ->create();
    }
}
