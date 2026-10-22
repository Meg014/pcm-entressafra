<?php
declare(strict_types=1);
namespace App\Controller;

use App\Service\Access;

class PeriodosController extends AppController
{
    public function index(): void
    {
        Access::pcm($this->currentUser);
        $periodos=$this->fetchTable('Entressafras')->find()->orderBy(['data_inicio'=>'DESC','id'=>'DESC'])->all();
        $this->set(compact('periodos'));
    }
    public function selecionar()
    {
        $this->request->allowMethod(['post']);Access::pcm($this->currentUser);
        $id=$this->request->getData('periodo_id');
        if(!is_scalar($id)||!ctype_digit((string)$id)||(int)$id<1)throw new \Cake\Http\Exception\BadRequestException('Selecione uma entressafra válida.');
        $s=$this->fetchTable('Entressafras')->get((int)$id);
        $this->request->getSession()->write('Periodo',['id'=>(int)$s->id,'usuario_id'=>(int)$this->currentUser->id]);
        // Previews belong to the previously selected context and must be prepared again.
        $this->request->getSession()->delete('Curva.preview');
        return $this->redirect(['controller'=>'Evolucao','action'=>'controle']);
    }
}
