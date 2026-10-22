<?php
declare(strict_types=1);
namespace App\Controller;

use Cake\Http\Exception\BadRequestException;

class DashboardController extends AppController
{
    public function index(): void
    {
        $table = $this->fetchTable('Entressafras');
        $entressafras = $table->find('list')->orderBy(['data_inicio' => 'DESC'])->all();
        $ativas = $table->find()->where(['status' => 'ativa'])->orderBy(['data_inicio' => 'DESC'])->all();
        $id = $this->request->getQuery('entressafra_id');
        if ($id !== null && $id !== '' && (!is_scalar($id) || !ctype_digit((string)$id) || (int)$id < 1)) {
            throw new BadRequestException('Selecione uma entressafra válida.');
        }
        $selecionada = $id ? $table->get((int)$id) : ($ativas->first() ?? $table->find()->orderBy(['data_inicio' => 'DESC'])->first());
        $ids = \App\Service\Access::sectors($this->currentUser) ?: [-1];
        $totalSetores = $this->fetchTable('Setores')->find()->where(['id IN' => $ids])->count();
        $totalCadernos = $this->fetchTable('Cadernos')->find()->where(['id IN' => \App\Service\Access::notebookIds($ids)])->count();
        $cadernosSelecionados = $selecionada ? $this->fetchTable('Cadernos')->find()->where(['entressafra_id' => $selecionada->id, 'id IN' => \App\Service\Access::notebookIds($ids)])->count() : 0;
        $this->set(compact('entressafras', 'ativas', 'selecionada', 'totalSetores', 'totalCadernos', 'cadernosSelecionados'));
    }
}
