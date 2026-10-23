<?php
declare(strict_types=1);
namespace App\Controller;

use App\Service\{Access,ClassificacaoCurva,ListLinks};

class OrganizacaoController extends OperacaoController
{
    private function season(): int
    {
        $id=(int)$this->request->getQuery('entressafra_id',0);$t=$this->fetchTable('Entressafras');
        return $id?: (int)(($t->find()->where(['status'=>'ativa'])->first()??$t->find()->orderBy(['id'=>'DESC'])->first())?->id??0);
    }
    public function index()
    {
        Access::pcm($this->currentUser);$season=$this->season();$book=(int)$this->request->getQuery('caderno_id',0);$service=new ClassificacaoCurva();
        if($this->request->is('post')){
            try{
                $d=$this->request->getData();
                if(($d['acao']??'')==='nova_area') {
                    $name=trim((string)($d['nome']??''));if($name===''||mb_strlen($name)>150)throw new \DomainException('Informe um nome de área válido.');
                    $t=$this->fetchTable('AreasFabrica');if($t->exists(['nome'=>$name,'entressafra_id'=>(int)\App\Service\PeriodoContexto::id()]))throw new \DomainException('Esta área já existe.');
                    $area=$t->saveOrFail(Access::entity($t,['nome'=>$name,'entressafra_id'=>(int)\App\Service\PeriodoContexto::id()]));Access::audit($this->currentUser,'area_apresentacao_criada',null,null,$area->toArray(),'Cadastro administrativo.');
                    $this->Flash->success('Área cadastrada. Confira a classificação principal dos cadernos.');return $this->redirect($this->request->getRequestTarget());
                }
                $type=($d['tipo']??'')==='caderno'?'caderno':'atividades';
                $ids=$type==='caderno'?[$book]:(array)($d['ids']??[]);
                $plan=$service->preview((int)$this->currentUser->id,$season,$type,$ids,!empty($d['area_curva_id'])?(int)$d['area_curva_id']:null,(string)($d['justificativa']??''));
                $this->request->getSession()->write('Curva.preview',$plan);
                return $this->redirect(['action'=>'previa']);
            }catch(\DomainException $e){$this->Flash->error($e->getMessage());}
        }
        $q=$this->fetchTable('Atividades')->find()->select(['id','os_numero','servico','bem','caderno_id','setor_executante_id','area_curva_id'])->where(['Atividades.entressafra_id'=>$season])->contain(['Cadernos','SetorExecutante'])->orderBy(['Atividades.os_numero'=>'ASC']);
        if($book)$q->where(['Atividades.caderno_id'=>$book]);
        if($this->request->getQuery('pendentes'))$q->leftJoin(['bc'=>'cadernos'],'bc.id=Atividades.caderno_id')->where(['Atividades.area_curva_id IS'=>null,'bc.area_curva_id IS'=>null]);
        $rows=$this->paginate($q,['limit'=>10,'maxLimit'=>10]);
        $notebook=$book?$this->fetchTable('Cadernos')->get($book):null;
        if($notebook&&(int)$notebook->entressafra_id!==$season)throw new \Cake\Http\Exception\BadRequestException('Caderno de outra entressafra.');
        $areas=$this->fetchTable('AreasFabrica')->find('list')->orderBy(['nome'=>'ASC'])->all()->toArray();
        $this->options();$this->set(compact('season','book','rows','notebook','areas'));
    }
    public function previa()
    {
        Access::pcm($this->currentUser);$plan=$this->request->getSession()->read('Curva.preview');
        if(!$plan){$this->Flash->error('Prepare uma prévia administrativa.');return $this->redirect(['action'=>'index']);}
        if($this->request->is('post')) {
            try{(new ClassificacaoCurva())->apply((int)$this->currentUser->id,$plan);$this->request->getSession()->delete('Curva.preview');$this->Flash->success('Classificação principal atualizada. Equipes e históricos preservados.');return $this->redirect(['action'=>'index','?'=>['entressafra_id'=>$plan['season']]]);}
            catch(\DomainException $e){$this->Flash->error($e->getMessage());}
        }
        $page=max(1,(int)$this->request->getQuery('pagina',1));$total=count($plan['rows']);$pages=max(1,(int)ceil($total/10));$page=min($page,$pages);$rows=array_slice($plan['rows'],($page-1)*10,10);
        $areas=$this->fetchTable('AreasFabrica')->find('list')->all()->toArray();$this->set(compact('plan','page','pages','total','rows','areas'));
    }
    public function lista($id=null)
    {
        $transferMode=$this->request->getParam('action')==='transferencia';
        Access::pcm($this->currentUser);$book=$this->fetchTable('Cadernos')->get((int)$id,contain:['Entressafras']);$s=new ListLinks();$token=(string)$this->request->getQuery('token','');
        try {
            if($this->request->is('post')) {
                $d=$this->request->getData();$action=(string)($d['acao']??'criar');
                if($action==='criar')$token=$s->create((int)$this->currentUser->id,(int)$book->id,(string)($d['numeros']??''));
                else {
                    $job=$s->job((int)$this->currentUser->id,$token);
                    if((int)$job->caderno_id!==(int)$book->id)throw new \DomainException('Prévia de outro caderno.');
                    if(!$transferMode&&in_array($action,['selecionar','todas','limpar'],true))throw new \DomainException('A vinculação simples confirma todas as O.S. elegíveis. Transferências são administrativas.');
                    switch($action){
                        case 'preparar':$s->prepare((int)$this->currentUser->id,$token);break;
                        case 'selecionar':$s->select((int)$this->currentUser->id,$token,(array)($d['ids']??[]));if(($d['transferir']??'')==='1')$s->select((int)$this->currentUser->id,$token,(array)($d['ids']??[]),false,true);break;
                        case 'todas':$s->select((int)$this->currentUser->id,$token,[],true);break;
                        case 'limpar':$s->clear((int)$this->currentUser->id,$token);break;
                        case 'confirmar':if($transferMode)$s->start((int)$this->currentUser->id,$token,(int)($d['quantidade']??0),(string)($d['justificativa']??''));else $s->confirmEligible((int)$this->currentUser->id,$token);break;
                        case 'processar':$s->process((int)$this->currentUser->id,$token);break;
                        default:throw new \DomainException('Ação inválida.');
                    }
                }
                return $this->redirect(['action'=>$transferMode?'transferencia':'lista',$book->id,'?'=>['token'=>$token,'pagina'=>(int)$this->request->getQuery('pagina',1)]]);
            }
        }catch(\DomainException $e){$this->Flash->error($e->getMessage());$paused=true;}
        $data=null;
        try{$data=$token!==''?$s->summary((int)$this->currentUser->id,$token,(int)$this->request->getQuery('pagina',1)):null;}
        catch(\DomainException $e){$this->Flash->error($e->getMessage());$paused=true;}
        if($data&&(int)$data['job']->caderno_id!==(int)$book->id)throw new \Cake\Http\Exception\BadRequestException('Prévia de outro caderno.');
        $listTarget=\App\Service\NavegacaoCadernos::target($this->request);
        if(!$transferMode&&$data&&$data['job']->estado==='concluido'){
            $counts=$data['counts'];$pending=0;
            foreach(['outro','nao_encontrada','inelegivel','ambigua','erro'] as $state)$pending+=$counts[$state]??0;
            if(!$pending){
                $this->Flash->success(($counts['sucesso']??0).' O.S. vinculada(s) ao caderno '. $book->nome.'.');
                return $this->redirect($listTarget);
            }
            $this->set('resultadoVinculos',($counts['sucesso']??0).' O.S. vinculada(s). '.$pending.' pendência(s) para revisão. Os resultados e os números informados permanecem salvos.');
        }
        $this->set(compact('listTarget'));
        $recent=[];
        foreach($this->fetchTable('VinculosLotes')->find()->where(['usuario_id'=>$this->currentUser->id,'caderno_id'=>$book->id])->orderBy(['id'=>'DESC'])->limit(20)->all() as $saved){
            $filters=json_decode($saved->filtros,true);
            if(($filters['tipo']??'')==='lista')$recent[]=['token'=>$saved->token,'estado'=>$saved->estado,'quantidade'=>$filters['unicas'],'created'=>$saved->created];
        }
        $areaCurva=$book->area_curva_id?$this->fetchTable('AreasFabrica')->get($book->area_curva_id)->nome:'Pendente de confirmação administrativa';$paused=$paused??false;$this->set(compact('book','token','data','paused','areaCurva','recent'));
    }
    public function transferencia($id=null)
    {
        $result=$this->lista($id);
        $this->viewBuilder()->setTemplate('transferencia');
        return $result;
    }
}
