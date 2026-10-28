<?php
declare(strict_types=1);
namespace App\Service;

use App\Integration\Totvs\{ListOrders,Orders};

final class ListLinks
{
    public function __construct(private ListOrders $source=new ListOrders()) {}
    public static function parse(string $text): array
    {
        if(strlen($text)>50000)throw new \DomainException('Lista muito extensa. Use até 2.000 números.');
        $tokens=preg_split('/[\s,;]+/u',trim($text),-1,PREG_SPLIT_NO_EMPTY);
        if(!$tokens||count($tokens)>4000)throw new \DomainException('Cole de 1 a 4.000 entradas, até 2.000 números únicos.');
        $numbers=[];
        foreach($tokens as $token){if(!preg_match('/^[0-9]{1,6}$/D',$token))throw new \DomainException('Use apenas números de O.S. de até seis dígitos.');$numbers[]=str_pad($token,6,'0',STR_PAD_LEFT);}
        $unique=array_values(array_unique($numbers));if(count($unique)>2000)throw new \DomainException('Use até 2.000 números únicos por lista.');
        return ['numbers'=>$unique,'texto_original'=>$text,'informadas'=>count($numbers),'unicas'=>count($unique),'repetidas'=>count($numbers)-count($unique)];
    }
    private function context(int $userId,int $book): array
    {
        $user=Access::actor($userId);Access::pcm($user);$notebook=Access::table('Cadernos')->get($book);$season=Access::table('Entressafras')->get($notebook->entressafra_id);
        if(!$notebook->ativo||$season->status==='encerrada')throw new \DomainException('Caderno inativo ou entressafra encerrada.');
        return compact('user','notebook','season');
    }
    private function classify(array $row,int $book,int $season): array
    {
        $old=Access::table('Atividades')->find()->where(['entressafra_id'=>$season,'os_filial'=>'01','os_numero'=>$row['numero']])->first();
        $sectors=Access::table('Setores')->find()->where(['area_totvs'=>$row['codigo_area'],'filial_totvs'=>'01'])->all();
        $automatic=$sectors->count()===0&&preg_match('/^[A-Za-z0-9_-]{1,6}$/D',(string)$row['codigo_area']);
        $state='elegivel';$reason=null;
        if((int)($row['identidades']??0)!==1||(int)$row['correspondencias_servico']>1){$state='ambigua';$reason='Número/filial ou serviço ambíguo no TOTVS.';}
        elseif($row['filial']!=='01'||!Orders::available($row)){$state='inelegivel';$reason='Exige filial 01, L/N, origem 2026 e serviço ENTRESSAFRA inequívoco.';}
        elseif(!$automatic&&($sectors->count()!==1||!$sectors->first()->ativo)){$state='ambigua';$reason='Área executante sem identificação única e ativa.';}
        elseif($old&&($automatic||(int)$old->setor_executante_id!==(int)$sectors->first()->id||$old->area_executante_totvs!==$row['codigo_area'])){$state='ambigua';$reason='Equipe executante diverge do registro local; histórico preservado.';}
        elseif($old&&$old->caderno_id!==null){$state=(int)$old->caderno_id===$book?'mesmo':'outro';$reason=$state==='mesmo'?'Já vinculada ao mesmo caderno.':'Transferência exige seleção explícita e justificativa.';}
        $current=$old?->caderno_id?Access::table('Cadernos')->get($old->caderno_id):null;
        $sectorId=$sectors->count()===1?(int)$sectors->first()->id:null;
        $associate=$sectorId&&!in_array($sectorId,Access::notebookSectors($book),true);
        return ['estado'=>$state,'motivo'=>$reason,'atividade_id'=>$old?->id,'caderno_atual'=>$old?->caderno_id,'caderno_nome'=>$current?->nome,'descricao'=>$current?->descricao,'setor_id'=>$sectorId,'equipe'=>$sectors->count()===1?$sectors->first()->nome:($automatic?'TOTVS 01 / '.$row['codigo_area']:'Não identificado'),'criar_equipe'=>(bool)$automatic,'associar_equipe'=>$associate];
    }
    public function create(int $userId,int $book,string $text): string
    {
        $ctx=$this->context($userId,$book);$parsed=self::parse($text);$token=bin2hex(random_bytes(24));$now=gmdate('Y-m-d H:i:s');
        $t=Access::table('VinculosLotes');$job=$t->saveOrFail(Access::entity($t,['token'=>$token,'usuario_id'=>$userId,'caderno_id'=>$book,'filtros'=>json_encode(['tipo'=>'lista','entressafra_id'=>(int)$ctx['season']->id]+$parsed,JSON_THROW_ON_ERROR),'estado'=>'preparando','cursor_os'=>'','created'=>$now,'modified'=>$now]));
        // Persist the token first so the preparation can be resumed after a source outage.
        return $token;
    }
    public function job(int $userId,string $token)
    {
        Access::pcm(Access::actor($userId));
        if(!preg_match('/^[a-f0-9]{48}$/D',$token))throw new \DomainException('Prévia inválida.');
        $job=Access::table('VinculosLotes')->find()->where(['usuario_id'=>$userId,'token'=>$token])->first();
        if(!$job||(json_decode($job->filtros,true)['tipo']??null)!=='lista')throw new \DomainException('Prévia não encontrada para esta conta.');
        $this->context($userId,(int)$job->caderno_id);return $job;
    }
    private function locked(int $userId,string $token)
    {
        $job=$this->job($userId,$token);$db=Access::table('VinculosLotes')->getConnection();
        if($db->getDriver() instanceof \Cake\Database\Driver\Postgres)$db->execute('SELECT id FROM vinculos_lotes WHERE id=? FOR UPDATE',[$job->id]);
        return $this->job($userId,$token);
    }
    public function prepare(int $userId,string $token): void
    {
        $db=Access::table('VinculosLotes')->getConnection();
        $db->transactional(function()use($userId,$token){
            $job=$this->locked($userId,$token);if($job->estado!=='preparando')return;
            $filters=json_decode($job->filtros,true);$offset=(int)$job->cursor_os;$numbers=array_slice($filters['numbers'],$offset,100);
            $rows=$this->source->find($numbers);$t=Access::table('VinculosItens');
            foreach($numbers as $number){
                $row=$rows[$number]??null;$classification=$row?$this->classify($row,(int)$job->caderno_id,$filters['entressafra_id']):['estado'=>'nao_encontrada','motivo'=>'Não encontrada no TOTVS filial 01.','atividade_id'=>null,'caderno_atual'=>null];
                $snapshot=['os'=>$row,'classificacao'=>$classification,'number'=>$number];
                $t->saveOrFail(Access::entity($t,['lote_id'=>$job->id,'os_chave'=>Orders::key('01',$number),'numero'=>$number,'snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR),'pesquisa'=>$number,'estado'=>$classification['estado'],'motivo'=>$classification['motivo'],'caderno_atual'=>$classification['caderno_atual']===null?null:(string)$classification['caderno_atual'],'atividade_id'=>$classification['atividade_id'],'selecionado'=>false]));
            }
            $offset+=count($numbers);$job->cursor_os=(string)$offset;$job->estado=$offset>=count($filters['numbers'])?'pronto':'preparando';Access::table('VinculosLotes')->saveOrFail($job);
        });
    }
    public function summary(int $userId,string $token,int $page=1,int $transferPage=1): array
    {
        $job=$this->job($userId,$token);$t=Access::table('VinculosItens');$q=$t->find()->where(['lote_id'=>$job->id]);$total=$q->count();$pages=max(1,(int)ceil($total/10));$page=max(1,min($page,$pages));
        $counts=[];foreach((clone $q)->select(['estado','selecionado'])->all() as $item){$counts[$item->estado]=($counts[$item->estado]??0)+1;if($item->selecionado){$counts['selecionadas']=($counts['selecionadas']??0)+1;if(in_array($item->estado,['sucesso','erro','mesmo'],true))$counts['processadas']=($counts['processadas']??0)+1;}}
        $rows=(clone $q)->orderBy(['id'=>'ASC'])->limit(10)->offset(($page-1)*10)->all();
        $transfers=(clone $q)->where(['estado'=>'outro']);$transferPages=max(1,(int)ceil(($counts['outro']??0)/50));$transferPage=max(1,min($transferPage,$transferPages));
        $transferRows=(clone $transfers)->orderBy(['id'=>'ASC'])->limit(50)->offset(($transferPage-1)*50)->all();
        $counts['transferencias_selecionadas']=(clone $transfers)->where(['selecionado'=>true])->count();
        $selectionSignature=$this->transferSignature($job);
        return compact('job','rows','counts','total','page','pages','transferRows','transferPage','transferPages','selectionSignature')+['filters'=>json_decode($job->filtros,true)];
    }
    private function transferSignature($job): string
    {
        $book=Access::table('Cadernos')->get($job->caderno_id);$items=Access::table('VinculosItens')->find()->select(['id','caderno_atual','atividade_id'])->where(['lote_id'=>$job->id,'estado'=>'outro','selecionado'=>true])->orderBy(['id'=>'ASC'])->disableHydration()->all()->toArray();
        return hash_hmac('sha256',json_encode([$job->token,$book->id,$book->entressafra_id,$book->area_curva_id,$book->ativo,$items],JSON_THROW_ON_ERROR),\Cake\Utility\Security::getSalt());
    }
    public function transferSelection(int $userId,string $token,array $selected,array $visible=[],bool $all=false,bool $keep=false): void
    {
        Access::table('VinculosLotes')->getConnection()->transactional(function()use($userId,$token,$selected,$visible,$all,$keep){
            $job=$this->locked($userId,$token);if(!in_array($job->estado,['pronto','concluido'],true))throw new \DomainException('Aguarde a prévia ou o processamento terminar.');
            $t=Access::table('VinculosItens');$where=['lote_id'=>$job->id,'estado'=>'outro'];
            foreach([...$selected,...$visible] as $id)if(!is_scalar($id)||!ctype_digit((string)$id)||(int)$id<1)throw new \DomainException('Seleção de transferência inválida.');
            $selected=array_values(array_unique(array_map('intval',$selected)));$visible=array_values(array_unique(array_map('intval',$visible)));
            if(!$all&&!$keep){if(array_diff($selected,$visible)||$t->find()->where($where+['id IN'=>$visible?:[-1]])->count()!==count($visible))throw new \DomainException('Seleção fora desta prévia de transferência.');}
            if($job->estado==='concluido'){$t->updateAll(['selecionado'=>false],['lote_id'=>$job->id]);$job->estado='pronto';Access::table('VinculosLotes')->saveOrFail($job);}
            if($all||$keep)$t->updateAll(['selecionado'=>$all&&!$keep],$where);
            else{$t->updateAll(['selecionado'=>false],$where+['id IN'=>$visible?:[-1]]);if($selected)$t->updateAll(['selecionado'=>true],$where+['id IN'=>$selected]);}
        });
    }
    public function confirmTransfers(int $userId,string $token,int $quantity,string $signature,bool $confirmed,string $reason): void
    {
        $db=Access::table('VinculosLotes')->getConnection();$db->transactional(function()use($db,$userId,$token,$quantity,$signature,$confirmed,$reason){
            $job=$this->locked($userId,$token);if(!$confirmed)throw new \DomainException('Confirme explicitamente a transferência das O.S. selecionadas.');
            if($db->getDriver() instanceof \Cake\Database\Driver\Postgres)$db->execute('SELECT id FROM cadernos WHERE id=? FOR SHARE',[$job->caderno_id]);
            if($job->estado!=='pronto'||!hash_equals($this->transferSignature($job),$signature))throw new \DomainException('A seleção ou o destino mudou. Confira novamente a prévia antes de confirmar.');
            $items=Access::table('VinculosItens')->find()->where(['lote_id'=>$job->id,'estado'=>'outro','selecionado'=>true])->orderBy(['atividade_id'=>'ASC'])->all();
            if(!$quantity||$items->count()!==$quantity)throw new \DomainException('A quantidade selecionada mudou. Confira antes de confirmar.');
            foreach($items as $item){if($db->getDriver() instanceof \Cake\Database\Driver\Postgres)$db->execute('SELECT id FROM atividades WHERE id=? FOR UPDATE',[$item->atividade_id]);
                $a=Access::table('Atividades')->get($item->atividade_id);$origin=$a->caderno_id?$db->execute('SELECT id,entressafra_id FROM cadernos WHERE id=?',[$a->caderno_id])->fetch('assoc'):null;
                $destination=Access::table('Cadernos')->get($job->caderno_id);
                if(!$origin||(int)$a->caderno_id!==(int)$item->caderno_atual||(int)$a->entressafra_id!==(int)$destination->entressafra_id||(int)$origin['entressafra_id']!==(int)$destination->entressafra_id)throw new \DomainException('O.S. '.$item->numero.': vínculo de origem mudou ou pertence a outra entressafra. Prepare uma nova prévia.');}
            $f=json_decode($job->filtros,true);$f['destino_confirmado']=['id'=>(int)$destination->id,'nome'=>$destination->nome,'area_curva_id'=>$destination->area_curva_id,'entressafra_id'=>(int)$destination->entressafra_id];$job->filtros=json_encode($f,JSON_THROW_ON_ERROR);Access::table('VinculosLotes')->saveOrFail($job);
            // This action authorizes only the transfer section, never new unlinked orders.
            Access::table('VinculosItens')->updateAll(['selecionado'=>false],['lote_id'=>$job->id,'estado !='=>'outro']);
            $this->start($userId,$token,$quantity,$reason);
        });
    }
    public function select(int $userId,string $token,array $ids,bool $all=false,bool $transfers=false): void
    {
        Access::table('VinculosLotes')->getConnection()->transactional(function()use($userId,$token,$ids,$all,$transfers){
            $job=$this->locked($userId,$token);if($job->estado!=='pronto')throw new \DomainException('Aguarde a prévia completa.');
            $t=Access::table('VinculosItens');$where=['lote_id'=>$job->id,'estado IN'=>$transfers?['outro']:['elegivel']];
            if(!$all){foreach($ids as $id)if(!is_scalar($id)||!ctype_digit((string)$id))throw new \DomainException('Seleção inválida.');$where['id IN']=$ids?:[-1];}
            $t->updateAll(['selecionado'=>true],$where);
        });
    }
    public function start(int $userId,string $token,int $quantity,string $reason): void
    {
        Access::table('VinculosLotes')->getConnection()->transactional(function()use($userId,$token,$quantity,$reason){
            $job=$this->locked($userId,$token);if(in_array($job->estado,['processando','concluido'],true))return;
            if($job->estado!=='pronto')throw new \DomainException('Aguarde a prévia completa.');
            $q=Access::table('VinculosItens')->find()->where(['lote_id'=>$job->id,'selecionado'=>true,'estado IN'=>['elegivel','outro']]);$count=$q->count();
            if(!$count||$quantity!==$count)throw new \DomainException('Confirme a quantidade total selecionada, incluindo outras páginas.');
            $transfers=(clone $q)->where(['estado'=>'outro'])->count();
            $reason=trim($reason);if(mb_strlen($reason)>3000||($transfers&&$reason===''))throw new \DomainException('Transferências exigem justificativa administrativa.');
            $f=json_decode($job->filtros,true);$f['justificativa']=$reason;$job->filtros=json_encode($f,JSON_THROW_ON_ERROR);$job->estado='processando';Access::table('VinculosLotes')->saveOrFail($job);
            Access::audit(Access::actor($userId),'lista_os_confirmada',null,null,['lote_id'=>$job->id,'quantidade'=>$count,'transferencias'=>$transfers],$reason?:'Vínculos por lista conferida.');
        });
    }
    public function confirmEligible(int $userId,string $token): void
    {
        Access::table('VinculosLotes')->getConnection()->transactional(function()use($userId,$token){
            $job=$this->locked($userId,$token);
            if($job->estado==='processando')return;
            if($job->estado==='concluido'){
                if(!Access::table('VinculosItens')->exists(['lote_id'=>$job->id,'estado'=>'elegivel']))return;
                $job->estado='pronto';Access::table('VinculosLotes')->saveOrFail($job);
            }
            if($job->estado!=='pronto')throw new \DomainException('Aguarde a conferência completa das O.S.');
            $items=Access::table('VinculosItens');
            $quantity=$items->find()->where(['lote_id'=>$job->id,'estado'=>'elegivel'])->count();
            if(!$quantity)throw new \DomainException('Nenhuma O.S. disponível para novo vínculo. Confira as pendências.');
            // Never inherit a transfer selection from the separate admin tool.
            $items->updateAll(['selecionado'=>false],['lote_id'=>$job->id]);
            $items->updateAll(['selecionado'=>true],['lote_id'=>$job->id,'estado'=>'elegivel']);
            $this->start($userId,$token,$quantity,'');
        });
    }
    public function clear(int $userId,string $token): void
    {
        Access::table('VinculosLotes')->getConnection()->transactional(function()use($userId,$token){
            $job=$this->locked($userId,$token);if($job->estado!=='pronto')throw new \DomainException('Seleção já confirmada.');
            Access::table('VinculosItens')->updateAll(['selecionado'=>false],['lote_id'=>$job->id]);
        });
    }
    public function process(int $userId,string $token): void
    {
        $job=$this->job($userId,$token);if($job->estado==='concluido')return;if($job->estado!=='processando')throw new \DomainException('Confirme a lista antes de gravar.');
        $items=Access::table('VinculosItens')->find()->where(['lote_id'=>$job->id,'selecionado'=>true,'estado IN'=>['elegivel','outro']])->orderBy(['id'=>'ASC'])->limit(20)->all();
        if(!$items->count()){$job->estado='concluido';Access::table('VinculosLotes')->saveOrFail($job);return;}
        $numbers=array_map(fn($i)=>$i->numero,$items->toList());$source=$this->source->find($numbers);
        foreach($items as $item) {
            $db=Access::table('Atividades')->getConnection();
            try{$db->transactional(function()use($db,$userId,$token,$item,$source){
                $job=$this->locked($userId,$token);$t=Access::table('VinculosItens');$item=$t->get($item->id);if(!in_array($item->estado,['elegivel','outro'],true)||!$item->selecionado)return;
                $ctx=$this->context($userId,(int)$job->caderno_id);$season=(int)$ctx['season']->id;
                if($db->getDriver() instanceof \Cake\Database\Driver\Postgres)$db->execute('SELECT id FROM entressafras WHERE id=? FOR UPDATE',[$season]);
                if($db->getDriver() instanceof \Cake\Database\Driver\Postgres){$destination=$db->execute('SELECT id,entressafra_id FROM cadernos WHERE id=? FOR SHARE',[$job->caderno_id])->fetch('assoc');if(!$destination||(int)$destination['entressafra_id']!==$season)throw new \DomainException('O destino mudou de entressafra. Prepare nova prévia.');$ctx=$this->context($userId,(int)$job->caderno_id);}
                $row=$source[$item->numero]??null;if(!$row)throw new \DomainException('O.S. não encontrada na revalidação.');
                if($db->getDriver() instanceof \Cake\Database\Driver\Postgres)$db->execute('SELECT pg_advisory_xact_lock(hashtext(?))',['pcm_equipe:'.$row['filial'].':'.$row['codigo_area']]);
                if($item->atividade_id&&$db->getDriver() instanceof \Cake\Database\Driver\Postgres)$db->execute('SELECT id FROM atividades WHERE id=? FOR UPDATE',[$item->atividade_id]);
                $now=$this->classify($row,(int)$job->caderno_id,$season);$preview=json_decode($item->snapshot,true)['classificacao'];
                $original=json_decode($item->snapshot,true)['os'];
                $f=json_decode($job->filtros,true);
                if(isset($f['destino_confirmado'])&&$f['destino_confirmado']!==['id'=>(int)$ctx['notebook']->id,'nome'=>$ctx['notebook']->nome,'area_curva_id'=>$ctx['notebook']->area_curva_id,'entressafra_id'=>$season])throw new \DomainException('O caderno de destino mudou depois da confirmação. Prepare nova prévia.');
                if($now['estado']==='mesmo'){$item->estado='mesmo';$item->motivo='Já vinculada por outro processamento; sem duplicar.';$t->saveOrFail($item);return;}
                if(!in_array($now['estado'],['elegivel','outro'],true)||$now['caderno_atual']!==$preview['caderno_atual']||$now['atividade_id']!==$preview['atividade_id']||($now['setor_id']!==$preview['setor_id']&&empty($preview['criar_equipe']))||$row['codigo_area']!==$original['codigo_area']||$row['filial']!==$original['filial'])throw new \DomainException('Identidade, situação, equipe ou vínculo mudou. Prepare nova prévia.');
                if($now['estado']==='outro'&&trim($f['justificativa']??'')==='')throw new \DomainException('Transferência não autorizada.');
                if($now['criar_equipe']){
                    $teams=Access::table('Setores');$team=$teams->saveOrFail(Access::entity($teams,['nome'=>'TOTVS '.$row['filial'].' / '.$row['codigo_area'],'area_totvs'=>$row['codigo_area'],'filial_totvs'=>$row['filial'],'ativo'=>true]));
                    $now['setor_id']=(int)$team->id;$now['associar_equipe']=true;
                    Access::audit($ctx['user'],'equipe_identificada_totvs',null,null,$team->toArray(),'Código original STJ010.TJ_CODAREA, confirmado na consulta da O.S.');
                }
                $activities=Access::table('Atividades');$a=$now['atividade_id']?$activities->get($now['atividade_id']):null;$before=$a?->toArray();
                if($a?->caderno_id){$originSeason=$db->execute('SELECT entressafra_id FROM cadernos WHERE id=?',[$a->caderno_id])->fetch('assoc');if(!$originSeason||(int)$originSeason['entressafra_id']!==$season)throw new \DomainException('Caderno de origem de outra entressafra. Histórico preservado.');}
                if(!$a){$stamp=gmdate('Y-m-d H:i:s');$a=Access::entity($activities,['entressafra_id'=>$season,'setor_executante_id'=>$now['setor_id'],'area_executante_totvs'=>$row['codigo_area'],'identificacao_setor'=>'area_totvs','os_chave'=>$row['chave'],'os_numero'=>$row['numero'],'os_filial'=>'01','servico'=>$row['servico'],'bem'=>$row['bem'],'situacao_totvs'=>$row['situacao'],'disponivel_totvs'=>true,'snapshot_totvs'=>json_encode($row,JSON_THROW_ON_ERROR),'criado_por'=>$userId,'created'=>$stamp,'modified'=>$stamp]);}
                if($now['associar_equipe']) {
                    $links=Access::table('CadernosSetores');$link=$links->saveOrFail(Access::entity($links,['caderno_id'=>$job->caderno_id,'setor_id'=>$now['setor_id']]));
                    Access::audit($ctx['user'],'equipe_caderno_lista',null,null,$link->toArray(),'Associação administrativa exibida na prévia e confirmada junto ao vínculo. Permissões por O.S. permanecem na equipe TOTVS.');
                }
                $a->caderno_id=$job->caderno_id;$activities->saveOrFail($a);
                $origin=$before&&$before['caderno_id']?Access::table('Cadernos')->get($before['caderno_id']):null;
                Access::audit($ctx['user'],$now['estado']==='outro'?'transferencia_caderno_lista':'vinculo_caderno_lista',null,$before,$a->toArray()+['atividade_id'=>(int)$a->id,'caderno_origem'=>['id'=>$origin?->id,'nome'=>$origin?->nome],'caderno_destino'=>['id'=>$ctx['notebook']->id,'nome'=>$ctx['notebook']->nome]],$f['justificativa']?:'Lista conferida pelo PCM; equipe executante preservada.');
                $item->atividade_id=$a->id;$item->estado='sucesso';$item->motivo=null;$t->saveOrFail($item);
            });}catch(\DomainException $e){$t=Access::table('VinculosItens');$failed=$t->get($item->id);$failed->estado='erro';$failed->motivo=$e->getMessage();$t->saveOrFail($failed);}
        }
    }
}
