<?php
declare(strict_types=1);
use Migrations\BaseMigration;

class AddTipoPlanejamento extends BaseMigration
{
    public function change(): void
    {
        // Existing orders retain their planning and all percentage history.
        $this->table('atividades')
            ->addColumn('tipo_planejamento', 'string', ['limit'=>32, 'default'=>'PROGRAMADA', 'null'=>false])
            ->addIndex(['entressafra_id', 'tipo_planejamento'])
            ->update();
    }
}
