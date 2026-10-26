<?php
declare(strict_types=1);
namespace App\Controller;
use App\Service\{Access,OperacaoRapida,Evolucao};
class OperacionalController extends OperacaoController
{
    public function ordens(\App\Integration\Totvs\GatewayInterface $gateway)
    {
        $term=$this->request->getQuery('q','');$status=200;
        try{if(!is_string($term))throw new \DomainException('Pesquisa inválida.');$data=(new OperacaoRapida($gateway))->search($this->currentUser,$term,$this->request->getQuery('tipo')==='evolucao');}
        catch(\DomainException $e){$status=422;$data=['error'=>$e->getMessage()];}
        return $this->response->withStatus($status)->withType('application/json')->withHeader('Cache-Control','no-store, private')->withStringBody(json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    }
    public function identificar(\App\Integration\Totvs\GatewayInterface $gateway)
    {
        $this->request->allowMethod(['post']);$status=200;
        try{$key=$this->request->getData('os_chave');if(!is_string($key))throw new \DomainException('Selecione uma O.S. válida.');$id=(new \App\Service\Activities($gateway))->identify((int)$this->currentUser->id,$key);$s=new OperacaoRapida($gateway);$data=['order'=>$s->result($s->activity($this->currentUser,$id,true),$this->request->getData('tipo')==='evolucao')];}catch(\DomainException $e){$status=422;$data=['error'=>$e->getMessage()];}
        return $this->response->withStatus($status)->withType('application/json')->withHeader('Cache-Control','no-store')->withStringBody(json_encode($data,JSON_THROW_ON_ERROR));
    }
    public function porcentagem()
    {
        $service=new \App\Service\PorcentagensLote();$season=(int)\App\Service\PeriodoContexto::id();
        $area=(int)$this->request->getQuery('area_id',0);$search=$this->request->getQuery('os','');$pending=[];
        $key=(string)($this->request->getData('chave')??bin2hex(random_bytes(16)));$ajax=$this->request->getHeaderLine('X-Requested-With')==='XMLHttpRequest';
        try{
            if(!is_string($search))throw new \DomainException('Pesquisa inválida.');
            $search=trim($search);if($search!=='')$area=0;
            if($this->request->is('post')){
                if($this->request->getData('atividade_id')!==null)throw new \DomainException('Use o lançamento em lote. O lançamento individual foi encerrado.');
                $json=$this->request->getData('alteracoes_json','[]');
                if(!is_string($json)||strlen($json)>2000000)throw new \DomainException('Lote muito extenso.');
                try{$pending=json_decode($json,true,512,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new \DomainException('Alterações inválidas.');}
                if(!is_array($pending))throw new \DomainException('Alterações inválidas.');
                $result=$service->save((int)$this->currentUser->id,$season,$area,$pending,$key);
                $this->Flash->success($result['quantidade'].' O.S. atualizada(s).');
                if($ajax)return $this->response->withType('application/json')->withHeader('Cache-Control','no-store')->withStringBody(json_encode(['ok'=>true]+$result));
                return $this->redirect($this->request->getRequestTarget());
            }
        }catch(\DomainException $e){
            if($ajax)return $this->response->withStatus(422)->withType('application/json')->withStringBody(json_encode(['error'=>$e->getMessage()]));
            $this->Flash->error($e->getMessage());
        }
        try{$batch=$service->page($this->currentUser,$season,$area,is_string($search)?$search:'',(int)$this->request->getQuery('pagina',1));}
        catch(\DomainException $e){$this->Flash->error($e->getMessage());$batch=['areas'=>$service->areas($this->currentUser,$season),'rows'=>[],'total'=>0,'pages'=>1,'page'=>1,'reference'=>null];}
        $this->set(compact('batch','season','area','search','key','pending'));
    }
}
