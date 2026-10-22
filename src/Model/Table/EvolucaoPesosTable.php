<?php
declare(strict_types=1);
namespace App\Model\Table;
use Cake\ORM\Table;
class EvolucaoPesosTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('evolucao_pesos');
        $this->setPrimaryKey('id');
    }
}