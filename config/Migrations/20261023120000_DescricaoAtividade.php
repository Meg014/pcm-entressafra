<?php
declare(strict_types=1);
use Migrations\BaseMigration;
class DescricaoAtividade extends BaseMigration
{
    public function change(): void
    {
        $this->table('atividades')->addColumn('descricao_atividade','text',['null'=>true])
            ->addColumn('descricao_origem','string',['limit'=>255,'null'=>true])->update();
    }
}
