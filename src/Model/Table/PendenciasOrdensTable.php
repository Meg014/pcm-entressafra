<?php
declare(strict_types=1);
namespace App\Model\Table;
class PendenciasOrdensTable extends \Cake\ORM\Table
{
    public function initialize(array $config): void { parent::initialize($config);$this->setTable('pendencias_ordens');$this->setPrimaryKey('id'); }
}
