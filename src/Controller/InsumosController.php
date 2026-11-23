<?php
declare(strict_types=1);
namespace App\Controller;
use App\Service\{Access,BatchEntryException,OperacaoRapida};
use App\Integration\Totvs\GatewayInterface;

class InsumosController extends OperacaoController
{
    public function add(GatewayInterface $gateway)
    {
        $errors=[];$selectedOrder=null;
        if($this->request->is('post')){
            try{$ids=(new \App\Service\Insumos($gateway))->saveBatch((int)$this->currentUser->id,$this->request->getData());$this->Flash->success(count($ids).' insumos registrados com sucesso.');return $this->redirect(['action'=>'index']);}
            catch(BatchEntryException $e){$errors=$e->errors;$this->Flash->error('Revise as linhas indicadas. Nenhum insumo foi salvo.');}
            catch(\DomainException $e){$this->Flash->error($e->getMessage());}
        }
        $id=$this->request->getData('atividade_id')??$this->request->getQuery('atividade_id');
        if(is_scalar($id)&&ctype_digit((string)$id)&&(int)$id>0){
            $activity=$this->activitiesQuery()->where(['Atividades.id'=>(int)$id])->first();
            if($activity){try{$service=new OperacaoRapida($gateway);$selectedOrder=$service->result($service->activity($this->currentUser,(int)$activity->id));}catch(\DomainException){}}
        }
        $this->set(compact('errors','selectedOrder'));
    }
    public function index(): void
    {
        $filters=\App\Service\InsumosConsulta::filters($this->request->getQueryParams());
        $query=\App\Service\InsumosConsulta::query($this->currentUser,$filters);
        $this->set('rows',$this->paginate($query,['limit'=>50,'maxLimit'=>50,'sortableFields'=>[]]));
        $this->set(compact('filters'));
    }

    public function edit($id)
    {
        $this->request->allowMethod(['post']);
        Access::admin($this->currentUser);
        try {
            (new \App\Service\InsumosEdicao())->edit((int)$this->currentUser->id,(int)$id,$this->request->getData());
            $this->Flash->success('Insumo atualizado. Histórico preservado.');
        } catch (\DomainException $e) {
            $this->Flash->error($e->getMessage());
        }
        $filters=\App\Service\InsumosConsulta::filters($this->request->getQueryParams());
        $page=$this->request->getQuery('page','1');
        if(is_string($page)&&ctype_digit($page))$filters['page']=$page;
        return $this->redirect(['action'=>'index','?'=>$filters]);
    }
}
