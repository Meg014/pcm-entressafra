<?php
declare(strict_types=1);
namespace App\Controller;
use App\Service\{Access,FilaPcm};
class FilaPcmController extends OperacaoController
{
    public function index(): void { Access::pcm($this->currentUser);$count=$this->fetchTable('Apontamentos')->find()->select(['atividade_id'])->distinct(['atividade_id'])->innerJoinWith('Atividades')->where(['Atividades.entressafra_id'=>\App\Service\PeriodoContexto::id(),'cancelado'=>false,'controle_pcm IN'=>['pendente','conferido']])->count();$pages=max(1,(int)ceil($count/10));$page=max(1,min((int)$this->request->getQuery('pagina',1),$pages));$this->set(compact('page','pages'));$this->set('rows',(new FilaPcm())->queue($this->currentUser,$page)); }
    public function view($id)
    {
        Access::pcm($this->currentUser);$activity=$this->activity((int)$id);
        if($this->request->is('post')) {
            try {
                $data=$this->request->getData();$selection=[];
                foreach((array)($data['selecionados']??[]) as $entry){$selection[]=['id'=>$entry,'revisao'=>$data['revisoes'][$entry]??null];}
                $n=(new FilaPcm())->confirm((int)$this->currentUser->id,(int)$id,$selection,(string)($data['acao']??''),(string)($data['motivo']??''),($data['confirmar']??'')==='1');
                $this->Flash->success($n.' apontamento(s) atualizado(s). Nenhuma escrita foi feita no TOTVS.');return $this->redirect(['action'=>'view',$id]);
            }catch(\DomainException $e){$this->Flash->error($e->getMessage());}
        }
        $rows=$this->paginate($this->fetchTable('Apontamentos')->find()->where(['atividade_id'=>(int)$id])->orderBy(['created'=>'ASC','id'=>'ASC']),['limit'=>10,'maxLimit'=>10]);$this->set(compact('activity','rows'));
    }
}
