<?php
declare(strict_types=1);
namespace App\Controller;
use App\Service\{Access,OperacaoRapida,Evolucao};
class OperacionalController extends OperacaoController
{
    public function ordens(\App\Integration\Totvs\GatewayInterface $gateway)
    {
        $term=$this->request->getQuery('q','');$status=200;
        try{if(!is_string($term))throw new \DomainException('Pesquisa inválida.');$data=(new OperacaoRapida($gateway))->search($this->currentUser,$term,$this->request->getQuery('tipo')==='evolucao');}
        catch(\DomainException $e){$status=422;$data=['error'=>$e->getMessage()];}
        return $this->response->withStatus($status)->withType('application/json')->withHeader('Cache-Control','no-store, private')->withStringBody(json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    }
    public function identificar(\App\Integration\Totvs\GatewayInterface $gateway)
    {
        $this->request->allowMethod(['post']);$status=200;
        try{$key=$this->request->getData('os_chave');if(!is_string($key))throw new \DomainException('Selecione uma O.S. válida.');$id=(new \App\Service\Activities($gateway))->identify((int)$this->currentUser->id,$key);$s=new OperacaoRapida($gateway);$data=['order'=>$s->result($s->activity($this->currentUser,$id,true),$this->request->getData('tipo')==='evolucao')];}catch(\DomainException $e){$status=422;$data=['error'=>$e->getMessage()];}
        return $this->response->withStatus($status)->withType('application/json')->withHeader('Cache-Control','no-store')->withStringBody(json_encode($data,JSON_THROW_ON_ERROR));
    }
    public function porcentagem()
    {
        $service=new OperacaoRapida();$selectedOrder=null;$reference=null;$weeks=[];
        $id=$this->request->getData('atividade_id')??$this->request->getQuery('atividade_id');
        try{
            if($id!==null && $id!=='') {
                if(!is_scalar($id)||!ctype_digit((string)$id))throw new \DomainException('Selecione uma O.S. válida.');
                $activity=$service->activity($this->currentUser,(int)$id,true);$season=(int)$activity->entressafra_id;
                $requested=$this->request->getData('referencia')??$this->request->getQuery('referencia');
                if($requested==='')$requested=null;
                if($requested!==null){if(!is_string($requested))throw new \DomainException('Referência inválida.');[$automatic]=$service->reference($season,null,(int)$activity->id);if($requested!==$automatic)Access::pcm($this->currentUser);}
                [$reference]=$service->reference($season,$requested,(int)$activity->id);$selectedOrder=$service->result($activity,true);
                $state=(new Evolucao())->state((int)$id,$reference);$selectedOrder=array_replace($selectedOrder,['referencia'=>$reference,'anterior'=>$state['anterior'],'percentual'=>$state['percentual'],'revisao'=>$state['revisao']]);
                $weeks=(new Evolucao())->calendar($season);
                if($this->request->is('post')) {
                    $d=$this->request->getData();
                    [$automatic]=$service->reference($season);
                    (new Evolucao())->saveBatch((int)$this->currentUser->id,$season,$reference,[['atividade_id'=>(int)$id,'percentual'=>$d['percentual']??null,'revisao'=>$d['revisao']??null,'motivo'=>$d['motivo']??'']],(string)($d['chave']??''),$reference===$automatic);
                    $this->Flash->success('Percentual acumulado registrado. Você pode lançar outra O.S.');return $this->redirect(['action'=>'porcentagem']);
                }
            }elseif($this->request->is('post')){throw new \DomainException('Pesquise e selecione uma O.S. válida.');}
        }catch(\DomainException $e){$this->Flash->error($e->getMessage());}
        $key=$this->request->getData('chave')??bin2hex(random_bytes(16));$this->set(compact('selectedOrder','reference','weeks','key'));
    }
}
