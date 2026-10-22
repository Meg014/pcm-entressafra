<?php
declare(strict_types=1);
namespace App\Service;
use App\Integration\Totvs\GatewayInterface;
final class Activities
{
    public function __construct(private GatewayInterface $totvs) {}
    public function search(int $userId,int $sectorId,string $term): array
    {
        $user=Access::actor($userId); Access::sector($user,$sectorId);
        $sector=Access::table('Setores')->get($sectorId);
        if(!$sector->ativo || trim((string)$sector->area_totvs)==='' || $sector->filial_totvs===null) { throw new \DomainException('Configure a área TJ_CODAREA e a filial no cadastro do setor.'); }
        $rows=$this->totvs->orders($term,(string)$sector->area_totvs,(string)$sector->filial_totvs);
        return array_slice(array_values(array_filter($rows,fn($row)=>($row['filial']??null)===$sector->filial_totvs && ($row['codigo_area']??null)===$sector->area_totvs && \App\Integration\Totvs\Orders::available($row))),0,50);
    }
    public function searchNotebook(int $userId,int $notebookId,string $term,?int $sectorId=null): array
    {
        $user=Access::actor($userId); Access::notebook($user,$notebookId);
        $book=Access::table('Cadernos')->get($notebookId);
        if (!$book->ativo) { throw new \DomainException('Caderno inativo.'); }
        $ids=array_values(array_intersect(Access::notebookSectors($notebookId),Access::sectors($user)));
        if ($sectorId!==null) {
            if (!in_array($sectorId,$ids,true)) { throw new \DomainException('O setor executante não está associado ao caderno ou autorizado.'); }
            $ids=[$sectorId];
        }
        $rows=[];
        $configured=0;
        foreach ($ids as $id) {
            $sector=Access::table('Setores')->get($id);
            if ($sectorId===null && (!$sector->ativo || trim((string)$sector->area_totvs)==='' || $sector->filial_totvs===null)) { continue; }
            ++$configured;
            foreach ($this->search($userId,$id,$term) as $row) { $rows[$row['chave']]=$row; }
        }
        if (!$configured) { throw new \DomainException('Configure a área/filial de pelo menos um setor autorizado associado ao caderno.'); }
        return array_slice(array_values($rows),0,50);
    }
    public function link(int $userId, int $notebookId, string $key, ?\Closure $validate = null): int
    {
        $user = Access::actor($userId);
        $caderno = Access::table('Cadernos')->get($notebookId);
        Access::notebook($user, $notebookId);
        if (!$caderno->ativo) { throw new \DomainException('O caderno está inativo.'); }
        $row = $this->totvs->order($key);
        if ($validate) { $validate($row); }
        foreach (['chave', 'numero', 'filial', 'servico', 'bem', 'situacao'] as $field) {
            if (!isset($row[$field]) || !is_string($row[$field]) || (!in_array($field,['filial','bem'],true) && trim($row[$field]) === '')) { throw new \DomainException('Identificação da O.S. incompleta ou ambígua no TOTVS.'); }
        }
        if ($row['chave'] !== $key || strlen($key) > 255) { throw new \DomainException('Identificação da O.S. inválida.'); }
        $table = Access::table('Atividades');
        $existing=$table->find()->where(['entressafra_id'=>$caderno->entressafra_id,'OR'=>[['os_chave'=>$key],['os_numero'=>$row['numero'],'os_filial'=>$row['filial']]]])->first();
        if($existing && $existing->caderno_id!==null) { throw new \DomainException('Esta O.S. já está vinculada a um caderno. Solicite revisão pelo PCM antes de criar outro vínculo.'); }
        if($existing){Access::pcm($user);return $this->classify($userId,(int)$existing->id,$notebookId);}
        if(($row['disponivel']??false)!==true || !\App\Integration\Totvs\Orders::available($row)) { throw new \DomainException('O.S. indisponível para novo vínculo: exige L, término N, origem em 2026 e serviço ENTRESSAFRA inequívoco.'); }
        $sector=Access::table('Setores')->find()->where(['area_totvs'=>$row['codigo_area']??'','filial_totvs'=>$row['filial']])->first();
        if(!$sector || !$sector->ativo || !in_array((int)$sector->id,Access::notebookSectors($notebookId),true)) { throw new \DomainException('Associe ao caderno o setor correspondente à área TJ_CODAREA e à filial desta O.S. O centro de custo não define o caderno.'); }
        Access::sector($user,(int)$sector->id);
        return $table->getConnection()->transactional(function () use ($table, $user, $row, $notebookId, $sector, $caderno): int {
            if($table->getConnection()->getDriver() instanceof \Cake\Database\Driver\Postgres)$table->getConnection()->execute('SELECT id FROM entressafras WHERE id=? FOR UPDATE',[$caderno->entressafra_id]);
            $registered=$table->find()->where(['entressafra_id'=>$caderno->entressafra_id,'os_filial'=>$row['filial'],'os_numero'=>$row['numero']])->first();
            if($registered){if($registered->caderno_id!==null)throw new \DomainException('O.S. já vinculada por outra usuária.');Access::pcm($user);return $this->classify((int)$user->id,(int)$registered->id,$notebookId);}
            $now = gmdate('Y-m-d H:i:s');
            try {
                $entity = $table->saveOrFail(Access::entity($table, ['caderno_id' => $notebookId,'setor_executante_id'=>$sector->id,'area_executante_totvs'=>$row['codigo_area'],'identificacao_setor'=>'area_totvs', 'os_chave' => $row['chave'], 'os_numero' => $row['numero'], 'os_filial' => $row['filial'], 'servico' => $row['servico'], 'bem' => $row['bem'], 'situacao_totvs' => $row['situacao'], 'disponivel_totvs'=>true, 'snapshot_totvs' => json_encode($row, JSON_THROW_ON_ERROR), 'criado_por' => $user->id, 'created' => $now, 'modified' => $now]));
            } catch (\Cake\Database\Exception\QueryException $e) {
                if (str_contains($e->getMessage(), '23505')) { throw new \DomainException('O.S. já vinculada por outra usuária. Solicite revisão pelo PCM.'); }
                throw $e;
            }
            Access::audit($user, 'vinculo_os', null, null, ['atividade_id' => $entity->id, 'os_chave' => $row['chave'], 'caderno_id' => $notebookId], null);
            return (int)$entity->id;
        });
    }
    public function synchronize(int $userId,int $id): void
    {
        $user=Access::actor($userId); Access::pcm($user);
        $table=Access::table('Atividades'); $activity=$table->get($id);
        $row=$this->totvs->order($activity->os_chave);
        if(($row['chave']??null)!==$activity->os_chave || ($row['numero']??null)!==$activity->os_numero || ($row['filial']??null)!==$activity->os_filial) { throw new \DomainException('Identificação da O.S. divergente. Histórico preservado.'); }
        $table->getConnection()->transactional(function() use($table,$user,$id,$row):void {
            if($table->getConnection()->getDriver() instanceof \Cake\Database\Driver\Postgres) { $table->getConnection()->execute('SELECT id FROM atividades WHERE id=? FOR UPDATE',[$id]); }
            $activity=$table->get($id); $before=$activity->toArray();
            $snapshot=json_decode($activity->snapshot_totvs,true,512,JSON_THROW_ON_ERROR);
            $snapshot['ultima_sincronizacao']=$row; $snapshot['sincronizado_em']=gmdate('Y-m-d H:i:s');
            $sector=Access::table('Setores')->find()->where(['area_totvs'=>$row['codigo_area'],'filial_totvs'=>$row['filial']])->first();
            $sameArea=$activity->area_executante_totvs===null || $activity->area_executante_totvs===$row['codigo_area'];
            if (($activity->setor_executante_id===null || (int)$activity->setor_executante_id===(int)$sector?->id) && $sameArea && $sector && (!$activity->caderno_id || in_array((int)$sector->id,Access::notebookSectors((int)$activity->caderno_id),true))) {
                $activity->setor_executante_id=$sector->id; $activity->area_executante_totvs=$row['codigo_area']; $activity->identificacao_setor='area_totvs';
            }
            $sameSector=$sector && (int)$activity->setor_executante_id===(int)$sector->id;
            $activity->snapshot_totvs=json_encode($snapshot,JSON_THROW_ON_ERROR); $activity->situacao_totvs=$row['situacao']; $activity->disponivel_totvs=$sameArea && $sameSector && \App\Integration\Totvs\Orders::available($row); $activity->modified=gmdate('Y-m-d H:i:s');
            $table->saveOrFail($activity);
            Access::audit($user,'sincronizacao_os',null,$before,$activity->toArray(), 'Consulta somente leitura da situação no TOTVS. Sem alteração de avanço físico.');
        });
    }
    public function identify(int $userId,string $key): int
    {
        $u=Access::actor($userId);$row=$this->totvs->order($key);
        if(($row['chave']??null)!==$key||($row['filial']??'')!=='01'||!\App\Integration\Totvs\Orders::available($row)||($row['disponivel']??false)!==true)throw new \DomainException('O.S. inelegível ou ambígua no TOTVS.');
        $sectors=Access::table('Setores')->find()->where(['area_totvs'=>$row['codigo_area'],'filial_totvs'=>'01','ativo'=>true])->all();if($sectors->count()!==1)throw new \DomainException('Área executante sem mapeamento inequívoco.');$sector=$sectors->first();Access::sector($u,(int)$sector->id);
        $seasons=Access::table('Entressafras')->find()->where(PeriodoContexto::id()!==null?['id'=>PeriodoContexto::id()]:['status'=>'ativa'])->all();if($seasons->count()!==1)throw new \DomainException('Configure exatamente uma entressafra ativa para identificar a O.S.');$season=$seasons->first();PeriodoContexto::writable((int)$season->id);$t=Access::table('Atividades');
        return $t->getConnection()->transactional(function()use($u,$row,$sector,$season,$t){
            if($t->getConnection()->getDriver() instanceof \Cake\Database\Driver\Postgres)$t->getConnection()->execute('SELECT id FROM entressafras WHERE id=? FOR UPDATE',[$season->id]);
            $old=$t->find()->where(['entressafra_id'=>$season->id,'os_numero'=>$row['numero'],'os_filial'=>'01'])->first();
            if($old){if((int)$old->setor_executante_id!==(int)$sector->id||$old->area_executante_totvs!==$row['codigo_area'])throw new \DomainException('Área executante divergente do registro local. Solicite conferência ao PCM.');if($old->disponivel_totvs!==true){$before=$old->toArray();$old->disponivel_totvs=true;$old->situacao_totvs=$row['situacao'];$t->saveOrFail($old);Access::audit($u,'revalidacao_os',null,$before,$old->toArray(),'Situação aberta revalidada por SELECT no TOTVS.');}return (int)$old->id;}
            $now=gmdate('Y-m-d H:i:s');$a=$t->saveOrFail(Access::entity($t,['entressafra_id'=>$season->id,'caderno_id'=>null,'setor_executante_id'=>$sector->id,'area_executante_totvs'=>$row['codigo_area'],'identificacao_setor'=>'area_totvs','os_chave'=>$row['chave'],'os_numero'=>$row['numero'],'os_filial'=>'01','servico'=>$row['servico'],'bem'=>$row['bem'],'situacao_totvs'=>$row['situacao'],'disponivel_totvs'=>true,'snapshot_totvs'=>json_encode($row,JSON_THROW_ON_ERROR),'criado_por'=>$u->id,'created'=>$now,'modified'=>$now]));Access::audit($u,'identificacao_os',null,null,['atividade_id'=>$a->id,'entressafra_id'=>$season->id,'setor_id'=>$sector->id],null);return (int)$a->id;
        });
    }
    public function classify(int $userId,int $id,int $bookId): int
    {
        $u=Access::actor($userId);Access::pcm($u);$t=Access::table('Atividades');
        return $t->getConnection()->transactional(function()use($u,$t,$id,$bookId){
            if($t->getConnection()->getDriver() instanceof \Cake\Database\Driver\Postgres)$t->getConnection()->execute('SELECT id FROM atividades WHERE id=? FOR UPDATE',[$id]);
            $a=$t->get($id);$b=Access::table('Cadernos')->get($bookId);
            if($a->caderno_id!==null)throw new \DomainException('A O.S. já tem caderno; não será transferida automaticamente.');
            if(!$b->ativo||(int)$b->entressafra_id!==(int)$a->entressafra_id||!in_array((int)$a->setor_executante_id,Access::notebookSectors($bookId),true))throw new \DomainException('Caderno incompatível com entressafra ou setor executante.');
            $before=$a->toArray();$a->caderno_id=$bookId;$t->saveOrFail($a);Access::audit($u,'classificacao_caderno',null,$before,$a->toArray(),'Classificação administrativa posterior; históricos preservados.');return $id;
        });
    }
}
