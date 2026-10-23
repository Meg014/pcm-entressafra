<?php
declare(strict_types=1);
namespace App\Service;

use App\Integration\Totvs\{PendingOrders,Orders};

final class OsSemCaderno
{
    public function __construct(private PendingOrders $source=new PendingOrders(),private ListLinks $links=new ListLinks()) {}
    private function context(int $actor): int
    {
        Access::admin(Access::actor($actor));$season=PeriodoContexto::id();
        if(!$season)throw new \DomainException('Selecione a entressafra.');
        return $season;
    }
    public function create(int $actor): string
    {
        $season=$this->context($actor);PeriodoContexto::writable($season);
        $token=bin2hex(random_bytes(24));$t=Access::table('PendenciasConsultas');
        $t->saveOrFail(Access::entity($t,['token'=>$token,'usuario_id'=>$actor,'entressafra_id'=>$season,'estado'=>'consultando','cursor_os'=>'','consultadas'=>0,'created'=>gmdate('Y-m-d H:i:s')]));
        return $token;
    }
    public function job(int $actor,string $token)
    {
        $season=$this->context($actor);
        if(!preg_match('/^[a-f0-9]{48}$/D',$token))throw new \DomainException('Consulta inválida.');
        $job=Access::table('PendenciasConsultas')->find()->where(['token'=>$token,'usuario_id'=>$actor,'entressafra_id'=>$season])->first();
        if(!$job)throw new \DomainException('Consulta não encontrada nesta conta e entressafra.');
        return $job;
    }
    private function locked(int $actor,string $token)
    {
        $job=$this->job($actor,$token);PeriodoContexto::writable((int)$job->entressafra_id);
        $db=Access::table('PendenciasConsultas')->getConnection();
        if($db->getDriver() instanceof \Cake\Database\Driver\Postgres)$db->execute('SELECT id FROM pendencias_consultas WHERE id=? FOR UPDATE',[$job->id]);
        return $this->job($actor,$token);
    }
    public function scan(int $actor,string $token): void
    {
        Access::table('PendenciasConsultas')->getConnection()->transactional(function()use($actor,$token){
            $job=$this->locked($actor,$token);if($job->estado!=='consultando')return;
            $data=$this->source->page($job->cursor_os);$t=Access::table('PendenciasOrdens');
            foreach($data['rows'] as $row){
                if($row['filial']!=='01'||!Orders::available($row)||(int)$row['identidades']!==1||!preg_match('/^[0-9]{6}$/D',$row['numero']))continue;
                if($t->exists(['consulta_id'=>$job->id,'numero'=>$row['numero']]))continue;
                $t->saveOrFail(Access::entity($t,['consulta_id'=>$job->id,'numero'=>$row['numero'],'servico_codigo'=>$row['codigo_servico'],'servico_nome'=>$row['servico'],'equipe'=>$row['codigo_area'],'situacao'=>$row['situacao'],'snapshot'=>json_encode($row,JSON_THROW_ON_ERROR),'selecionada'=>false]));
            }
            $job->cursor_os=$data['cursor'];$job->consultadas+=count($data['rows']);$job->estado=$data['has_more']?'consultando':'pronto';Access::table('PendenciasConsultas')->saveOrFail($job);
        });
    }
    private function pending($job,array $filters=[])
    {
        $linked=Access::table('Atividades')->find()->select(['os_numero'])->where(['entressafra_id'=>$job->entressafra_id,'os_filial'=>'01','caderno_id IS NOT'=>null]);
        $q=Access::table('PendenciasOrdens')->find()->where(['consulta_id'=>$job->id,'numero NOT IN'=>$linked]);
        foreach(['servico'=>'servico_codigo','equipe'=>'equipe','situacao'=>'situacao'] as $key=>$column){
            $value=$filters[$key]??'';if(!is_string($value)||strlen($value)>20)throw new \DomainException('Filtro inválido.');
            if($value!=='')$q->where([$column=>$value]);
        }
        $number=$filters['numero']??'';
        if(!is_string($number)||($number!==''&&!preg_match('/^[0-9]{1,6}$/D',$number)))throw new \DomainException('Pesquise até seis dígitos do número da O.S.');
        if($number!=='')$q->where(['numero LIKE'=>'%'.$number.'%']);
        return $q;
    }
    public function summary(int $actor,string $token,array $filters=[],int $page=1): array
    {
        $job=$this->job($actor,$token);$all=$this->pending($job);$q=$this->pending($job,$filters);$total=$q->count();$pages=max(1,(int)ceil($total/10));$page=max(1,min($page,$pages));
        $rows=(clone $q)->orderBy(['numero'=>'ASC'])->limit(10)->offset(($page-1)*10)->all();
        $display=[];foreach($rows as $row){$os=json_decode($row->snapshot,true);$local=Access::table('Atividades')->find()->where(['entressafra_id'=>$job->entressafra_id,'os_filial'=>'01','os_numero'=>$row->numero])->first();$display[]=['row'=>$row,'descricao'=>OsApresentacao::description($local?->descricao_atividade,$os)];}
        $services=(clone $all)->select(['servico_codigo','servico_nome'])->distinct()->orderBy(['servico_codigo'=>'ASC'])->disableHydration()->all()->toList();
        $teams=(clone $all)->select(['equipe'])->distinct()->orderBy(['equipe'=>'ASC'])->all()->extract('equipe')->toList();
        $states=(clone $all)->select(['situacao'])->distinct()->all()->extract('situacao')->toList();
        $selected=(clone $all)->where(['selecionada'=>true])->count();
        $plan=$job->plano?json_decode($job->plano,true):null;$counts=[];
        foreach($plan['tokens']??[] as $linkToken){$part=$this->links->summary($actor,$linkToken);foreach($part['counts'] as $state=>$count)$counts[$state]=($counts[$state]??0)+$count;}
        return compact('job','display','total','pages','page','services','teams','states','selected','plan','counts')+['pendentes'=>$all->count()];
    }
    public function select(int $actor,string $token,string $action,array $numbers=[],array $filters=[],array $visible=[]): void
    {
        Access::table('PendenciasConsultas')->getConnection()->transactional(function()use($actor,$token,$action,$numbers,$filters,$visible){
            $job=$this->locked($actor,$token);if($job->estado!=='pronto')throw new \DomainException('Aguarde a conclusão da consulta.');
            if($action==='pagina'&&$visible){
                if(count($visible)>10||array_diff($numbers,$visible))throw new \DomainException('Página de seleção inválida.');
                foreach($visible as $number)if(!is_string($number)||!preg_match('/^[0-9]{6}$/D',$number))throw new \DomainException('Página de seleção inválida.');
                Access::table('PendenciasOrdens')->updateAll(['selecionada'=>false],['consulta_id'=>$job->id,'numero IN'=>$visible]);
            }
            $q=$this->pending($job,$action==='todas'?$filters:[]);
            if(in_array($action,['pagina','remover'],true)){
                if(count($numbers)>10)throw new \DomainException('Selecione até dez O.S. por página.');
                foreach($numbers as $number)if(!is_string($number)||!preg_match('/^[0-9]{6}$/D',$number))throw new \DomainException('Seleção inválida.');
                $q->where(['numero IN'=>$numbers?:['-']]);
            }elseif(!in_array($action,['todas','limpar'],true))throw new \DomainException('Seleção inválida.');
            $ids=$q->select(['id'])->all()->extract('id')->toList();
            foreach(array_chunk($ids,500) as $chunk)Access::table('PendenciasOrdens')->updateAll(['selecionada'=>!in_array($action,['limpar','remover'],true)],['consulta_id'=>$job->id,'id IN'=>$chunk]);
            $job->plano=null;Access::table('PendenciasConsultas')->saveOrFail($job);
        });
    }
    public function preview(int $actor,string $token,int $book): void
    {
        Access::table('PendenciasConsultas')->getConnection()->transactional(function()use($actor,$token,$book){
            $job=$this->locked($actor,$token);if($job->estado!=='pronto')throw new \DomainException('Aguarde a consulta.');
            PeriodoContexto::notebook($book,true);$notebook=Access::table('Cadernos')->get($book);
            if(!$notebook->ativo||!$notebook->area_curva_id)throw new \DomainException('Escolha um caderno ativo com setor principal.');
            $quantity=$this->pending($job)->where(['selecionada'=>true])->count();if(!$quantity)throw new \DomainException('Selecione pelo menos uma O.S. disponível.');
            $job->plano=json_encode(['caderno_id'=>$book,'caderno'=>$notebook->nome,'quantidade'=>$quantity,'confirmacao'=>bin2hex(random_bytes(24))],JSON_THROW_ON_ERROR);Access::table('PendenciasConsultas')->saveOrFail($job);
        });
    }
    public function confirm(int $actor,string $token,string $confirmation): void
    {
        Access::table('PendenciasConsultas')->getConnection()->transactional(function()use($actor,$token,$confirmation){
            $job=$this->locked($actor,$token);$plan=json_decode($job->plano??'null',true);
            if(!$plan||!hash_equals($plan['confirmacao'],$confirmation))throw new \DomainException('Resumo inválido. Revise a seleção.');
            if(in_array($job->estado,['vinculando','concluido'],true))return;
            if($job->estado!=='pronto')throw new \DomainException('Aguarde a consulta.');
            PeriodoContexto::notebook((int)$plan['caderno_id'],true);
            $numbers=$this->pending($job)->where(['selecionada'=>true])->orderBy(['numero'=>'ASC'])->all()->extract('numero')->toList();
            if(count($numbers)!==$plan['quantidade'])throw new \DomainException('Alguma O.S. já foi vinculada por outro usuário. Revise o resumo antes de confirmar.');
            $plan['tokens']=[];foreach(array_chunk($numbers,2000) as $chunk)$plan['tokens'][]=$this->links->create($actor,(int)$plan['caderno_id'],implode("\n",$chunk));
            $job->plano=json_encode($plan,JSON_THROW_ON_ERROR);$job->estado='vinculando';Access::table('PendenciasConsultas')->saveOrFail($job);
            Access::audit(Access::actor($actor),'os_sem_caderno_confirmada',null,null,['consulta'=>$job->id,'caderno'=>$plan['caderno_id'],'quantidade'=>count($numbers)],'Confirmação administrativa da seleção.');
        });
    }
    public function process(int $actor,string $token): void
    {
        Access::table('PendenciasConsultas')->getConnection()->transactional(function()use($actor,$token){
            $job=$this->locked($actor,$token);if($job->estado!=='vinculando')return;
            $plan=json_decode($job->plano,true);
            foreach($plan['tokens'] as $part){
                $linkJob=$this->links->job($actor,$part);
                if($linkJob->estado==='preparando'){$this->links->prepare($actor,$part);return;}
                if($linkJob->estado==='pronto'){
                    $summary=$this->links->summary($actor,$part);
                    if(!empty($summary['counts']['elegivel'])){$this->links->confirmEligible($actor,$part);return;}
                    continue;
                }
                if($linkJob->estado==='processando'){$this->links->process($actor,$part);return;}
            }
            $job->estado='concluido';Access::table('PendenciasConsultas')->saveOrFail($job);
        });
    }
}
