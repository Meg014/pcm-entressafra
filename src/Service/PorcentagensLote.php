<?php
declare(strict_types=1);
namespace App\Service;

final class PorcentagensLote
{
    public function __construct(private ?\App\Integration\Totvs\ListOrders $source=null) {}
    private function scope($user,int $season,int $area)
    {
        PeriodoContexto::season($season);
        $areas=$area?[$area]:array_keys($this->areas($user,$season));
        return Access::activityScope(Access::table('Atividades')->find(),$user)
            ->innerJoinWith('Cadernos',fn($q)=>$q->where(['Cadernos.entressafra_id'=>$season,'Cadernos.area_curva_id IN'=>$areas?:[-1]]))
            ->where(['Atividades.entressafra_id'=>$season]);
    }
    public function areas($user,int $season): array
    {
        $q=Access::table('AreasFabrica')->find('list')->where(['entressafra_id'=>$season])->orderBy(['nome'=>'ASC']);
        if($user->perfil==='RESPONSAVEL_SETOR'){
            $books=Access::activityScope(Access::table('Atividades')->find(),$user)->select(['caderno_id'])->where(['Atividades.entressafra_id'=>$season,'caderno_id IS NOT'=>null]);
            $ids=Access::table('Cadernos')->find()->select(['area_curva_id'])->where(['id IN'=>$books,'area_curva_id IS NOT'=>null]);$q->where(['id IN'=>$ids]);
        }
        return $q->all()->toArray();
    }
    public function page($user,int $season,int $area,string $search,int $page): array
    {
        if($search!==''&&!preg_match('/^[0-9]{1,6}$/D',$search))throw new \DomainException('Pesquise até seis dígitos do número da O.S.');
        if($search!=='')$area=0;
        $areas=$this->areas($user,$season);if($area&&!array_key_exists($area,$areas))throw new \Cake\Http\Exception\ForbiddenException('Setor da Curva S não autorizado nesta entressafra.');
        $rows=[];$total=0;$pages=1;$page=max(1,$page);$reference=null;
        if($area || $search!==''){
            [$reference]=(new OperacaoRapida())->reference($season);
            $q=$this->scope($user,$season,$area);if($search!=='')$q->where(['Atividades.os_numero LIKE'=>'%'.$search.'%']);
            $total=$q->count();$pages=max(1,(int)ceil($total/50));$page=min($page,$pages);$s=new Evolucao();$s->loadHistory($season);
            foreach($q->orderBy(['Atividades.os_numero'=>'ASC','Atividades.id'=>'ASC'])->limit(50)->offset(($page-1)*50)->all() as $a){
                $state=$s->state((int)$a->id,$reference);$rows[]=['id'=>(int)$a->id,'numero'=>$a->os_numero,'descricao'=>OsApresentacao::description($a->descricao_atividade,json_decode($a->snapshot_totvs??'{}',true)??[]),'bem'=>$a->bem,'percentual'=>$state['percentual'],'revisao'=>$state['revisao']];
            }
            $missing=[];foreach($rows as $i=>$row)if($row['descricao']==='Descrição não informada')$missing[$row['numero']]=$i;
            if($missing){
                try{$notes=($this->source??new \App\Integration\Totvs\ListOrders())->find(array_map('strval',array_keys($missing)));foreach($notes as $number=>$note)if(isset($missing[$number]))$rows[$missing[$number]]['descricao']=OsApresentacao::description(null,$note);}
                catch(\DomainException){/* A source outage never blocks local percentage work. */}
            }
        }
        return compact('areas','rows','total','pages','page','reference');
    }
    public function save(int $actor,int $season,int $area,array $rows,string $key): array
    {
        PeriodoContexto::writable($season);$user=Access::actor($actor);
        if($area!==0&&!array_key_exists($area,$this->areas($user,$season)))throw new \Cake\Http\Exception\ForbiddenException('Setor da Curva S não autorizado.');
        if(!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$key)||count($rows)>10000)throw new \DomainException('Lote inválido.');
        $clean=[];foreach($rows as $r){
            if(!is_array($r))throw new \DomainException('Alteração inválida.');
            $id=filter_var($r['atividade_id']??null,FILTER_VALIDATE_INT);if(!$id||isset($clean[$id]))throw new \DomainException('O.S. inválida ou repetida.');
            $revision=$r['revisao']??null;if(!is_scalar($revision)||!ctype_digit((string)$revision))throw new \DomainException('Revisão inválida.');
            $clean[$id]=['atividade_id'=>$id,'percentual'=>Evolucao::number($r['percentual']??null),'base'=>Evolucao::number($r['base']??null),'revisao'=>(int)$revision,'referencia'=>$r['referencia']??null];
        }
        ksort($clean);$hash=hash('sha256',json_encode([$season,$area,$clean],JSON_THROW_ON_ERROR));$batchKey='porclote-'.$key;
        $db=Access::table('Atividades')->getConnection();
        return $db->transactional(function()use($db,$actor,$season,$area,$clean,$batchKey,$hash,$user){
            if($db->getDriver() instanceof \Cake\Database\Driver\Postgres)$db->execute('SELECT id FROM entressafras WHERE id=? FOR UPDATE',[$season]);
            $old=$db->execute('SELECT * FROM evolucao_lotes WHERE usuario_id=? AND chave=?',[$actor,$batchKey])->fetch('assoc');
            if($old){if($old['hash']!==$hash)throw new \DomainException('Esta solicitação já foi utilizada com outros valores. Confira os dados antes de salvar novamente.');return json_decode($old['resultado'],true,512,JSON_THROW_ON_ERROR);}
            [$reference]=(new OperacaoRapida())->reference($season);$s=new Evolucao();$s->loadHistory($season);$changed=[];
            foreach($clean as $id=>$r){
                $a=$this->scope($user,$season,$area)->where(['Atividades.id'=>$id])->first();
                if(!$a)throw new \DomainException('Uma O.S. não pertence mais ao setor/caderno autorizado. As alterações foram preservadas.');
                if($r['percentual']===$r['base'])continue;
                if($r['referencia']!==$reference)throw new \DomainException('A referência automática mudou. Recarregue e confira os valores antes de salvar.');
                $state=$s->state($id,$reference);
                if($state['percentual']!==$r['base']||$state['revisao']!==$r['revisao'])throw new \DomainException('Outra pessoa alterou a O.S. '.$a->os_numero.'. Valor atual: '.$state['percentual'].'%. Recarregue e confira o valor salvo antes de refazer a alteração.');
                if($r['percentual']<$state['percentual'])throw new \DomainException('O.S. '.$a->os_numero.': redução de percentual exige correção administrativa separada.');
                foreach($s->history($id) as $history)if($history['referencia']>$reference)throw new \DomainException('O.S. '.$a->os_numero.': o histórico posterior exige correção administrativa separada.');
                $changed[]=['atividade_id'=>$id,'percentual'=>$r['percentual'],'revisao'=>$r['revisao']];
            }
            $ids=[];foreach(array_chunk($changed,200) as $index=>$chunk){$part=(new Evolucao())->saveBatch($actor,$season,$reference,$chunk,hash('sha256',$batchKey.':'.$index),true);$ids=array_merge($ids,$part['atividades']);}
            $result=['quantidade'=>count($ids),'atividades'=>$ids];
            if($ids)$db->insert('evolucao_lotes',['usuario_id'=>$actor,'chave'=>$batchKey,'hash'=>$hash,'resultado'=>json_encode($result,JSON_THROW_ON_ERROR),'created'=>gmdate('Y-m-d H:i:s')]);
            return $result;
        });
    }
}
