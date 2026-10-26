<?php
declare(strict_types=1);
namespace App\Controller;
use App\Service\Access;
use App\Service\TimeEntries;
use App\Service\ConflictException;

class ApontamentosController extends OperacaoController
{
    private ?\App\Integration\Totvs\GatewayInterface $operationGateway=null;
    protected function totvs(): \App\Integration\Totvs\GatewayInterface { return $this->operationGateway??parent::totvs(); }
    public function ordens(\App\Integration\Totvs\GatewayInterface $gateway)
    {
        $this->request->allowMethod(['get']);
        $term=$this->request->getQuery('q','');
        $status=200;
        if (!is_string($term) || mb_strlen(trim($term))<2 || mb_strlen(trim($term))>60) {
            $status=422; $data=['error'=>'Digite de 2 a 60 caracteres para pesquisar.'];
        } else {
            try {
                $data=(new \App\Service\OrderAutocomplete($gateway))->search($this->currentUser,trim($term),$this->request->getQueryParams());
            } catch(\Cake\Http\Exception\BadRequestException) { $status=422; $data=['error'=>'Filtros inválidos.']; }
            catch(\DomainException | \PDOException | \Cake\Database\Exception\MissingConnectionException) { $status=503; $data=['error'=>'Não foi possível pesquisar as O.S. agora. Tente novamente.']; }
        }
        return $this->response->withStatus($status)->withType('application/json')->withHeader('Cache-Control','no-store, private')->withHeader('X-Content-Type-Options','nosniff')->withStringBody(json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    }
    private function orderResult($activity): array
    {
        return ['id'=>(string)$activity->id,'numero'=>$activity->os_numero,'filial'=>$activity->os_filial,'servico'=>$activity->servico,'bem'=>$activity->bem,'situacao'=>$activity->situacao_totvs,'setor'=>$activity->setor_executante?->nome??'Identificação pendente','caderno'=>$activity->caderno->nome,'entressafra'=>$activity->caderno->entressafra->nome];
    }
    public function funcionarios(\App\Integration\Totvs\GatewayInterface $gateway)
    {
        $this->request->allowMethod(['get']);
        $term = $this->request->getQuery('q', '');
        $status = 200;
        if (!is_string($term) || mb_strlen(trim($term)) < 2 || mb_strlen(trim($term)) > 60) {
            $status = 422; $data = ['error'=>'Digite de 2 a 60 caracteres para pesquisar.'];
        } else {
            try {
                $rows = $gateway->employees($term);
                $visible=array_slice($rows,0,20);
                $warnings=array_values(array_filter(array_column($visible,'bloqueio')));
                $data = ['results'=>$visible, 'has_more'=>count($rows)>20, 'warning'=>$warnings[0]??null];
            } catch (\DomainException $error) { $status=503; $data=['error'=>$error->getMessage()]; }
        }
        return $this->response->withStatus($status)->withType('application/json')->withHeader('Cache-Control','no-store, private')->withHeader('X-Content-Type-Options','nosniff')->withStringBody(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
    public function consulta(): void
    { Access::pcm($this->currentUser); $this->index(); $this->viewBuilder()->setTemplate('index'); }
    public function index(): void
    {
        $query = Access::activityScope($this->fetchTable('Apontamentos')->find()->contain(['Atividades' => ['Entressafras','SetorExecutante','Cadernos' => ['Setores', 'Entressafras']]])->innerJoinWith('Atividades'),$this->currentUser);
        $this->filters($query, 'Apontamentos');
        $employee = $this->request->getQuery('funcionario', '');
        if (!is_string($employee) || mb_strlen($employee) > 150) { throw new \Cake\Http\Exception\BadRequestException('Funcionário inválido.'); }
        if ($employee !== '') { $query->where(['OR' => ['funcionario_nome LIKE' => '%' . $employee . '%', 'funcionario_matricula LIKE' => '%' . $employee . '%']]); }
        foreach (['data_inicio' => 'fim >', 'data_fim' => 'inicio <'] as $field => $comparison) {
            $date = $this->request->getQuery($field);
            if ($date !== null && $date !== '') {
                if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || !checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))) { throw new \Cake\Http\Exception\BadRequestException('Data de filtro inválida.'); }
                $dt = new \DateTimeImmutable($date, new \DateTimeZone('America/Sao_Paulo'));
                if ($field === 'data_fim') { $dt = $dt->modify('+1 day'); }
                $query->where(['Apontamentos.' . $comparison => $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')]);
            }
        }
        $this->set('rows', $this->paginate($query->orderBy(['Apontamentos.inicio' => 'DESC']), ['limit' => 10, 'maxLimit' => 10])); $this->options();
    }
    public function add(\App\Integration\Totvs\GatewayInterface $gateway) { $this->operationGateway=$gateway;return $this->form(null); }
    public function edit($id,\App\Integration\Totvs\GatewayInterface $gateway) { $this->operationGateway=$gateway;Access::pcm($this->currentUser); return $this->form((int)$id); }
    private function form(?int $id)
    {
        $row = $id ? $this->fetchTable('Apontamentos')->get($id) : null;
        if ($row) { $this->activity((int)$row->atividade_id); }
        $conflicts = []; $batchErrors = [];
        if ($this->request->is('post')) {
            try {
                if (!$id && $this->request->getData('apontamentos') !== null) {
                    $ids = (new TimeEntries($this->totvs()))->saveBatch((int)$this->currentUser->id, $this->request->getData());
                    $this->Flash->success(count($ids).' apontamentos registrados com sucesso.');
                    return $this->redirect(['action'=>'add']);
                }
                $saved = (new TimeEntries($this->totvs()))->save((int)$this->currentUser->id, $this->request->getData(), $id);
                if(!$id && (\App\Service\Access::operational($this->currentUser)||$this->request->getData('continuar')==='1')) { $this->Flash->success('Apontamento #'.$saved.' recebido pelo PCM. Você pode iniciar outro lançamento.');return $this->redirect(['action'=>'add']); }
                return $this->redirect(['action' => 'view', $saved]);
            } catch (ConflictException $e) { $conflicts = $e->conflicts; $this->Flash->error($e->getMessage()); }
            catch (\App\Service\BatchEntryException $e) { $batchErrors=$e->errors; $this->Flash->error($e->getMessage()); }
            catch (\DomainException $e) { $this->Flash->error($e->getMessage()); }
        }
        $selectedOrder=null;
        $selectedId=$this->request->getData('atividade_id') ?? $this->request->getQuery('atividade_id');
        if (!$row && is_scalar($selectedId) && ctype_digit((string)$selectedId) && (int)$selectedId>0) {
            $selected=$this->activitiesQuery()->where(['Atividades.id'=>(int)$selectedId,'Atividades.disponivel_totvs'=>true])->first();
            if ($selected) { try{ $selectedOrder=(new \App\Service\OperacaoRapida($this->totvs()))->result((new \App\Service\OperacaoRapida())->activity($this->currentUser,(int)$selected->id)); }catch(\DomainException){} }
        }
        $selectedEmployee=null;
        $employeeKey=$this->request->getData('funcionario_chave');
        if (!$row && is_string($employeeKey) && $employeeKey!=='') {
            try {
                $person=$this->totvs()->employee($employeeKey);
                if (($person['ativo']??false)===true && ($person['chave']??null)===$employeeKey) { $selectedEmployee=$person; }
            } catch (\DomainException) { /* Require a fresh selection if identity cannot be confirmed. */ }
        }
        $this->set(compact('row', 'conflicts', 'selectedOrder', 'selectedEmployee', 'batchErrors')); $this->options(); $this->viewBuilder()->setTemplate(($id || ($this->request->is('post') && $this->request->getData('apontamentos') === null)) ? 'form' : 'batch');
    }
    public function view($id): void
    {
        $row = $this->fetchTable('Apontamentos')->get($id); $activity = $this->activity((int)$row->atividade_id);
        $audits = $this->fetchTable('Auditorias')->find()->where(['apontamento_id' => $row->id])->orderBy(['id' => 'ASC'])->all();
        $this->set(compact('row', 'activity', 'audits'));
    }
    public function change($id)
    {
        $this->request->allowMethod(['post']); Access::pcm($this->currentUser);
        try { if($this->request->getData('acao')==='lancado' && $this->request->getData('confirmar')!=='1') { throw new \DomainException('Confirme explicitamente o lançamento manual no TOTVS.'); } (new TimeEntries($this->totvs()))->change((int)$this->currentUser->id, (int)$id, (string)$this->request->getData('acao'), (string)$this->request->getData('motivo')); $this->Flash->success('Operação registrada no histórico.'); }
        catch (\DomainException $e) { $this->Flash->error($e->getMessage()); }
        return $this->redirect(['action' => 'view', $id]);
    }
}
