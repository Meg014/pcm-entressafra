<?php
declare(strict_types=1);
namespace App\Controller;
use App\Service\{Access,FilaPcm,OrderProgress};

class FilaPcmController extends OperacaoController
{
    public function index(): void
    {
        Access::pcm($this->currentUser);$service=new FilaPcm();$count=$service->queueCount($this->currentUser);
        $pages=max(1,(int)ceil($count/10));$page=max(1,min((int)$this->request->getQuery('pagina',1),$pages));
        $this->set(compact('page','pages'));$this->set('rows',$service->queue($this->currentUser,$page));
    }
    public function view($id)
    {
        Access::pcm($this->currentUser);$activity=$this->activity((int)$id);
        if($this->request->is('post')){
            try{
                $data=$this->request->getData();$category=$data['categoria']??'horas';
                if(!in_array($category,['horas','insumos'],true))throw new \DomainException('Categoria inválida.');
                if(!is_array($data['selecionados']??null)||!is_array($data['revisoes']??null))throw new \DomainException('Selecione os registros desta categoria.');
                foreach(['acao','motivo','confirmar'] as $field)if(isset($data[$field])&&!is_string($data[$field]))throw new \DomainException('Operação inválida.');
                $selection=[];
                foreach($data['selecionados'] as $entry){
                    if(!is_scalar($entry)||!ctype_digit((string)$entry))throw new \DomainException('Seleção inválida.');
                    $selection[]=['id'=>$entry,'revisao'=>$data['revisoes'][$entry]??null];
                }
                $service=new FilaPcm();$method=$category==='insumos'?'confirmSupplies':'confirm';
                $n=$service->$method((int)$this->currentUser->id,(int)$id,$selection,$data['acao']??'',$data['motivo']??'',($data['confirmar']??'')==='1');
                $this->Flash->success($n.($category==='insumos'?' insumo(s)':' apontamento(s)').' atualizado(s). Nenhuma escrita foi feita no TOTVS.');
                return $this->redirect(['action'=>'view',$id,'?'=>$this->request->getQueryParams(),'#'=>$category]);
            }catch(\DomainException $e){$this->Flash->error($e->getMessage());}
        }
        $rows=$this->paginate($this->fetchTable('Apontamentos')->find()->where(['atividade_id'=>(int)$id])->orderBy(['created'=>'ASC','id'=>'ASC']),['limit'=>10,'maxLimit'=>10]);
        [$supplies,$supplyPage,$supplyPages,$supplyTotal]=$this->sectionPage($this->fetchTable('Insumos')->find()->where(['atividade_id'=>(int)$id])->orderBy(['created'=>'ASC','id'=>'ASC']),'insumos_pagina',10);
        $historyQuery=$this->fetchTable('Auditorias')->find()->where(['OR'=>[
            'apontamento_id IN'=>$this->fetchTable('Apontamentos')->find()->select(['id'])->where(['atividade_id'=>(int)$id]),
            'insumo_id IN'=>$this->fetchTable('Insumos')->find()->select(['id'])->where(['atividade_id'=>(int)$id]),
        ]])->orderBy(['Auditorias.created'=>'DESC','Auditorias.id'=>'DESC']);
        [$history,$historyPage,$historyPages,$historyTotal]=$this->sectionPage($historyQuery,'historico_pagina',20);
        $completion=OrderProgress::status((int)$id);
        $this->set(compact('activity','rows','supplies','supplyPage','supplyPages','supplyTotal','history','historyPage','historyPages','historyTotal','completion'));
    }
    private function sectionPage($query,string $key,int $limit): array
    {
        $total=$query->count();$pages=max(1,(int)ceil($total/$limit));
        $page=max(1,min((int)$this->request->getQuery($key,1),$pages));
        return [$query->limit($limit)->offset(($page-1)*$limit)->all(),$page,$pages,$total];
    }
}
