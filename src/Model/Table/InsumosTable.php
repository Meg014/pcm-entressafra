<?php
declare(strict_types=1);
namespace App\Model\Table;
use Cake\ORM\Table;

class InsumosTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('insumos');$this->setPrimaryKey('id');
        $this->addBehavior('PeriodoScope',['find'=>false,'via'=>'atividade']);
        $this->belongsTo('Atividades',['foreignKey'=>'atividade_id','propertyName'=>'atividade']);
    }
}
