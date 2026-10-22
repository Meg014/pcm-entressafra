<?php
declare(strict_types=1);
namespace App\Model\Table;
class VinculosItensTable extends \Cake\ORM\Table
{
    public function initialize(array $config): void
    { parent::initialize($config); $this->setTable('vinculos_itens'); $this->setPrimaryKey('id'); }
}
