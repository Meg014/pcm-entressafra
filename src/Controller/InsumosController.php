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
        $query=Access::activityScope($this->fetchTable('Insumos')->find()->contain(['Atividades'=>['Entressafras','SetorExecutante','Cadernos']])->innerJoinWith('Atividades'),$this->currentUser);
        $this->set('rows',$this->paginate($query->orderBy(['Insumos.id'=>'DESC']),['limit'=>50,'maxLimit'=>50]));
    }
}
