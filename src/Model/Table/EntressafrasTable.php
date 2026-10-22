<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

class EntressafrasTable extends CadastroTable
{
    public const STATUS = ['planejamento' => 'Planejamento', 'ativa' => 'Ativa', 'encerrada' => 'Encerrada'];

    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('entressafras');
        $this->setEntityClass(\App\Model\Entity\Entressafra::class);
        $this->hasMany('Cadernos', ['foreignKey' => 'entressafra_id', 'dependent' => false]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        parent::validationDefault($validator);
        return $validator
            ->date('data_inicio', ['ymd'], 'Informe uma data inicial válida.')
            ->requirePresence('data_inicio', 'create')->notEmptyDate('data_inicio', 'Informe a data inicial.')
            ->date('data_fim', ['ymd'], 'Informe uma data final válida.')
            ->requirePresence('data_fim', 'create')->notEmptyDate('data_fim', 'Informe a data final.')
            ->add('data_fim', 'periodo', [
                'rule' => static function ($value, array $context): bool {
                    $inicio = $context['data']['data_inicio'] ?? null;
                    if (!$inicio) {
                        return true;
                    }
                    try {
                        return new \DateTimeImmutable((string)$value) >= new \DateTimeImmutable((string)$inicio);
                    } catch (\Exception) {
                        return false;
                    }
                },
                'message' => 'A data final deve ser igual ou posterior à data inicial.',
            ])
            ->requirePresence('status', 'create')->notEmptyString('status', 'Selecione o status.')
            ->inList('status', array_keys(self::STATUS), 'Selecione um status válido.')
            ->scalar('observacoes')->allowEmptyString('observacoes');
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['nome']), ['errorField' => 'nome', 'message' => 'Já existe uma entressafra com este nome.']);
        return $rules;
    }
}
