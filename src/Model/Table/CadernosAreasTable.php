<?php
declare(strict_types=1);
namespace App\Model\Table;
class CadernosAreasTable extends \Cake\ORM\Table
{
    public function initialize(array $config): void
    {parent::initialize($config);$this->setTable('cadernos_areas');$this->setPrimaryKey('id');$this->addBehavior('PeriodoScope');}
}
