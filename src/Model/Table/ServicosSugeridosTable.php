<?php
declare(strict_types=1);
namespace App\Model\Table;
use Cake\ORM\Table;

class ServicosSugeridosTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);$this->setTable('servicos_sugeridos');$this->setPrimaryKey('id');
    }
}
