<?php
declare(strict_types=1);
namespace App\Controller;
class CadernosController extends CadastrosController
{
    protected string $cadastro = 'Cadernos';
    protected string $singular = 'Caderno';
    public function index(): void
    {
        try{parent::index();}
        catch(\Cake\Http\Exception\NotFoundException $error){
            if(!($error->getPrevious() instanceof \Cake\Datasource\Paging\Exception\PageOutOfBoundsException))throw $error;
            $query=$this->request->getQueryParams();unset($query['page']);
            $this->redirect(['action'=>'index','?'=>$query]);return;
        }
        \App\Service\NavegacaoCadernos::remember($this->request);
    }
    protected function options(): void
    {
        parent::options();
        $this->set('areasPrincipais',(new \App\Service\SetoresApresentacao())->availableAreas((int)\App\Service\PeriodoContexto::id())->find('list')->orderBy(['nome'=>'ASC'])->all());
    }
    protected function saveForm($registro,bool $editing)
    {
        $this->request->allowMethod($editing?['get','post','put','patch']:['get','post']);
        if(!$this->request->is('get')){
            $table=$this->fetchTable('Cadernos');
            $data=array_intersect_key($this->request->getData(),array_flip(['nome','area_curva_id']));
            $data['entressafra_id']=(int)\App\Service\PeriodoContexto::id();
            if(!$editing)$data['ativo']=true;
            $registro=$table->patchEntity($registro,$data);
            if(!$registro->area_curva_id)$registro->setError('area_curva_id','Selecione o setor principal da Curva S.');
            if($table->save($registro)){
                $this->Flash->success('Caderno salvo. Equipes executantes serão identificadas pelo TOTVS ao vincular as O.S.');
                return $this->redirect(\App\Service\NavegacaoCadernos::target($this->request));
            }
            $this->Flash->error('Confira o nome e o setor principal da Curva S.');
        }
        $this->options();$this->set(compact('registro','editing'));$this->viewBuilder()->setTemplate('form');
    }
    public function view($id=null): void
    {
        parent::view($id);
        $book=$this->fetchTable('Cadernos')->get($id);
        $this->set('areaCurvaNome',$book->area_curva_id?$this->fetchTable('AreasFabrica')->get($book->area_curva_id)->nome:'Pendente de definição administrativa');
    }
}
