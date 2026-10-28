<?php
declare(strict_types=1);
namespace App\Service;

/** Weekly spreadsheet over the existing append-only percentage history. */
final class EvolucaoSemanal
{
    public function __construct(private ?\DateTimeImmutable $now=null,private ?\App\Integration\Totvs\ListOrders $source=null) {}
    private function today(): string {return ($this->now??new \DateTimeImmutable('now'))->setTimezone(new \DateTimeZone('America/Sao_Paulo'))->format('Y-m-d');}
    public function weeks(int $season): array
    {
        PeriodoContexto::season($season);$s=Access::table('Entressafras')->get($season);$today=$this->today();
        return array_values(array_filter(CurvaSemanal::weeks($s->data_inicio->format('Y-m-d'),$s->data_fim->format('Y-m-d')),static fn($w)=>$w['inicio']<=$today));
    }
    public function areas($user,int $season): array
    {
        PeriodoContexto::season($season);$areas=Access::table('AreasFabrica')->find('list')->where(['entressafra_id'=>$season])->orderBy(['nome'=>'ASC'])->all()->toArray();
        if($user->perfil!=='RESPONSAVEL_SETOR'||Access::operational($user))return $areas;
        $visible=[];foreach(Access::activityScope(Access::table('Atividades')->find()->contain(['Cadernos']),$user)->where(['Atividades.entressafra_id'=>$season])->all() as $a){$visible[(int)($a->area_curva_id??$a->caderno?->area_curva_id)]=true;}
        return array_intersect_key($areas,$visible);
    }
    private function scope($user,int $season,int $area)
    {
        $areas=$area?[$area]:array_keys($this->areas($user,$season));
        return Access::activityScope(Access::table('Atividades')->find()->leftJoinWith('Cadernos'),$user)->where(['Atividades.entressafra_id'=>$season,'COALESCE(Atividades.area_curva_id,Cadernos.area_curva_id) IN'=>$areas?:[-1]],['COALESCE(Atividades.area_curva_id,Cadernos.area_curva_id)'=>'integer']);
    }
    /** Exact cell only; absent entries stay NULL while carry-forward is handled by Evolucao::state. */
    public static function cell(array $history,array $week): ?array
    {
        $last=null;foreach($history as $r)if($r['referencia']>=$week['inicio']&&$r['referencia']<=$week['fim'])$last=$r;return $last;
    }
    public function page($user,int $season,int $area,string $search,int $page): array
    {
        if($search!==''&&!preg_match('/^[0-9]{1,6}$/D',$search))throw new \DomainException('Pesquise até seis dígitos do número da O.S.');
        if($search!=='')$area=0;$areas=$this->areas($user,$season);
        if($area&&!isset($areas[$area]))throw new \Cake\Http\Exception\ForbiddenException('Setor da Curva S não autorizado.');
        $weeks=$this->weeks($season);$rows=[];$total=0;$pages=1;$page=max(1,$page);$reference=$weeks?end($weeks)['fim']:null;
        if($area||$search!==''){
            $q=$this->scope($user,$season,$area);if($search!=='')$q->where(['Atividades.os_numero LIKE'=>'%'.$search.'%']);$total=$q->count();$pages=max(1,(int)ceil($total/50));$page=min($page,$pages);
            $e=new Evolucao();$e->loadHistory($season);
            foreach($q->orderBy(['Atividades.os_numero'=>'ASC','Atividades.id'=>'ASC'])->limit(50)->offset(($page-1)*50)->all() as $a){
                $history=$e->history((int)$a->id);$cells=[];
                foreach($weeks as $w){$cell=self::cell($history,$w);$state=$e->state((int)$a->id,$w['fim']);$cells[$w['fim']]=['valor'=>$cell===null?null:(float)$cell['percentual'],'registro_id'=>(int)($cell['id']??0),'revisao'=>(int)($cell['revisao']??0),'acumulado'=>$state['percentual']];}
                $rows[]=['id'=>(int)$a->id,'numero'=>$a->os_numero,'descricao'=>OsApresentacao::description($a->descricao_atividade,json_decode($a->snapshot_totvs??'{}',true)??[]),'bem'=>$a->bem,'percentual'=>$reference?$e->state((int)$a->id,$reference)['percentual']:0,'cells'=>$cells];
            }
            $missing=[];foreach($rows as $i=>$row)if($row['descricao']==='Descrição não informada')$missing[$row['numero']]=$i;
            if($missing)try{foreach(($this->source??new \App\Integration\Totvs\ListOrders())->find(array_map('strval',array_keys($missing))) as $number=>$note)if(isset($missing[$number]))$rows[$missing[$number]]['descricao']=OsApresentacao::description(null,$note);}catch(\DomainException){}
        }
        $today=$this->today();return compact('areas','rows','total','pages','page','reference','weeks','today');
    }
    public function save(int $actor,int $season,int $area,array $rows,string $key): array
    {
        $user=Access::actor($actor);PeriodoContexto::writable($season);
        if($area&&!isset($this->areas($user,$season)[$area]))throw new \Cake\Http\Exception\ForbiddenException('Setor não autorizado.');
        if(!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$key)||!array_is_list($rows)||count($rows)>10000)throw new \DomainException('Lote inválido.');
        $weeks=array_column($this->weeks($season),null,'fim');$clean=[];
        foreach($rows as $r){
            if(!is_array($r))throw new \DomainException('Célula inválida.');$id=filter_var($r['atividade_id']??null,FILTER_VALIDATE_INT);$ref=$r['referencia']??null;
            if(!$id||!is_string($ref)||!isset($weeks[$ref])||isset($clean[$id.':'.$ref]))throw new \DomainException('O.S./semana inválida, futura ou repetida.');
            try{$value=($r['percentual']??null)===''||($r['percentual']??null)===null?null:Evolucao::number($r['percentual']);$base=($r['base']??null)===null?null:Evolucao::number($r['base']);}
            catch(\DomainException $ex){$a=$this->scope($user,$season,$area)->where(['Atividades.id'=>$id])->first();throw new \DomainException('O.S. '.($a?->os_numero??'#'.$id).' · Semana '.$weeks[$ref]['semana'].': '.$ex->getMessage());}
            $revision=$r['revisao']??0;$record=$r['base_registro']??0;
            if(!is_scalar($revision)||!ctype_digit((string)$revision)||!is_scalar($record)||!ctype_digit((string)$record))throw new \DomainException('Revisão da célula inválida.');
            $reason=$r['motivo']??'';if(!is_string($reason)||mb_strlen($reason)>3000)throw new \DomainException('Justificativa inválida.');
            $clean[$id.':'.$ref]=['atividade_id'=>$id,'referencia'=>$ref,'percentual'=>$value,'base'=>$base,'revisao'=>(int)$revision,'base_registro'=>(int)$record,'motivo'=>trim($reason),'confirmar_reducao'=>in_array($r['confirmar_reducao']??false,[true,1,'1'],true),'legacy'=>!array_key_exists('base_registro',$r)];
        }
        ksort($clean);$hash=hash('sha256',json_encode([$season,$area,$clean],JSON_THROW_ON_ERROR));$batchKey='grade-'.$key;$db=Access::table('Atividades')->getConnection();
        return $db->transactional(function()use($actor,$season,$area,$clean,$hash,$batchKey,$db,$user,$weeks){
            if($db->getDriver() instanceof \Cake\Database\Driver\Postgres){$db->execute('SELECT id FROM entressafras WHERE id=? FOR SHARE',[$season]);$db->execute('SELECT pg_advisory_xact_lock(hashtextextended(?,0))',['pcm_grade:'.$actor.':'.$batchKey]);}
            PeriodoContexto::writable($season);
            $old=$db->execute('SELECT * FROM evolucao_lotes WHERE usuario_id=? AND chave=?',[$actor,$batchKey])->fetch('assoc');
            if($old){if($old['hash']!==$hash)throw new \DomainException('Envio já utilizado com outros valores.');return json_decode($old['resultado'],true,512,JSON_THROW_ON_ERROR);}
            $ids=array_values(array_unique(array_column($clean,'atividade_id')));sort($ids);$activities=$ids?$this->scope($user,$season,$area)->where(['Atividades.id IN'=>$ids])->all()->indexBy('id')->toArray():[];
            foreach($ids as $id){if(!isset($activities[$id]))throw new \DomainException('O.S. #'.$id.' fora do setor ou entressafra autorizados.');if($db->getDriver() instanceof \Cake\Database\Driver\Postgres)$db->execute('SELECT id FROM atividades WHERE id=? FOR UPDATE',[$id]);}
            if($ids){$activities=$this->scope($user,$season,$area)->where(['Atividades.id IN'=>$ids])->all()->indexBy('id')->toArray();if(count($activities)!==count($ids))throw new \DomainException('Uma O.S. mudou de setor ou entressafra. Recarregue a planilha.');}
            $e=new Evolucao();$changed=[];$histories=[];
            foreach($clean as $cellKey=>$r){$id=$r['atividade_id'];$ref=$r['referencia'];$w=$weeks[$ref];$label='O.S. '.$activities[$id]->os_numero.' · Semana '.$w['semana'].' ('.$w['inicio'].' a '.$w['fim'].')';$history=$histories[$id]??=$e->history($id);$cell=self::cell($history,$w);
                if($r['percentual']===null){if($cell!==null)throw new \DomainException($label.': campo vazio não apaga um lançamento salvo. Informe o percentual corrigido.');continue;}
                $current=$cell===null?null:(float)$cell['percentual'];
                if($r['legacy']&&$r['percentual']===$r['base'])continue;
                if(($r['legacy']?($e->state($id,$ref)['percentual']!==$r['base']):($current!==$r['base']||(int)($cell['id']??0)!==$r['base_registro']))||(int)($cell['revisao']??0)!==$r['revisao'])throw new \DomainException($label.': outra pessoa alterou esta célula. Recarregue e compare os valores; seu rascunho foi mantido.');
                if($current===$r['percentual'])continue;
                $changed[$cellKey]=$r+['old'=>$cell,'label'=>$label];
            }
            // Check the final proposed timeline, including other edited cells in this same batch.
            foreach($changed as $cellKey=>$r){$id=$r['atividade_id'];$ref=$r['referencia'];$history=$histories[$id];$before=0.;$after=null;
                foreach($weeks as $w){$other=$changed[$id.':'.$w['fim']]??null;$cell=self::cell($history,$w);$value=$other['percentual']??($cell===null?null:(float)$cell['percentual']);if($value===null)continue;if($w['fim']<$ref)$before=$value;elseif($w['fim']>$ref){$after=$value;break;}}
                $reduction=$r['percentual']<$before||($r['old']!==null&&$r['percentual']<(float)$r['old']['percentual'])||($after!==null&&$r['percentual']>$after);
                $changed[$cellKey]['exceptional']=$reduction;$changed[$cellKey]['acumulado_anterior']=$before;
                if($reduction&&(!$r['confirmar_reducao']||$r['motivo']===''))throw new \DomainException($r['label'].': correção excepcional do acumulado exige confirmação e justificativa.');
            }
            $saved=[];$now=($this->now??new \DateTimeImmutable('now'))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            foreach($changed as $r){$id=$r['atividade_id'];$ref=$r['referencia'];$targetRef=$r['old']['referencia']??$weeks[$ref]['inicio'];
                if($db->getDriver() instanceof \Cake\Database\Driver\Postgres)$db->execute('INSERT INTO evolucao_semanas (entressafra_id,referencia) VALUES (?,?) ON CONFLICT (entressafra_id,referencia) DO NOTHING',[$season,$targetRef]);
                elseif(!$db->execute('SELECT id FROM evolucao_semanas WHERE entressafra_id=? AND referencia=?',[$season,$targetRef])->fetch('assoc'))$db->insert('evolucao_semanas',['entressafra_id'=>$season,'referencia'=>$targetRef]);
                $week=$db->execute('SELECT id FROM evolucao_semanas WHERE entressafra_id=? AND referencia=?',[$season,$targetRef])->fetch('assoc');$revision=(int)$db->execute('SELECT MAX(revisao) AS n FROM evolucao_registros WHERE atividade_id=? AND semana_id=?',[$id,$week['id']])->fetch('assoc')['n']+1;
                $db->insert('evolucao_registros',['atividade_id'=>$id,'semana_id'=>$week['id'],'percentual'=>$r['percentual'],'revisao'=>$revision,'usuario_id'=>$actor,'observacoes'=>'Grade semanal — acumulado','motivo'=>$r['motivo'],'created'=>$now]);
                Access::audit($user,$r['old']||$r['exceptional']?'evolucao_correcao':'evolucao_lancamento',null,$r['old'],['atividade_id'=>$id,'os_numero'=>$activities[$id]->os_numero,'referencia'=>$targetRef,'fim_semana'=>$ref,'semana'=>$weeks[$ref]['semana'],'percentual'=>$r['percentual'],'acumulado_anterior'=>$r['acumulado_anterior'],'revisao'=>$revision,'confirmar_reducao'=>$r['confirmar_reducao'],'retroativo'=>$ref<$this->today()],$r['motivo']?:null);
                $saved[]=['atividade_id'=>$id,'referencia'=>$ref];
            }
            $result=['quantidade'=>count($saved),'atividades'=>array_values(array_unique(array_column($saved,'atividade_id'))),'celulas'=>$saved];
            if($saved)$db->insert('evolucao_lotes',['usuario_id'=>$actor,'chave'=>$batchKey,'hash'=>$hash,'resultado'=>json_encode($result,JSON_THROW_ON_ERROR),'created'=>$now]);return $result;
        });
    }
}
