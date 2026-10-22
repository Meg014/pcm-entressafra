<?php
declare(strict_types=1);
namespace App\Model\Table;
use Cake\ORM\Table;
class EvolucaoSemanasTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config); $this->addBehavior('PeriodoScope');
        $this->setTable('evolucao_semanas');
        $this->setPrimaryKey('id');
    }
}