<?php
declare(strict_types=1);
namespace App\Model\Table;
use Cake\ORM\Table;
class UsuariosSetoresTable extends Table
{
 public function initialize(array $config): void { parent::initialize($config); $this->setTable('usuarios_setores'); $this->setPrimaryKey('id');  }
}
