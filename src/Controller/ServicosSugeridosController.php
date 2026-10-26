<?php
declare(strict_types=1);
namespace App\Controller;
use App\Service\{Access,ServiceSuggestions};

class ServicosSugeridosController extends AppController
{
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);if(!$event->isStopped())Access::admin($this->currentUser);
    }
    public function index(): void
    {
        $search=$this->request->getQuery('q','');if(!is_string($search)||mb_strlen($search)>100)throw new \Cake\Http\Exception\BadRequestException('Pesquisa inválida.');
        $query=$this->fetchTable('ServicosSugeridos')->find()->orderBy(['descricao_busca'=>'ASC']);
        if(trim($search)!=='')$query->where("descricao_busca LIKE :service_search ESCAPE '!'")->bind(':service_search',ServiceSuggestions::prefix($search),'string');
        $rows=$this->paginate($query,['limit'=>20,'maxLimit'=>20]);$this->set(compact('rows','search'));
    }
    public function add(){return $this->form(null);}
    public function edit($id){return $this->form((int)$id);}
    private function form(?int $id)
    {
        $row=$id!==null?$this->fetchTable('ServicosSugeridos')->get($id):null;
        if($this->request->is('post')){
            try{(new ServiceSuggestions())->save((int)$this->currentUser->id,$this->request->getData(),$id);$this->Flash->success('Sugestão salva.');return $this->redirect(['action'=>'index']);}
            catch(\DomainException $e){$this->Flash->error($e->getMessage());}
        }
        $this->set(compact('row'));$this->viewBuilder()->setTemplate('form');
    }
    public function toggle($id)
    {
        $this->request->allowMethod(['post']);
        try{(new ServiceSuggestions())->setActive((int)$this->currentUser->id,(int)$id,$this->request->getData('ativo'));$this->Flash->success('Situação da sugestão atualizada.');}
        catch(\DomainException $e){$this->Flash->error($e->getMessage());}
        return $this->redirect(['action'=>'index']);
    }
}
