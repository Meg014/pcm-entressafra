<?php
declare(strict_types=1);
namespace App\Controller;
use App\Service\{Access,FilaPcm,OrderProgress,OrderDescriptions,CancelamentoRegistrosOs,PeriodoContexto};
use App\Integration\Totvs\ListOrders;

class FilaPcmController extends OperacaoController
{
    public function index(ListOrders $orders): void
    {
        Access::pcm($this->currentUser);$service=new FilaPcm();
        $sectors=$service->sectorOptions($this->currentUser);
        $number=$this->request->getQuery('os_numero','');
        if (!is_string($number) || (trim($number)!=='' && !preg_match('/^[0-9]{1,60}$/D',trim($number)))) { throw new \Cake\Http\Exception\BadRequestException('Número da O.S. inválido.'); }
        $filters=['os_numero'=>trim($number),'setor'=>$this->request->getQuery('setor',''),'evolucao'=>$this->request->getQuery('evolucao','')];
        if (!is_string($filters['setor']) || ($filters['setor']!=='' && !array_key_exists($filters['setor'],$sectors)) ||
            !is_string($filters['evolucao']) || !in_array($filters['evolucao'],['','zero','andamento','concluidas'],true)) {
            throw new \Cake\Http\Exception\BadRequestException('Filtros inválidos.');
        }
        $requested=$this->request->getQuery('pagina',1);
        $result=$service->queuePage($this->currentUser,is_scalar($requested)?(int)$requested:1,$filters);
        $this->set($result);
        $this->set('presentations',(new OrderDescriptions($orders))->presentations(array_column($result['rows'],'id')));
        $this->set(compact('sectors','filters'));
        $bulkCancellation=null;
        $cancelId=$this->request->getQuery('excluir_os');
        if ($cancelId!==null) {
            Access::admin($this->currentUser);
            if (!is_string($cancelId) || !ctype_digit($cancelId) || (int)$cancelId<1) { throw new \Cake\Http\Exception\BadRequestException('O.S. inválida.'); }
            $activity=$this->activity((int)$cancelId);
            $preview=(new CancelamentoRegistrosOs())->preview((int)$this->currentUser->id,(int)$activity->id);
            $token=bin2hex(random_bytes(32));
            $this->request->getSession()->write('CancelamentoOs.'.$activity->id,[
                'usuario_id'=>(int)$this->currentUser->id,'entressafra_id'=>PeriodoContexto::id(),
                'token'=>$token,'assinatura'=>$preview['assinatura'],
            ]);
            $description=(new OrderDescriptions($orders))->presentations([(int)$activity->id])[(int)$activity->id]['descricao'];
            $bulkCancellation=compact('activity','preview','token','description');
        }
        $this->set(compact('bulkCancellation'));
    }
    private function queueQuery(): array
    {
        return array_intersect_key($this->request->getQueryParams(),array_flip(['os_numero','setor','evolucao','pagina','entressafra_id']));
    }
    public function cancelRecords($id)
    {
        $this->request->allowMethod(['post']);Access::admin($this->currentUser);
        $this->activity((int)$id);
        $session=$this->request->getSession();$key='CancelamentoOs.'.(int)$id;
        try {
            $confirmation=$session->read($key);$token=$this->request->getData('confirmacao');$reason=$this->request->getData('motivo');
            if (!is_array($confirmation) || ($confirmation['usuario_id']??null)!==(int)$this->currentUser->id ||
                ($confirmation['entressafra_id']??null)!==PeriodoContexto::id() || !is_string($token) ||
                !hash_equals($confirmation['token'],$token)) { throw new \DomainException('Abra a confirmação da O.S. antes de excluir os registros.'); }
            if (!is_string($reason)) { throw new \DomainException('Informe a justificativa da exclusão.'); }
            $result=(new CancelamentoRegistrosOs())->cancel((int)$this->currentUser->id,(int)$id,$reason,
                $this->request->getData('confirmar')==='1',$confirmation['assinatura']);
            $session->delete($key);
            $this->Flash->success('O.S. '.$result['os_numero'].': '.$result['apontamentos'].' apontamento(s) e '.$result['insumos'].' insumo(s) cancelado(s). Histórico preservado.');
            return $this->redirect(['action'=>'index','?'=>$this->queueQuery()]);
        } catch (\DomainException $e) {
            $this->Flash->error($e->getMessage());
            return $this->redirect(['action'=>'index','?'=>['excluir_os'=>(int)$id]+$this->queueQuery()]);
        }
    }
    public function view($id,ListOrders $orders)
    {
        Access::pcm($this->currentUser);$activity=$this->activity((int)$id);
        if($this->request->is('post')){
            try{
                $data=$this->request->getData();
                if (($data['acao']??'')==='cancelar') {
                    Access::admin($this->currentUser);
                    if (($data['confirmar']??'')!=='1' || !is_string($data['motivo']??null) || !is_string($data['apontamento_id']??null) || !ctype_digit($data['apontamento_id']) || !is_string($data['revisao']??null) || !ctype_digit($data['revisao'])) { throw new \DomainException('Confirme o apontamento e informe a justificativa.'); }
                    (new \App\Service\TimeEntries($this->totvs()))->change((int)$this->currentUser->id,(int)$data['apontamento_id'],'cancelar',$data['motivo'],(int)$id,(int)$data['revisao']);
                    $this->Flash->success('Apontamento cancelado com histórico preservado.');
                    return $this->redirect(['action'=>'view',$id,'?'=>array_diff_key($this->request->getQueryParams(),['cancelar'=>true]),'#'=>'horas']);
                }
                $category=$data['categoria']??'horas';
                if(!in_array($category,['horas','insumos'],true))throw new \DomainException('Categoria inválida.');
                if(!is_array($data['selecionados']??null)||!is_array($data['revisoes']??null))throw new \DomainException('Selecione os registros desta categoria.');
                foreach(['acao','motivo','confirmar'] as $field)if(isset($data[$field])&&!is_string($data[$field]))throw new \DomainException('Operação inválida.');
                $selection=[];
                foreach($data['selecionados'] as $entry){
                    if(!is_scalar($entry)||!ctype_digit((string)$entry))throw new \DomainException('Seleção inválida.');
                    $selection[]=['id'=>$entry,'revisao'=>$data['revisoes'][$entry]??null];
                }
                $service=new FilaPcm();$method=$category==='insumos'?'confirmSupplies':'confirm';
                $n=$service->$method((int)$this->currentUser->id,(int)$id,$selection,$data['acao']??'',$data['motivo']??'',($data['confirmar']??'')==='1');
                $this->Flash->success($n.($category==='insumos'?' insumo(s)':' apontamento(s)').' atualizado(s). Nenhuma escrita foi feita no TOTVS.');
                return $this->redirect(['action'=>'view',$id,'?'=>$this->request->getQueryParams(),'#'=>$category]);
            }catch(\DomainException $e){$this->Flash->error($e->getMessage());}
        }
        $rows=$this->paginate($this->fetchTable('Apontamentos')->find()->where(['atividade_id'=>(int)$id])->orderBy(['created'=>'ASC','id'=>'ASC']),['limit'=>10,'maxLimit'=>10]);
        [$supplies,$supplyPage,$supplyPages,$supplyTotal]=$this->sectionPage($this->fetchTable('Insumos')->find()->where(['atividade_id'=>(int)$id])->orderBy(['created'=>'ASC','id'=>'ASC']),'insumos_pagina',10);
        $historyQuery=$this->fetchTable('Auditorias')->find()->where(['OR'=>[
            'apontamento_id IN'=>$this->fetchTable('Apontamentos')->find()->select(['id'])->where(['atividade_id'=>(int)$id]),
            'insumo_id IN'=>$this->fetchTable('Insumos')->find()->select(['id'])->where(['atividade_id'=>(int)$id]),
        ]])->orderBy(['Auditorias.created'=>'DESC','Auditorias.id'=>'DESC']);
        [$history,$historyPage,$historyPages,$historyTotal]=$this->sectionPage($historyQuery,'historico_pagina',20);
        $cancelEntry=null;
        $cancelId=$this->request->getQuery('cancelar');
        if ($cancelId!==null) {
            Access::admin($this->currentUser);
            if (!is_string($cancelId) || !ctype_digit($cancelId)) { throw new \Cake\Http\Exception\BadRequestException('Apontamento inválido.'); }
            $cancelEntry=$this->fetchTable('Apontamentos')->find()->where(['id'=>(int)$cancelId,'atividade_id'=>(int)$id])->firstOrFail();
        }
        $this->set(compact('cancelEntry'));
        $completion=OrderProgress::status((int)$id);
        $presentation=(new OrderDescriptions($orders))->presentations([(int)$activity->id])[(int)$activity->id];
        $this->set(compact('presentation'));
        $this->set(compact('activity','rows','supplies','supplyPage','supplyPages','supplyTotal','history','historyPage','historyPages','historyTotal','completion'));
    }
    private function sectionPage($query,string $key,int $limit): array
    {
        $total=$query->count();$pages=max(1,(int)ceil($total/$limit));
        $page=max(1,min((int)$this->request->getQuery($key,1),$pages));
        return [$query->limit($limit)->offset(($page-1)*$limit)->all(),$page,$pages,$total];
    }
}
