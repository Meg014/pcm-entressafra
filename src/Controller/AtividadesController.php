<?php
declare(strict_types=1);
namespace App\Controller;
use App\Service\Activities;
class AtividadesController extends OperacaoController
{
    public function index(): void
    {
        $query = $this->activitiesQuery(); $this->filters($query, 'Atividades');
        $rows=$this->paginate($query->orderBy(['Atividades.id' => 'DESC']), ['limit' => 10, 'maxLimit' => 10]);$ids=array_map(fn($a)=>(int)$a->id,$rows->items()->toList());
        $metrics=(new \App\Service\FilaPcm())->metrics($ids);$progress=[];$today=(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        foreach($ids as $id){$progress[$id]=(new \App\Service\Evolucao())->state($id,$today)['percentual'];}
        $this->set(compact('rows','metrics','progress')); $this->options();
    }
    public function add()
    {
        $matches = [];
        if ($this->request->is('post')) {
            try {
                $notebookId=(int)$this->request->getData('caderno_id');
                $seasonId=(int)$this->request->getData('entressafra_id');
                if (!$seasonId) { throw new \DomainException('Selecione a entressafra de destino.'); }
                if ($notebookId && $seasonId && (int)$this->fetchTable('Cadernos')->get($notebookId)->entressafra_id!==$seasonId) { throw new \DomainException('O caderno não pertence à entressafra selecionada.'); }
                if ($this->request->getData('acao') === 'pesquisar') {
                    $search = trim((string)$this->request->getData('pesquisa'));
                    if (mb_strlen($search) < 2 || mb_strlen($search) > 60) { throw new \DomainException('Informe de 2 a 60 caracteres para pesquisar a O.S.'); }
                    $sectorId=(int)$this->request->getData('setor_id');
                    $notebookId=(int)$this->request->getData('caderno_id');
                    if (!$notebookId) { throw new \DomainException('Selecione o caderno de destino.'); }
                    $matches=(new Activities($this->totvs()))->searchNotebook((int)$this->currentUser->id,$notebookId,$search,$sectorId?:null);
                } else {
                    $id = (new Activities($this->totvs()))->link((int)$this->currentUser->id, (int)$this->request->getData('caderno_id'), (string)$this->request->getData('os_chave'));
                    return $this->redirect(['action' => 'view', $id]);
                }
            } catch (\DomainException $e) { $this->Flash->error($e->getMessage()); }
        }
        $this->set(compact('matches')); $this->options();
    }
    public function sync($id)
    {
        $this->request->allowMethod(['post']); \App\Service\Access::pcm($this->currentUser);
        try { (new Activities($this->totvs()))->synchronize((int)$this->currentUser->id,(int)$id); $this->Flash->success('Situação consultada no TOTVS. Vínculo, histórico e avanço físico preservados.'); }
        catch(\DomainException $error) { $this->Flash->error($error->getMessage()); }
        return $this->redirect(['action'=>'view',$id]);
    }
    public function view($id): void
    {
        $activity = $this->activity((int)$id);
        $rows = $this->fetchTable('Apontamentos')->find()->where(['atividade_id' => $activity->id])->orderBy(['inicio' => 'ASC'])->all();
        $totals = []; $total = 0;
        foreach ($rows as $row) { if (!$row->cancelado) { $key = $row->funcionario_chave; $totals[$key] ??= ['nome' => $row->funcionario_nome, 'matricula' => $row->funcionario_matricula, 'minutos' => 0]; $totals[$key]['minutos'] += $row->minutos; $total += $row->minutos; } }
        $metrics=(new \App\Service\FilaPcm())->metrics([(int)$id]);$progress=(new \App\Service\Evolucao())->state((int)$id,(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d'));
        $areaId=$activity->area_curva_id??$activity->caderno?->area_curva_id;
        $areaCurva=$areaId?$this->fetchTable('AreasFabrica')->get($areaId)->nome:'Pendente de classificação administrativa';
        $historyPage=(new \App\Service\Curva())->historyPage((int)$id,max(1,(int)$this->request->getQuery('pagina',1)));
        $notes=null;
        if($this->request->getQuery('consultar_descricao')==='1') {
            \App\Service\Access::pcm($this->currentUser);
            try {
                $found=\Cake\Datasource\ConnectionManager::get('protheus')->execute(\App\Integration\Totvs\Queries::ORDER_NOTES,['numero'=>$activity->os_numero,'filial'=>$activity->os_filial])->fetchAll('assoc');
                if(count($found)!==1)throw new \DomainException('O.S. não encontrada ou ambígua.');
                $notes=trim((string)$found[0]['observacoes']);
                if(!mb_check_encoding($notes,'UTF-8'))$notes=mb_convert_encoding($notes,'UTF-8','Windows-1252');
            }catch(\Throwable){$this->Flash->error('Não foi possível consultar as observações no TOTVS. Nenhuma descrição foi alterada.');}
        }
        $this->set(compact('activity', 'rows', 'totals', 'total','metrics','progress','areaCurva','historyPage','notes'));
    }
    public function descricao($id)
    {
        $this->request->allowMethod(['post']);\App\Service\Access::pcm($this->currentUser);
        try{
            (new \App\Service\DescricaoAtividade())->save((int)$this->currentUser->id,(int)$id,(string)$this->request->getData('descricao_atividade'),(string)$this->request->getData('descricao_origem'));
            $this->Flash->success('Descrição específica salva com auditoria.');
        }catch(\DomainException $e){$this->Flash->error($e->getMessage());}
        return $this->redirect(['action'=>'view',$id]);
    }
}
