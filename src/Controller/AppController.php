<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use Cake\Controller\Controller;

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @link https://book.cakephp.org/5/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{
    protected $currentUser = null;

    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        \App\Service\PeriodoContexto::set(null);
        parent::beforeFilter($event);
        if ($this->request->getParam('controller') === 'Error' || ($this->request->getParam('controller') === 'Auth' && $this->request->getParam('action') === 'login')) { return; }
        $session = $this->request->getSession();
        $id = (int)$session->read('Auth.id');
        $user = $id ? $this->fetchTable('Usuarios')->find()->where(['id' => $id, 'ativo' => true])->first() : null;
        $remember=new \App\Service\ComputerSession();
        $credential=$this->request->getCookie(\App\Service\ComputerSession::COOKIE);
        $valid=$user&&hash_equals(hash('sha256',$user->senha_hash),(string)$session->read('Auth.stamp'));
        if(!$valid||(!\App\Service\Access::operational($user)&&time()-(int)$session->read('Auth.last')>1800)){
            $session->delete('Auth');
            $user=$remember->restore($credential);
            if($user){$session->renew();$session->write('Auth',['id'=>$user->id,'stamp'=>hash('sha256',$user->senha_hash),'last'=>time()]);}
        }
        if (!$user) {
            $session->delete('Auth');
            if($credential!==null)$this->setResponse($this->response->withCookie($remember->cookie($this->request)));
            $event->setResult($this->redirect(['controller' => 'Auth', 'action' => 'login']));
            $event->stopPropagation();
            return;
        }
        $session->write('Auth.last', time());
        if(\App\Service\Access::operational($user)&&!($this->request->getParam('controller')==='Auth'&&$this->request->getParam('action')==='logout')){
            try{
                $value=$remember->matches($user,$credential)?$credential:$remember->issue($user);
                $this->setResponse($this->response->withCookie($remember->cookie($this->request,$value)));
            }catch(\Cake\Database\Exception\QueryException $e){$this->log('Sessão permanente do computador indisponível; confira a migration 20261026150000.','error');}
        }
        $this->currentUser = $user;
        $this->set('currentUser', $user);
        if(!$this->periodo($event))return;
        if (\App\Service\Access::operational($user)) {
            $controller=$this->request->getParam('controller');$action=$this->request->getParam('action');
            if($controller==='Dashboard') { $event->setResult($this->redirect(['controller'=>'Apontamentos','action'=>'add']));$event->stopPropagation();return; }
            $allowed=['Insumos'=>['add','index'],'Apontamentos'=>['add','index','view','funcionarios','ordens','servicos'],'Operacional'=>['ordens','identificar','porcentagem'],'Evolucao'=>['historico'],'Auth'=>['logout']];
            if(!in_array($action,$allowed[$controller]??[],true)) { throw new \Cake\Http\Exception\ForbiddenException('Conta operacional: acesso limitado a lançamentos e históricos autorizados.'); }
        }
    }
    private function periodo(\Cake\Event\EventInterface $event): bool
    {
        $controller=$this->request->getParam('controller');$action=$this->request->getParam('action');
        if(in_array($controller,['Auth','Periodos'],true))return true;
        $session=$this->request->getSession();$administrative=in_array($this->currentUser->perfil,['ADMIN_PCM','PCM'],true);
        if($administrative) {
            $id=(int)$session->read('Periodo.id');
            if((int)$session->read('Periodo.usuario_id')!==(int)$this->currentUser->id)$id=0;
            $period=$id?$this->fetchTable('Entressafras')->find()->where(['id'=>$id])->first():null;
            if(!$period) {
                $session->delete('Periodo');
                $session->delete('Curva.preview');
                if($controller==='Entressafras'&&in_array($action,['index','add'],true))return true;
                $event->setResult($this->redirect(['controller'=>'Periodos','action'=>'index']));$event->stopPropagation();return false;
            }
        } else {
            $periods=$this->fetchTable('Entressafras')->find()->where(['status'=>'ativa'])->all();
            if($periods->count()!==1)throw new \Cake\Http\Exception\ConflictException('O PCM deve configurar exatamente uma entressafra operacional ativa.');
            $period=$periods->first();
        }
        $id=(int)$period->id;\App\Service\PeriodoContexto::set($id);
        $this->set(['periodoSelecionado'=>$period,'periodoEncerrado'=>$period->status==='encerrada']);
        // Validate supplied values before filling the implicit context.
        $validate=function(array $data)use(&$validate,$id): void {
            foreach($data as $key=>$value){
                if(is_array($value)){if($key==='entressafra_id')throw new \Cake\Http\Exception\BadRequestException('Período inválido.');$validate($value);continue;}
                if($value===null||$value==='')continue;
                if($key==='entressafra_id') {
                    if(!is_scalar($value)||!ctype_digit((string)$value)||(int)$value!==$id)throw new \Cake\Http\Exception\ForbiddenException('Entressafra diferente da sessão. Use Trocar entressafra.');
                }
                if(in_array($key,['caderno_id','caderno_destino','atividade_id','calendario_id','pesos','metas'],true)) {
                    if(!is_scalar($value)||!ctype_digit((string)$value)||(int)$value<1)throw new \Cake\Http\Exception\BadRequestException('Identificador inválido.');
                    if($key==='atividade_id'){
                        try{\App\Service\PeriodoContexto::activity((int)$value);}
                        catch(\Cake\Datasource\Exception\RecordNotFoundException $e){if(!($this->request->getParam('controller')==='Apontamentos'&&$this->request->getParam('action')==='add'&&$this->request->is('post')))throw $e;}
                    }
                    elseif(in_array($key,['caderno_id','caderno_destino'],true))\App\Service\PeriodoContexto::notebook((int)$value);
                    else {$alias=$key==='calendario_id'?'CalendariosOperacionais':'EvolucaoVersoes';$row=$this->fetchTable($alias)->get((int)$value);\App\Service\PeriodoContexto::season((int)$row->entressafra_id);}
                }
            }
        };
        $query=$this->request->getQueryParams();$data=(array)$this->request->getData();
        // Creating a new period is global administration; it does not select that period.
        if(!($controller==='Entressafras'&&$action==='add')){$validate($query);$validate($data);}
        $pathId=(int)$this->request->getParam('pass.0',0);
        if($pathId) {
            if(in_array($controller,['Atividades','FilaPcm'],true)||($controller==='Evolucao'&&$action==='historico'))\App\Service\PeriodoContexto::activity($pathId);
            elseif($controller==='Cadernos'||($controller==='Organizacao'&&in_array($action,['lista','transferencia'],true)))\App\Service\PeriodoContexto::notebook($pathId);
            elseif($controller==='Apontamentos'){$entry=$this->fetchTable('Apontamentos')->get($pathId);\App\Service\PeriodoContexto::activity((int)$entry->atividade_id);}
            elseif($controller==='Entressafras')\App\Service\PeriodoContexto::season($pathId);
        }
        if(!$this->request->is('get')&&!$this->request->is('head')&&!($controller==='Entressafras'&&$action==='add')&&$controller!=='Usuarios')\App\Service\PeriodoContexto::writable($id);
        $query['entressafra_id']=(string)$id;
        if(!($controller==='Entressafras'&&$action==='add'))$this->setRequest($this->request->withQueryParams($query)->withData('entressafra_id',(string)$id));
        return true;
    }
    public function afterFilter(\Cake\Event\EventInterface $event)
    {
        parent::afterFilter($event);
        \App\Service\PeriodoContexto::set(null);
    }
    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('FormProtection');`
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Flash');
        $this->viewBuilder()->setLayout('pcm');

        /*
         * Enable the following component for recommended CakePHP form protection settings.
         * see https://book.cakephp.org/5/en/controllers/components/form-protection.html
         */
        //$this->loadComponent('FormProtection');
    }
}
