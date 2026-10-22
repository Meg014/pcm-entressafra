<?php
declare(strict_types=1);
namespace App\Controller;
use App\Service\{Access,Calendarios,Evolucao};
class PrazosController extends OperacaoController
{
    public function index()
    {
        Access::pcm($this->currentUser);$season=(int)$this->request->getQuery('entressafra_id',0);if(!$season)$season=(int)($this->fetchTable('Entressafras')->find()->orderBy(['id'=>'DESC'])->first()?->id??0);$service=new Calendarios();
        if($this->request->is('post')){try{$d=$this->request->getData();if(($d['acao']??'')==='classificar')$service->classify((int)$this->currentUser->id,$season,(array)($d['ids']??[]),(int)($d['area_destino']??0),!empty($d['caderno_destino'])?(int)$d['caderno_destino']:null);elseif(($d['acao']??'')==='nova_area'){$name=trim((string)($d['nome']??''));if($name===''||mb_strlen($name)>150)throw new \DomainException('Informe um nome válido para a área física.');$t=$this->fetchTable('AreasFabrica');$e=$t->saveOrFail(Access::entity($t,['nome'=>$name]));Access::audit($this->currentUser,'nova_area_fabrica',null,null,['id'=>$e->id,'nome'=>$name],null);}else $service->save((int)$this->currentUser->id,$season,$d);$this->Flash->success('Configuração conferida e registrada.');return $this->redirect($this->request->getRequestTarget());}catch(\DomainException $e){$this->Flash->error($e->getMessage());}}
        $rows=$season?$service->rows($season):[];$book=(int)$this->request->getQuery('caderno_id');
        $q=$this->fetchTable('Atividades')->find()->select(['id','os_numero','caderno_id','area_fabrica_id','setor_executante_id','entressafra_id','area_executante_totvs'])->where(['Atividades.entressafra_id'=>$season])->contain(['Cadernos','SetorExecutante'])->orderBy(['Atividades.os_numero'=>'ASC']);
        if($book)$q->where(['Atividades.caderno_id'=>$book]);
        $total=$q->count();$page=max(1,min((int)$this->request->getQuery('pagina',1),max(1,(int)ceil($total/10))));
        $activities=[];foreach($q->limit(10)->offset(($page-1)*10)->all() as $a)$activities[]=$a->toArray()+['caderno_nome'=>$a->caderno?->nome??'Sem caderno','setor_nome'=>$a->setor_executante?->nome??'Pendente'];
        $areas=$this->fetchTable('AreasFabrica')->find('list')->orderBy(['nome'=>'ASC'])->all();$this->options();$this->set(compact('season','rows','activities','areas','service','page','total'));
    }
}
