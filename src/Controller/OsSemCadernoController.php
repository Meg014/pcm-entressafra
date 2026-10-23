<?php
declare(strict_types=1);
namespace App\Controller;
use App\Service\{Access,OsSemCaderno,PeriodoContexto};

class OsSemCadernoController extends AppController
{
    public function index()
    {
        Access::admin($this->currentUser);$s=new OsSemCaderno();$actor=(int)$this->currentUser->id;$session=$this->request->getSession();
        $sessionKey='OsSemCaderno.u'.$actor.'.p'.PeriodoContexto::id();
        $token=(string)$this->request->getQuery('token',$session->read($sessionKey.'.token')??'');$filters=[];
        foreach(['numero','servico','equipe','situacao'] as $key)$filters[$key]=$this->request->getQuery($key,'');
        $paused=false;
        try{
            if($this->request->is('post')){
                $d=$this->request->getData();$action=(string)($d['acao']??'');
                switch($action){
                    case 'consultar':$token=$s->create($actor);$session->write($sessionKey.'.token',$token);break;
                    case 'continuar':$job=$s->job($actor,$token);if($job->estado==='consultando')$s->scan($actor,$token);elseif($job->estado==='vinculando')$s->process($actor,$token);break;
                    case 'pagina':case 'remover':case 'todas':case 'limpar':$s->select($actor,$token,$action,(array)($d['numeros']??[]),$filters,(array)($d['exibidas']??[]));break;
                    case 'resumo':$s->preview($actor,$token,(int)($d['caderno_id']??0));break;
                    case 'confirmar':$s->confirm($actor,$token,(string)($d['confirmacao']??''));break;
                    default:throw new \DomainException('Ação inválida.');
                }
                if($this->request->getHeaderLine('X-Requested-With')==='XMLHttpRequest'){
                    $reply=['ok'=>true,'estado'=>$s->job($actor,$token)->estado];
                    if(in_array($action,['pagina','remover','todas','limpar'],true))$reply['selecionadas']=$s->summary($actor,$token)['selected'];
                    return $this->response->withType('application/json')->withStringBody(json_encode($reply));
                }
                return $this->redirect(['action'=>'index','?'=>['token'=>$token]+$filters+['pagina'=>$this->request->getQuery('pagina',1)]]);
            }
        }catch(\DomainException $e){
            if($this->request->getHeaderLine('X-Requested-With')==='XMLHttpRequest')return $this->response->withStatus(422)->withType('application/json')->withStringBody(json_encode(['error'=>$e->getMessage()]));
            $this->Flash->error($e->getMessage());$paused=true;
        }
        $data=null;
        try{if($token!=='')$data=$s->summary($actor,$token,$filters,(int)$this->request->getQuery('pagina',1));}
        catch(\DomainException $e){$this->Flash->error($e->getMessage());$paused=true;}
        if($data&&$data['job']->estado==='concluido'){
            $flashKey=$sessionKey.'.resultado.'.$token;
            if(!$session->read($flashKey)){$this->Flash->success(($data['counts']['sucesso']??0).' O.S. vinculada(s) ao caderno '.($data['plan']['caderno']??'').'.');$session->write($flashKey,true);}
        }
        $notebooks=$this->fetchTable('Cadernos')->find('list')->where(['entressafra_id'=>PeriodoContexto::id(),'ativo'=>true,'area_curva_id IS NOT'=>null])->orderBy(['nome'=>'ASC'])->all();
        $this->set(compact('data','token','filters','paused','notebooks'));
    }
}
