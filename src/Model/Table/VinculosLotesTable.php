<?php
declare(strict_types=1);
namespace App\Model\Table;
class VinculosLotesTable extends \Cake\ORM\Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config); $this->addBehavior('PeriodoScope',['via'=>'caderno']); $this->setTable('vinculos_lotes'); $this->setPrimaryKey('id');
        $this->belongsTo('Cadernos',['foreignKey'=>'caderno_id','propertyName'=>'caderno']);
    }
}
