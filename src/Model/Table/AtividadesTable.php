<?php
declare(strict_types=1);
namespace App\Model\Table;
use Cake\ORM\Table;
class AtividadesTable extends Table
{
 public function initialize(array $config): void { parent::initialize($config); $this->addBehavior('PeriodoScope'); $this->setTable('atividades'); $this->setPrimaryKey('id'); $this->belongsTo('Entressafras',['foreignKey'=>'entressafra_id','propertyName'=>'entressafra']);$this->belongsTo('AreasFabrica',['foreignKey'=>'area_fabrica_id','propertyName'=>'area_fabrica']);$this->belongsTo('Cadernos', ['foreignKey' => 'caderno_id', 'propertyName' => 'caderno']); $this->belongsTo('SetorExecutante',['className'=>'Setores','foreignKey'=>'setor_executante_id','propertyName'=>'setor_executante']); }
 public function beforeSave(\Cake\Event\EventInterface $event,\Cake\Datasource\EntityInterface $entity,\ArrayObject $options): void { if(!$entity->entressafra_id && $entity->caderno_id){$entity->entressafra_id=\App\Service\Access::table('Cadernos')->get($entity->caderno_id)->entressafra_id;} }
}
