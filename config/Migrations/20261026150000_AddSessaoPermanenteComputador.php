<?php
declare(strict_types=1);
use Migrations\BaseMigration;

class AddSessaoPermanenteComputador extends BaseMigration
{
    public function up(): void
    {
        $this->table('usuarios')->addColumn('sessao_computador_hash','string',['limit'=>64,'null'=>true,'default'=>null])->update();
    }
    public function down(): void
    {
        throw new RuntimeException('Reversão automática desabilitada para preservar as sessões dos computadores.');
    }
}
