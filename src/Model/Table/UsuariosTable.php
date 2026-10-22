<?php
declare(strict_types=1);
namespace App\Model\Table;
use Cake\ORM\Table;
class UsuariosTable extends Table
{
 public function initialize(array $config): void { parent::initialize($config); $this->setTable('usuarios'); $this->setEntityClass(\App\Model\Entity\Usuario::class); $this->setPrimaryKey('id'); $this->hasMany('UsuariosSetores', ['foreignKey' => 'usuario_id']); }
}
