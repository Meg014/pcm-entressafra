<?php
declare(strict_types=1);
use Migrations\BaseMigration;
class AddDisponibilidadeAtividades extends BaseMigration
{
    public function change(): void
    {
        $this->table('atividades')->addColumn('disponivel_totvs','boolean',['null'=>true,'default'=>null])->addIndex(['disponivel_totvs'])->update();
    }
}
