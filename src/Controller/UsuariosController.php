<?php
declare(strict_types=1);
namespace App\Controller;
use App\Service\Access;
use App\Service\Users;
class UsuariosController extends AppController
{
    public function beforeFilter(\Cake\Event\EventInterface $event)
    { parent::beforeFilter($event); if ($event->isStopped()) { return; } Access::admin($this->currentUser); }
    public function index(): void
    { $this->set('rows', $this->paginate($this->fetchTable('Usuarios')->find()->select(['id','nome','login','perfil','ativo','computador_operacional'])->orderBy(['nome' => 'ASC']),['limit'=>10,'maxLimit'=>10])); }
    public function add() { return $this->form(null); }
    public function edit($id) { return $this->form((int)$id); }
    private function form(?int $id)
    {
        $row = $id ? $this->fetchTable('Usuarios')->get($id) : null;
        $selected = $id ? $this->fetchTable('UsuariosSetores')->find()->where(['usuario_id' => $id])->all()->extract('setor_id')->toList() : [];
        if ($this->request->is('post')) {
            try { (new Users())->save((int)$this->currentUser->id, $this->request->getData(), $id); return $this->redirect(['action' => 'index']); }
            catch (\DomainException $e) { $this->Flash->error($e->getMessage()); }
        }
        $sectors = \App\Service\OsApresentacao::options($this->fetchTable('Setores')->find('list')->orderBy(['nome' => 'ASC'])->all());
        $this->set(compact('row', 'selected', 'sectors')); $this->viewBuilder()->setTemplate('form');
    }
}
