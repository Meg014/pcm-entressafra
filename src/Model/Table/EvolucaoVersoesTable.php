<?php
declare(strict_types=1);
namespace App\Model\Table;
use Cake\ORM\Table;
class EvolucaoVersoesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config); $this->addBehavior('PeriodoScope');
        $this->setTable('evolucao_versoes');
        $this->setPrimaryKey('id');
    }
}