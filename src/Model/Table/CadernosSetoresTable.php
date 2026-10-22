<?php
declare(strict_types=1);
namespace App\Model\Table;
class CadernosSetoresTable extends \Cake\ORM\Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config); $this->setTable('cadernos_setores');
        $this->belongsTo('Cadernos',['foreignKey'=>'caderno_id']); $this->belongsTo('Setores',['foreignKey'=>'setor_id']);
    }
}
