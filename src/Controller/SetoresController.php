<?php
declare(strict_types=1);
namespace App\Controller;
class SetoresController extends CadastrosController
{
    protected string $cadastro = 'Setores';
    protected string $singular = 'Setor';
    public function index(): void
    {
        if($this->request->getQuery('equipes')||$this->currentUser->perfil==='RESPONSAVEL_SETOR'){parent::index();return;}
        \App\Service\Access::pcm($this->currentUser);
        $areas=$this->paginate((new \App\Service\SetoresApresentacao())->availableAreas((int)\App\Service\PeriodoContexto::id())->orderBy(['nome'=>'ASC']),['limit'=>10,'maxLimit'=>10]);
        $season=(int)\App\Service\PeriodoContexto::id();$service=new \App\Service\SetoresApresentacao();$configurations=[];
        foreach($areas as $area)$configurations[$area->id]=$service->configuration($season,(int)$area->id);
        $this->set(compact('areas','season','configurations'));$this->viewBuilder()->setTemplatePath('Setores');
    }
    public function curva($id=null)
    {
        \App\Service\Access::pcm($this->currentUser);$season=(int)\App\Service\PeriodoContexto::id();$area=$this->fetchTable('AreasFabrica')->get((int)$id);$service=new \App\Service\SetoresApresentacao();
        if($this->request->is('post')){
            try{$service->save((int)$this->currentUser->id,$season,(int)$area->id,$this->request->getData());$this->Flash->success('Datas e cadernos do setor salvos. Classificações principais preservadas.');return $this->redirect(['action'=>'curva',$area->id]);}
            catch(\DomainException $e){$this->Flash->error($e->getMessage());}
        }
        $configuration=$service->configuration($season,(int)$area->id);$books=$this->fetchTable('Cadernos')->find()->where(['entressafra_id'=>$season])->orderBy(['nome'=>'ASC'])->all();
        $this->set(compact('season','area','configuration','books'));$this->viewBuilder()->setTemplatePath('Setores');
    }
    public function novaArea()
    {
        $this->request->allowMethod(['post']);\App\Service\Access::pcm($this->currentUser);$name=trim((string)$this->request->getData('nome'));
        if($name===''||mb_strlen($name)>150||$this->fetchTable('AreasFabrica')->exists(['nome'=>$name,'entressafra_id'=>(int)\App\Service\PeriodoContexto::id()])){$this->Flash->error('Informe um nome único de até 150 caracteres.');return $this->redirect(['action'=>'index']);}
        $t=$this->fetchTable('AreasFabrica');$a=$t->saveOrFail(\App\Service\Access::entity($t,['nome'=>$name,'entressafra_id'=>(int)\App\Service\PeriodoContexto::id()]));\App\Service\Access::audit($this->currentUser,'area_apresentacao_criada',null,null,$a->toArray(),'Cadastro do setor de apresentação.');return $this->redirect(['action'=>'curva',$a->id]);
    }
}
