<?php
declare(strict_types=1);
namespace App\Model\Table;
use Cake\ORM\Table;
class ApontamentosTable extends Table
{
 public function initialize(array $config): void { parent::initialize($config); $this->addBehavior('PeriodoScope',['find'=>false,'via'=>'atividade']); $this->setTable('apontamentos'); $this->setPrimaryKey('id'); $this->belongsTo('Atividades', ['foreignKey' => 'atividade_id', 'propertyName' => 'atividade']); }
}
