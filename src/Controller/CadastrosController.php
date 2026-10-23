<?php
declare(strict_types=1);
namespace App\Controller;

use Cake\Http\Exception\BadRequestException;

abstract class CadastrosController extends AppController
{
    protected string $cadastro;
    protected string $singular;

    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);
        if ($event->isStopped()) { return; }
        if (in_array($this->request->getParam('action'), ['add', 'edit'], true)) { \App\Service\Access::pcm($this->currentUser); }
    }

    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setTemplatePath('Cadastros');
        $this->set(['cadastro' => $this->cadastro, 'singular' => $this->singular]);
    }

    public function index(): void
    {
        $table = $this->fetchTable($this->cadastro);
        $query = $table->find()->orderBy([$this->cadastro . '.nome' => 'ASC']);
        if ($this->currentUser->perfil === 'RESPONSAVEL_SETOR' && in_array($this->cadastro, ['Setores', 'Cadernos'], true)) {
            $query->where([$this->cadastro.'.id IN' => $this->cadastro==='Setores'?(\App\Service\Access::sectors($this->currentUser)?:[-1]):\App\Service\Access::notebookIds(\App\Service\Access::sectors($this->currentUser))]);
        }
        $search = $this->request->getQuery('q', '');
        if (!is_string($search) || mb_strlen($search) > 150) {
            throw new BadRequestException('Pesquisa inválida.');
        }
        if (trim($search) !== '') {
            $query->where([$this->cadastro . '.nome LIKE' => '%' . trim($search) . '%']);
        }
        if ($this->cadastro === 'Cadernos') {
            $query->contain(['Entressafras', 'Setores','SetorPrincipal']);
            $principal=$this->request->getQuery('area_curva_id');
            if($principal!==null&&$principal!==''){
                if(!is_scalar($principal)||!ctype_digit((string)$principal)||(int)$principal<1)throw new BadRequestException('Setor principal inválido.');
                $query->where(['Cadernos.area_curva_id'=>(int)$principal]);
            }
            if ($this->currentUser->perfil==='RESPONSAVEL_SETOR') { $query->contain(['Setores'=>fn($q)=>$q->where(['Setores.id IN'=>\App\Service\Access::sectors($this->currentUser)?:[-1]])]); }
            foreach (['entressafra_id', 'setor_id'] as $field) {
                $id = $this->request->getQuery($field);
                if ($id !== null && $id !== '') {
                    if (!is_scalar($id) || !ctype_digit((string)$id) || (int)$id < 1) {
                        throw new BadRequestException('Filtro inválido.');
                    }
                    $query->where([$field==='setor_id'?'Cadernos.id IN':'Cadernos.entressafra_id'=>$field==='setor_id'?\App\Service\Access::notebookIds([(int)$id]):(int)$id]);
                }
            }
            $this->options();
        }
        $registros = $this->paginate($query, ['limit' => 10, 'maxLimit' => 10, 'sortableFields' => ['nome', 'created']]);
        $this->set(compact('registros', 'search'));
    }

    public function view($id = null): void
    {
        $table = $this->fetchTable($this->cadastro);
        $registro = $table->get($id, contain: $this->cadastro === 'Cadernos' ? ['Entressafras', 'Setores'] : []);
        if ($this->cadastro==='Cadernos' && $this->currentUser->perfil==='RESPONSAVEL_SETOR') { $registro->setores=array_values(array_filter($registro->setores,fn($s)=>in_array((int)$s->id,array_map('intval',\App\Service\Access::sectors($this->currentUser)),true))); }
        if ($this->cadastro==='Cadernos') { \App\Service\Access::notebook($this->currentUser,(int)$registro->id); }
        if ($this->cadastro==='Setores') { \App\Service\Access::sector($this->currentUser,(int)$registro->id); }
        $totalCadernos = $this->cadastro === 'Cadernos' ? null : $this->fetchTable('Cadernos')->find()->where([
            $this->cadastro === 'Entressafras' ? 'entressafra_id' : 'Cadernos.id IN' => $this->cadastro==='Entressafras'?$registro->id:\App\Service\Access::notebookIds([(int)$registro->id]),
        ])->count();
        if ($this->cadastro === 'Entressafras' && $this->currentUser->perfil === 'RESPONSAVEL_SETOR') { $totalCadernos = $this->fetchTable('Cadernos')->find()->where(['entressafra_id' => $registro->id, 'id IN' => \App\Service\Access::notebookIds(\App\Service\Access::sectors($this->currentUser))])->count(); }
        $this->set(compact('registro', 'totalCadernos'));
    }

    public function add()
    {
        $table = $this->fetchTable($this->cadastro);
        $registro = $table->newEmptyEntity();
        if ($this->cadastro === 'Entressafras') {
            $registro->status = 'planejamento';
        } else {
            $registro->ativo = true;
        }
        return $this->saveForm($registro, false);
    }

    public function edit($id = null)
    {
        $registro = $this->fetchTable($this->cadastro)->get($id,contain:$this->cadastro==='Cadernos'?['Setores']:[]);
        return $this->saveForm($registro, true);
    }

    protected function saveForm($registro, bool $editing)
    {
        $this->request->allowMethod($editing ? ['get', 'post', 'put', 'patch'] : ['get', 'post']);
        if (!$this->request->is('get')) {
            $table = $this->fetchTable($this->cadastro);
            $registro = $table->patchEntity($registro, $this->request->getData());
            if ($table->save($registro)) {
                $this->Flash->success('Cadastro salvo com sucesso.');
                if($this->cadastro==='Entressafras'&&!$editing)return $this->redirect(['controller'=>'Periodos','action'=>'index']);
                return $this->redirect(['action' => 'view', $registro->id]);
            }
            $this->Flash->error('Não foi possível salvar. Confira os campos indicados.');
        }
        $this->options();
        $this->set(compact('registro', 'editing'));
        $this->viewBuilder()->setTemplate('form');
    }

    protected function options(): void
    {
        if ($this->cadastro === 'Cadernos') {
            $this->set('entressafras', $this->fetchTable('Entressafras')->find('list')->orderBy(['nome' => 'DESC'])->all());
            $this->set('setores', \App\Service\OsApresentacao::options($this->fetchTable('Setores')->find('list')->where(['id IN' => \App\Service\Access::sectors($this->currentUser) ?: [-1]])->orderBy(['nome' => 'ASC'])->all()));
        }
    }
}
