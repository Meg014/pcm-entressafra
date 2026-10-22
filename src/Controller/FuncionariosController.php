<?php
declare(strict_types=1);
namespace App\Controller;
use App\Service\Access;
use App\Integration\Totvs\Employees;
class FuncionariosController extends AppController
{
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);
        if($event->isStopped()) { return; }
        Access::pcm($this->currentUser);
    }
    protected function employees(): Employees { return new Employees(); }
    public function pendencias(): void
    {
        $groups=[]; $unavailable=false;
        try { $groups=$this->employees()->duplicateGroups(); }
        catch(\DomainException $error) { $unavailable=true; $this->Flash->error($error->getMessage()); }
        $hasMore=count($groups)>50;
        $this->response=$this->response->withHeader('Cache-Control','no-store, private');
        $this->set(['groups'=>array_slice($groups,0,50),'hasMore'=>$hasMore,'unavailable'=>$unavailable]);
    }
}
