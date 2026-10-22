<?php
declare(strict_types=1);
namespace App\Model\Table;
use Cake\ORM\Table;
class EvolucaoMetasTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('evolucao_metas');
        $this->setPrimaryKey('id');
    }
}