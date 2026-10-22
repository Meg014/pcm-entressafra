<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;

class SetoresTable extends CadastroTable
{
    public function beforeMarshal(\Cake\Event\EventInterface $event, \ArrayObject $data, \ArrayObject $options): void
    {
        foreach(['area_totvs','filial_totvs'] as $field) { if(isset($data[$field]) && is_string($data[$field])) { $data[$field]=trim($data[$field]); } }
        if(isset($data['area_totvs']) && is_string($data['area_totvs']) && trim($data['area_totvs'])==='') { $data['area_totvs']=null; $data['filial_totvs']=null; }
    }
    public function validationDefault(\Cake\Validation\Validator $validator): \Cake\Validation\Validator
    {
        return parent::validationDefault($validator)->scalar('area_totvs')->maxLength('area_totvs',6)->allowEmptyString('area_totvs')->scalar('filial_totvs')->maxLength('filial_totvs',2)->allowEmptyString('filial_totvs');
    }
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('setores');
        $this->setEntityClass(\App\Model\Entity\Setor::class);
        $this->belongsToMany('Cadernos',['through'=>'CadernosSetores','foreignKey'=>'setor_id','targetForeignKey'=>'caderno_id']);
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['nome']), ['errorField' => 'nome', 'message' => 'Já existe um setor com este nome.']);
        $rules->add($rules->isUnique(['filial_totvs','area_totvs']), ['errorField'=>'area_totvs','message'=>'Esta área e filial TOTVS já estão mapeadas para outro setor.']);
        return $rules;
    }
}
