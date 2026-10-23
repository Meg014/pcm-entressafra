<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

class CadernosTable extends CadastroTable
{
    public function initialize(array $config): void
    {
        parent::initialize($config); $this->addBehavior('PeriodoScope');
        $this->setTable('cadernos');
        $this->setEntityClass(\App\Model\Entity\Caderno::class);
        $this->belongsTo('Entressafras', ['foreignKey' => 'entressafra_id', 'joinType' => 'INNER', 'propertyName' => 'entressafra']);
        $this->belongsTo('SetorLegado', ['className'=>'Setores','foreignKey'=>'setor_id','propertyName'=>'setor']);
        $this->belongsTo('SetorPrincipal',['className'=>'AreasFabrica','foreignKey'=>'area_curva_id']);
        $this->belongsToMany('Setores',['through'=>'CadernosSetores','foreignKey'=>'caderno_id','targetForeignKey'=>'setor_id','propertyName'=>'setores','saveStrategy'=>'replace']);
    }

    public function beforeMarshal(\Cake\Event\EventInterface $event, \ArrayObject $data, \ArrayObject $options): void
    {
        // Compatibility for existing callers; the UI uses setores._ids exclusively.
        if (!isset($data['setores']) && isset($data['setor_id'])) { $data['setores']=['_ids'=>[$data['setor_id']]]; }
    }
    public function beforeRules(\Cake\Event\EventInterface $event,$entity,\ArrayObject $options): void
    {
        if ($entity->isNew() && !$entity->setor_id && $entity->setores) { $entity->setor_id=$entity->setores[0]->id; }
    }

    public function validationDefault(Validator $validator): Validator
    {
        parent::validationDefault($validator);
        foreach (['entressafra_id' => 'entressafra'] as $field => $label) {
            $validator->integer($field, 'Selecione um registro válido.')
                ->greaterThan($field, 0)
                ->requirePresence($field, 'create')
                ->notEmptyString($field, 'Selecione o(a) ' . $label . '.');
        }
        // Legacy integrations with explicit executor associations remain readable.
        // New administration uses the principal area, never an executor selector.
        return $validator->integer('area_curva_id')->greaterThan('area_curva_id',0)
            ->requirePresence('area_curva_id',fn($context)=>$context['newRecord']&&empty($context['data']['setor_id'])&&empty($context['data']['setores']))
            ->notEmptyString('area_curva_id','Selecione o setor principal da Curva S.')
            ->scalar('area_fisica')->maxLength('area_fisica',150)->allowEmptyString('area_fisica');
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->addUpdate(function ($entity): bool {
            return (!$entity->isDirty('entressafra_id') && !$entity->isDirty('setor_id')) || !\App\Service\Access::table('Atividades')->exists(['caderno_id' => $entity->id]);
        }, 'vinculoHistorico', ['errorField' => 'entressafra_id', 'message' => 'Caderno com atividades não pode mudar de entressafra nem reescrever a referência legada. Edite os setores associados.']);
        $rules->add($rules->existsIn(['entressafra_id'], 'Entressafras'), ['errorField' => 'entressafra_id', 'message' => 'Entressafra não encontrada.']);
        $rules->add(function($entity):bool {
            if (!$entity->isDirty('setores')) { return true; }
            $ids=array_map(fn($s)=>(int)$s->id,$entity->setores??[]);
            if (!$ids && $entity->isNew()) { return true; }
            if ($entity->isNew()) { return true; }
            if (!array_diff(\App\Service\Access::notebookSectors((int)$entity->id),$ids)) { return true; }
            return !\App\Service\Access::table('Atividades')->exists(['caderno_id'=>$entity->id,'OR'=>[['setor_executante_id NOT IN'=>$ids],['setor_executante_id IS'=>null]]]);
        },'setoresHistoricos',['errorField'=>'setores','message'=>'Mantenha os setores das atividades existentes; resolva identificações pendentes antes de remover associações.']);
        $rules->add(function($entity):bool {
            if(!$entity->area_curva_id)return (bool)$entity->setor_id;
            $area=\App\Service\Access::table('AreasFabrica')->find()->where(['id'=>$entity->area_curva_id])->first();
            if($entity->setor_id&&$area&&$area->entressafra_id===null)return true;
            return (new \App\Service\SetoresApresentacao())->availableAreas((int)$entity->entressafra_id)->where(['AreasFabrica.id'=>$entity->area_curva_id])->count()===1;
        },'principalDaEntressafra',['errorField'=>'area_curva_id','message'=>'Selecione um setor da Curva S desta entressafra.']);
        $rules->add($rules->isUnique(['entressafra_id','area_curva_id','nome']),['errorField'=>'nome','message'=>'Já existe este caderno no setor principal selecionado.']);
        $rules->add($rules->isUnique(['entressafra_id', 'setor_id', 'nome']), ['errorField' => 'nome', 'message' => 'Já existe este caderno no setor e entressafra selecionados.']);
        return $rules;
    }
    public function afterSave(\Cake\Event\EventInterface $event,$entity,\ArrayObject $options): void
    {
        if(!$entity->area_curva_id)return;
        $t=\App\Service\Access::table('CadernosAreas');$data=['caderno_id'=>$entity->id,'area_id'=>$entity->area_curva_id,'entressafra_id'=>$entity->entressafra_id];
        if(!$t->exists($data))$t->saveOrFail(\App\Service\Access::entity($t,$data));
    }
}
