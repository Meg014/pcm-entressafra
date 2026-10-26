<?php
declare(strict_types=1);
namespace App\Controller;
class AuthController extends AppController
{
    public function login()
    {
        $this->request->allowMethod(['get', 'post']);
        if ($this->request->is('post')) {
            $login = strtolower(trim((string)$this->request->getData('login')));
            $password = (string)$this->request->getData('senha');
            $table = $this->fetchTable('Usuarios');
            $user = strlen($login) <= 100 ? $table->find()->where(['login' => $login])->first() : null;
            $hash = $user?->senha_hash ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
            $valid = strlen($password) <= 1024 && password_verify($password, $hash);
            if ($valid && $user?->ativo && (!$user->bloqueado_ate || $user->bloqueado_ate->getTimestamp() <= time())) {
                $session = $this->request->getSession(); $session->renew();
                $session->delete('Periodo');$session->delete('Curva.preview');
                $session->write('Auth', ['id' => $user->id, 'stamp' => hash('sha256', $user->senha_hash), 'last' => time()]);
                $user->falhas_login = 0; $user->bloqueado_ate = null; $table->saveOrFail($user);
                $remember=new \App\Service\ComputerSession();
                $this->setResponse($this->response->withCookie($remember->cookie($this->request)));
                if(\App\Service\Access::operational($user)){
                    try{$this->setResponse($this->response->withCookie($remember->cookie($this->request,$remember->issue($user))));}
                    catch(\Cake\Database\Exception\QueryException $e){$this->log('Sessão permanente do computador indisponível; confira a migration 20261026150000.','error');}
                }
                if(\App\Service\Access::operational($user))return $this->redirect(['controller'=>'Apontamentos','action'=>'add']);
                if(in_array($user->perfil,['ADMIN_PCM','PCM'],true))return $this->redirect(['controller'=>'Periodos','action'=>'index']);
                return $this->redirect(['controller' => 'Dashboard', 'action' => 'index']);
            }
            if ($user) {
                $table->getConnection()->execute('UPDATE usuarios SET falhas_login = falhas_login + 1 WHERE id = ?', [$user->id]);
                $user = $table->get($user->id);
                if ($user->falhas_login >= 5) { $user->bloqueado_ate = gmdate('Y-m-d H:i:s', time() + 900); $table->saveOrFail($user); }
            }
            $this->Flash->error('Login ou senha inválidos, conta inativa ou temporariamente bloqueada.');
        }
    }
    public function logout()
    {
        $this->request->allowMethod(['post']);
        $remember=new \App\Service\ComputerSession();$remember->revoke($this->currentUser);
        $this->setResponse($this->response->withCookie($remember->cookie($this->request)));
        $this->request->getSession()->destroy();
        return $this->redirect(['action' => 'login']);
    }
}
