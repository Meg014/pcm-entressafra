<?php
declare(strict_types=1);
namespace App\Service;
final class OperacaoRapida
{
    public function __construct(private ?\App\Integration\Totvs\GatewayInterface $gateway=null) {}
    public function activity($user,int $id,bool $progress=false)
    {
        $a=Access::table('Atividades')->get($id,contain:['Entressafras','SetorExecutante','Cadernos'=>['Entressafras']]);Access::activity($user,$a);
        if($a->setor_executante_id===null || (PeriodoContexto::id()===null?$a->entressafra->status!=='ativa':$a->entressafra->status==='encerrada')) { throw new \DomainException('Selecione uma O.S. identificada na entressafra ativa e em um setor autorizado.'); }
        if(!$progress && $a->disponivel_totvs!==true) { throw new \DomainException('O.S. indisponível para novos apontamentos. Solicite conferência ao PCM.'); }
        return $a;
    }
    public function result($a,bool $progress=false,array $source=[]): array
    {
        $out=['id'=>(string)$a->id,'numero'=>$a->os_numero,'filial'=>$a->os_filial,'servico'=>$a->servico,'bem'=>$a->bem,'situacao'=>$a->situacao_totvs,'setor'=>$a->setor_executante?->nome??'Pendente','caderno'=>($a->caderno?->nome??'Sem caderno (opcional)'),'entressafra'=>$a->entressafra->nome,'entressafra_id'=>(int)$a->entressafra_id,'descricao_caderno'=>$a->caderno?->descricao??'','selecionavel'=>true,'codigo_area'=>$a->area_executante_totvs,'centro_custo'=>''];
        if($progress) { [$reference,$week]=$this->reference((int)$a->entressafra_id,null,(int)$a->id);$state=(new Evolucao())->state((int)$a->id,$reference);$out+=['referencia'=>$reference,'calendario_confirmado'=>$week!==null,'anterior'=>$state['anterior'],'percentual'=>$state['percentual'],'revisao'=>$state['revisao'],'referencias'=>array_column((new Evolucao())->calendar((int)$a->entressafra_id),'referencia')]; }
        if(!$progress)$out+=OrderCompletion::status((int)$a->id);
        $presentation=OsApresentacao::activity($a,$source);
        if(!$source&&$this->gateway!==null&&$presentation['descricao']==='Descrição não informada'){
            try{$source=$this->gateway->order($a->os_chave);$presentation=OsApresentacao::activity($a,$source);}
            catch(\DomainException){/* Display remains available when the source is offline. */}
        }
        return $out+$presentation;
    }
    public function reference(int $season,?string $requested=null,?int $activityId=null): array
    {
        $today=(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        $past=array_values(array_filter((new Evolucao())->calendar($season),fn($w)=>$w['referencia']<=$today));
        if($requested===null) {
            $s=Access::table('Entressafras')->get($season);
            $automatic=Calendarios::weeks($s->data_inicio->format('Y-m-d'),$s->data_fim->format('Y-m-d'));
            $past=array_values(array_filter($past,fn($w)=>in_array($w['referencia'],$automatic,true)));
        }
        if(!$past) { throw new \DomainException('A entressafra ainda não possui semana iniciada.'); }
        $week=end($past);
        if($requested!==null) { $matches=array_values(array_filter($past,fn($w)=>$w['referencia']===$requested));if(!$matches)throw new \DomainException('Referência retroativa inválida.');$week=$matches[0]; }
        return [$week['referencia'],$week['id']];
    }
    public function search($user,string $term,bool $progress): array
    {
        if(mb_strlen(trim($term))<2 || mb_strlen($term)>60) { throw new \DomainException('Digite de 2 a 60 caracteres.'); }
        $seasons=Access::table('Entressafras')->find()->where(PeriodoContexto::id()!==null?['id'=>PeriodoContexto::id()]:['status'=>'ativa'])->all();if($seasons->count()!==1)return ['results'=>[],'has_more'=>false,'message'=>'O PCM deve configurar exatamente uma entressafra ativa.'];$season=$seasons->first();
        $gateway=$this->gateway??new \App\Integration\Totvs\Gateway();$results=[];
        foreach(Access::table('Setores')->find()->where(['id IN'=>Access::sectors($user)?:[-1],'ativo'=>true,'filial_totvs'=>'01'])->all() as $sector){
            if(!$sector->area_totvs)continue;
            foreach($gateway->orders($term,$sector->area_totvs,'01') as $r){
                if(!\App\Integration\Totvs\Orders::available($r)||$r['filial']!=='01'||$r['codigo_area']!==$sector->area_totvs)continue;
                $old=Access::table('Atividades')->find()->where(['entressafra_id'=>$season->id,'os_numero'=>$r['numero'],'os_filial'=>'01'])->first();
                if($old){$a=$this->activity($user,(int)$old->id,true);$out=$this->result($a,$progress,$r);$out['selecionavel']=(int)$old->setor_executante_id===(int)$sector->id&&$old->area_executante_totvs===$r['codigo_area'];}
                else{$out=['id'=>null,'selecionavel'=>true,'numero'=>$r['numero'],'filial'=>'01','servico'=>$r['servico'],'bem'=>$r['bem'],'setor'=>$sector->nome,'caderno'=>'Sem caderno (opcional)','situacao'=>$r['situacao'],'codigo_area'=>$r['codigo_area'],'centro_custo'=>$r['centro_custo']??''];}
                $out+=OsApresentacao::source($r,$sector->nome);
                $out['os_chave']=$r['chave'];$out['confirmar_origem']=true;$results[$r['chave']]=$out;
            }
        }
        if($progress){foreach(Access::activityScope(Access::table('Atividades')->find()->contain(['Entressafras','SetorExecutante','Cadernos']),$user)->where(['Atividades.entressafra_id'=>$season->id,'Atividades.disponivel_totvs'=>false,'OR'=>['os_numero LIKE'=>'%'.$term.'%','servico LIKE'=>'%'.$term.'%']])->limit(20)->all() as $a){$results[$a->os_chave]=$this->result($a,true);}}
        return ['results'=>array_slice(array_values($results),0,20),'has_more'=>count($results)>20,'message'=>'Nenhuma O.S. elegível nas áreas executantes autorizadas.'];
    }
}
