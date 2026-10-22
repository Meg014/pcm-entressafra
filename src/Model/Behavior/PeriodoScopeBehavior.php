<?php
declare(strict_types=1);
namespace App\Model\Behavior;

use App\Service\{PeriodoContexto,Access};
use Cake\ORM\Behavior;
use Cake\Event\EventInterface;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Query\SelectQuery;

class PeriodoScopeBehavior extends Behavior
{
    protected array $_defaultConfig=['find'=>true,'via'=>null];
    public function beforeFind(EventInterface $event,SelectQuery $query,\ArrayObject $options,bool $primary): void
    {
        $id=PeriodoContexto::id();if($id===null||!$this->getConfig('find'))return;
        $alias=$this->table()->getAlias();
        if($this->getConfig('via')==='caderno')$query->where([$alias.'.caderno_id IN'=>Access::table('Cadernos')->find()->select(['id'])->where(['Cadernos.entressafra_id'=>$id])]);
        else $query->where([$alias.'.entressafra_id'=>$id]);
    }
    public function beforeSave(EventInterface $event,EntityInterface $entity,\ArrayObject $options): void
    {
        if(PeriodoContexto::id()===null)return;
        if($this->getConfig('via')==='atividade'){PeriodoContexto::activity((int)$entity->atividade_id,true);return;}
        if($this->getConfig('via')==='caderno'){PeriodoContexto::notebook((int)$entity->caderno_id,true);return;}
        $id=(int)$entity->entressafra_id;
        if(!$id&&$entity->caderno_id)$id=(int)Access::table('Cadernos')->get($entity->caderno_id)->entressafra_id;
        PeriodoContexto::writable($id);
    }
}
