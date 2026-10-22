<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

abstract class CadastroTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setPrimaryKey('id');
        $this->setDisplayField('nome');
        $this->addBehavior('Timestamp');
    }

    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->scalar('nome')->maxLength('nome', $this->getAlias() === 'Entressafras' ? 100 : 150, 'O nome ultrapassa o limite de caracteres.')
            ->requirePresence('nome', 'create', 'Informe o nome.')
            ->notEmptyString('nome', 'Informe o nome.')
            ->add('nome', 'notBlank', [
                'rule' => static fn($value) => is_string($value) && trim($value) !== '',
                'message' => 'Informe um nome válido.',
            ])
            ->scalar('descricao')->allowEmptyString('descricao')
            ->boolean('ativo', 'Selecione uma situação válida.');
    }
}
