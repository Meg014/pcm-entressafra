<?php
declare(strict_types=1);
namespace App\Controller;
use App\Service\Access;
use App\Integration\Totvs\Gateway;
use App\Integration\Totvs\GatewayInterface;

abstract class OperacaoController extends AppController
{
    protected function totvs(): GatewayInterface { return new Gateway(); }
    protected function options(): void
    {
        $ids = Access::sectors($this->currentUser) ?: [-1];
        $this->set('sectors', \App\Service\OsApresentacao::options($this->fetchTable('Setores')->find('list')->where(['id IN' => $ids])->orderBy(['nome' => 'ASC'])->all()));
        $this->set('notebooks', $this->fetchTable('Cadernos')->find('list')->where(['Cadernos.id IN' => Access::notebookIds($ids)])->orderBy(['nome' => 'ASC'])->all());
        $this->set('seasons', $this->fetchTable('Entressafras')->find('list')->orderBy(['nome' => 'DESC'])->all());
    }
    protected function activitiesQuery()
    {
        return Access::activityScope($this->fetchTable('Atividades')->find()->contain(['Entressafras','AreasFabrica','SetorExecutante','Cadernos' => ['Setores', 'Entressafras']]),$this->currentUser);
    }
    protected function activity(int $id)
    {
        $activity = $this->fetchTable('Atividades')->get($id, contain: ['Entressafras','AreasFabrica','SetorExecutante','Cadernos' => ['Setores', 'Entressafras']]);
        Access::activity($this->currentUser,$activity);
        return $activity;
    }
    protected function filters($query, string $alias): void
    {
        foreach (['entressafra_id', 'setor_id', 'caderno_id'] as $key) {
            $value = $this->request->getQuery($key);
            if ($value !== null && $value !== '') {
                if (!is_string($value) || !ctype_digit($value) || (int)$value < 1) { throw new \Cake\Http\Exception\BadRequestException('Filtro inválido.'); }
                $query->where([$key==='setor_id'?'Atividades.setor_executante_id':'Atividades.' . $key => (int)$value]);
            }
        }
        $number = $this->request->getQuery('os', '');
        if (!is_string($number) || strlen($number) > 100) { throw new \Cake\Http\Exception\BadRequestException('O.S. inválida.'); }
        if ($number !== '') { $query->where(['Atividades.os_numero LIKE' => '%' . $number . '%']); }
    }
}
