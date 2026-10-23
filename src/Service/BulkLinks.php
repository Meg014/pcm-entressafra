<?php
declare(strict_types=1);
namespace App\Service;

use App\Integration\Totvs\BulkOrders;
use App\Integration\Totvs\Gateway;
use App\Integration\Totvs\GatewayInterface;
use App\Integration\Totvs\Orders;

final class BulkLinks
{
    public function __construct(private BulkOrders $source=new BulkOrders(), private GatewayInterface $gateway=new Gateway()) {}
    private function jobs() { return Access::table('VinculosLotes'); }
    private function items() { return Access::table('VinculosItens'); }
    private function save($table,array $data) { return $table->saveOrFail(Access::entity($table,$data)); }
    private function id($value): int
    {
        if (!is_scalar($value) || !ctype_digit((string)$value) || (int)$value<1) { throw new \DomainException('Selecione entressafra, setor e caderno válidos.'); }
        return (int)$value;
    }
    public function context(int $userId,array $input): array
    {
        $user=Access::actor($userId); Access::pcm($user);
        $season=Access::table('Entressafras')->get($this->id($input['entressafra_id']??null));
        $sector=Access::table('Setores')->get($this->id($input['setor_id']??null));
        $notebook=Access::table('Cadernos')->get($this->id($input['caderno_id']??null));
        Access::sector($user,(int)$sector->id);
        if (!$sector->ativo || !$notebook->ativo || $season->status==='encerrada' || !in_array((int)$sector->id,Access::notebookSectors((int)$notebook->id),true) || (int)$notebook->entressafra_id!==(int)$season->id) { throw new \DomainException('Caderno, setor ou entressafra incompatível/inativo.'); }
        if (trim((string)$sector->area_totvs)==='' || $sector->filial_totvs===null) { throw new \DomainException('Configure Área TOTVS e Filial do setor.'); }
        return ['user'=>$user,'notebook'=>$notebook,'sector'=>$sector,'season'=>$season];
    }
    public function services(int $userId,array $input): array
    {
        $ctx=$this->context($userId,$input);
        return $this->source->services($ctx['sector']->area_totvs,$ctx['sector']->filial_totvs);
    }
    public function create(int $userId,array $input): string
    {
        $ctx=$this->context($userId,$input);
        $codes=$input['servicos']??[];
        if (!is_array($codes) || !$codes || count($codes)>20) { throw new \DomainException('Selecione de 1 a 20 serviços.'); }
        $available=array_column($this->services($userId,$input),'codigo');
        foreach ($codes as $code) { if (!is_string($code) || !in_array($code,$available,true)) { throw new \DomainException('Serviço não disponível para a área/filial selecionada. Atualize os serviços.'); } }
        $filters=['entressafra_id'=>(int)$ctx['season']->id,'setor_id'=>(int)$ctx['sector']->id,'caderno_id'=>(int)$ctx['notebook']->id,'area'=>$ctx['sector']->area_totvs,'filial'=>$ctx['sector']->filial_totvs,'servicos'=>array_values(array_unique($codes))];
        foreach (['cc','bem','busca'] as $field) {
            $value=$input[$field]??'';
            if (!is_string($value) || mb_strlen($value)>60) { throw new \DomainException('Filtros devem ter até 60 caracteres.'); }
            $filters[$field]=trim($value);
        }
        $token=bin2hex(random_bytes(24)); $now=gmdate('Y-m-d H:i:s');
        $this->save($this->jobs(),['token'=>$token,'usuario_id'=>$userId,'caderno_id'=>$ctx['notebook']->id,'filtros'=>json_encode($filters,JSON_THROW_ON_ERROR),'estado'=>'preparando','cursor_os'=>'','created'=>$now,'modified'=>$now]);
        return $token;
    }
    private function lock($job): void
    {
        $db=$this->jobs()->getConnection();
        if ($db->getDriver() instanceof \Cake\Database\Driver\Postgres) { $db->execute('SELECT id FROM vinculos_lotes WHERE id=? FOR UPDATE',[$job->id]); }
    }
    public function job(int $userId,string $token)
    {
        $user=Access::actor($userId); Access::pcm($user);
        if (!preg_match('/^[a-f0-9]{48}$/D',$token)) { throw new \DomainException('Prévia inválida.'); }
        $job=$this->jobs()->find()->where(['token'=>$token,'usuario_id'=>$userId])->first();
        if (!$job) { throw new \DomainException('Prévia não encontrada para este usuário.'); }
        return $job;
    }
    private function filters($job): array { return json_decode($job->filtros,true,512,JSON_THROW_ON_ERROR); }
    private function checkedContext(int $userId,$job): array
    {
        $filters=$this->filters($job); $ctx=$this->context($userId,$filters);
        if ($ctx['sector']->area_totvs!==$filters['area'] || $ctx['sector']->filial_totvs!==$filters['filial']) { throw new \DomainException('O mapeamento do setor mudou. Prepare outra prévia.'); }
        return $ctx;
    }
    private function reason(array $row,array $filters): ?string
    {
        if (!Orders::available($row)) { return 'O.S. não atende a L/N, origem 2026 e serviço ENTRESSAFRA inequívoco.'; }
        if (($row['codigo_area']??null)!==$filters['area'] || ($row['filial']??null)!==$filters['filial']) { return 'Área ou filial incompatível com o setor.'; }
        if (!in_array($row['codigo_servico']??null,$filters['servicos'],true)) { return 'Serviço não corresponde à seleção confirmada.'; }
        foreach (['cc'=>'centro_custo','bem'=>'bem'] as $filter=>$field) {
            if (($filters[$filter]??'')!=='' && mb_stripos((string)($row[$field]??''),$filters[$filter])===false) { return 'O.S. não corresponde aos filtros adicionais.'; }
        }
        $term=$filters['busca']??'';
        if ($term!=='' && mb_stripos($row['numero'],$term)===false && mb_stripos($row['servico'],$term)===false) { return 'O.S. não corresponde à pesquisa confirmada.'; }
        return null;
    }
    public function prepare(int $userId,string $token): array
    {
        $this->jobs()->getConnection()->transactional(function() use($userId,$token) {
            $job=$this->job($userId,$token); $this->lock($job); $job=$this->job($userId,$token);
            if ($job->estado!=='preparando') { return; }
            $this->checkedContext($userId,$job); $filters=$this->filters($job);
            $page=$this->source->page($filters,$job->cursor_os);
            if ($page['has_more'] && $page['cursor']===$job->cursor_os) { throw new \DomainException('Paginação da fonte não avançou. Nenhum vínculo foi criado.'); }
            foreach ($page['rows'] as $row) {
                if ($this->items()->exists(['lote_id'=>$job->id,'os_chave'=>$row['chave']])) { continue; }
                $reason=$this->reason($row,$filters);
                if ((int)($row['identidades']??0)!==1) { $reason='Número/filial possui registros ambíguos no TOTVS; conferir com o PCM.'; }
                $existing=Access::table('Atividades')->find()->contain(['Cadernos'=>['Entressafras']])->where(['Atividades.entressafra_id'=>$filters['entressafra_id'],'OR'=>[['os_chave'=>$row['chave']],['os_numero'=>$row['numero'],'os_filial'=>$row['filial']]]])->first();
                $linked=$existing && $existing->caderno_id!==null; $current=$linked ? $existing->caderno->nome.' / '.$existing->caderno->entressafra->nome : null;
                $this->save($this->items(),['lote_id'=>$job->id,'os_chave'=>$row['chave'],'numero'=>$row['numero'],'snapshot'=>json_encode($row,JSON_THROW_ON_ERROR),'pesquisa'=>mb_strtolower(implode(' ',[$row['numero'],$row['codigo_servico'],$row['servico'],$row['bem'],$row['centro_custo']]),'UTF-8'),'estado'=>$linked?'vinculada':($reason?'bloqueada':'elegivel'),'motivo'=>$linked?'Já vinculada. Não será transferida para outro caderno.':$reason,'caderno_atual'=>$current,'selecionado'=>false]);
            }
            $job->cursor_os=$page['cursor']; $job->estado=$page['has_more']?'preparando':'pronto'; $job->modified=gmdate('Y-m-d H:i:s'); $this->jobs()->saveOrFail($job);
        });
        return $this->summary($userId,$token);
    }
    private function matches(int $jobId,string $search='')
    {
        if (mb_strlen($search)>60) { throw new \DomainException('Pesquisa de até 60 caracteres.'); }
        $query=$this->items()->find()->where(['lote_id'=>$jobId]);
        if ($search!=='') {
            // Portable literal substring; user wildcards are escaped with SQL ESCAPE.
            $pattern='%'.strtr(mb_strtolower($search,'UTF-8'),['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
            $query->where(fn($exp)=>$exp->add('pesquisa LIKE :preview_search ESCAPE \'!\''))->bind(':preview_search',$pattern,'string');
        }
        return $query;
    }
    public function summary(int $userId,string $token,string $search='',int $page=1): array
    {
        $job=$this->job($userId,$token); $filters=$this->filters($job);
        $counts=['encontradas'=>0,'selecionadas'=>0,'elegiveis'=>0,'ja_vinculadas'=>0,'bloqueadas'=>0,'vinculadas'=>0,'falhas'=>0];
        foreach ($this->items()->find()->select(['estado','selecionado'])->where(['lote_id'=>$job->id])->all() as $item) {
            ++$counts['encontradas']; if ($item->selecionado) { ++$counts['selecionadas']; }
            $key=match($item->estado) {'elegivel'=>'elegiveis','vinculada'=>'ja_vinculadas','bloqueada'=>'bloqueadas','sucesso'=>'vinculadas','erro'=>'falhas',default=>null};
            if ($key) { ++$counts[$key]; }
        }
        $query=$this->matches((int)$job->id,$search); $total=$query->count(); $pages=max(1,(int)ceil($total/10)); $page=max(1,min($page,$pages));
        $eligible=(clone $query)->where(['estado'=>'elegivel'])->count(); $rows=[];
        foreach ($query->orderBy(['numero'=>'ASC','id'=>'ASC'])->limit(10)->offset(($page-1)*10)->all() as $item) {
            $row=json_decode($item->snapshot,true,512,JSON_THROW_ON_ERROR);
            $local=Access::table('Atividades')->find()->where(['entressafra_id'=>$filters['entressafra_id'],'os_numero'=>$row['numero'],'os_filial'=>$row['filial']])->first();
            $presentation=$local?OsApresentacao::activity($local,$row):OsApresentacao::source($row);
            $rows[]=['id'=>(string)$item->id,'estado'=>$item->estado,'selecionado'=>$item->selecionado,'motivo'=>OsApresentacao::texto((string)$item->motivo),'caderno_atual'=>$item->caderno_atual,'os'=>$row+$presentation];
        }
        $notebook=Access::table('Cadernos')->get($job->caderno_id);
        return ['token'=>$token,'estado'=>$job->estado,'contagens'=>$counts,'destino'=>$notebook->nome,'entressafra_nome'=>Access::table('Entressafras')->get($notebook->entressafra_id)->nome,'setor_nome'=>Access::table('Setores')->get($filters['setor_id'])->nome,'filtros'=>$filters,'rows'=>$rows,'pagina'=>$page,'paginas'=>$pages,'filtradas'=>$total,'elegiveis_filtradas'=>$eligible];
    }
    public function select(int $userId,string $token,array $input): array
    {
        if (!is_string($input['busca']??'')) { throw new \DomainException('Pesquisa inválida.'); }
        $this->jobs()->getConnection()->transactional(function() use($userId,$token,$input) {
            $job=$this->job($userId,$token); $this->lock($job); $job=$this->job($userId,$token);
            if ($job->estado!=='pronto') { throw new \DomainException('A seleção exige uma prévia pronta e ainda não confirmada.'); }
            $this->checkedContext($userId,$job);
            $selected=($input['selecionado']??false)===true;
            $query=$this->matches((int)$job->id,(string)($input['busca']??''))->where(['estado'=>'elegivel']);
            if (($input['todas']??false)===true) {
                if ($selected && (int)($input['quantidade_confirmada']??-1)!==$query->count()) { throw new \DomainException('Confirme a quantidade atual de O.S. elegíveis antes de selecionar todas.'); }
            } else {
                $ids=$input['ids']??[];
                if (!is_array($ids) || !$ids || count($ids)>50) { throw new \DomainException('Seleção inválida.'); }
                $ids=array_map(fn($id)=>$this->id($id),$ids); $query->where(['id IN'=>$ids]);
                if ($query->count()!==count(array_unique($ids))) { throw new \DomainException('A seleção contém uma O.S. bloqueada ou de outra prévia.'); }
            }
            $this->items()->updateAll(['selecionado'=>$selected],['id IN'=>$query->select(['id'])]);
        });
        return $this->summary($userId,$token,(string)($input['busca']??''),(int)($input['pagina']??1));
    }
    public function start(int $userId,string $token,int $confirmed): array
    {
        $this->jobs()->getConnection()->transactional(function() use($userId,$token,$confirmed) {
            $job=$this->job($userId,$token); $this->lock($job); $job=$this->job($userId,$token);
            if (in_array($job->estado,['processando','concluido'],true)) { return; }
            if ($job->estado!=='pronto') { throw new \DomainException('Aguarde a conclusão da prévia.'); }
            $ctx=$this->checkedContext($userId,$job);
            $count=$this->items()->find()->where(['lote_id'=>$job->id,'estado'=>'elegivel','selecionado'=>true])->count();
            if ($count<1 || $count!==$confirmed) { throw new \DomainException('Confirme a quantidade exata de O.S. selecionadas.'); }
            $job->estado='processando'; $this->jobs()->saveOrFail($job);
            Access::audit($ctx['user'],'inicio_vinculo_lote',null,null,['lote_id'=>$job->id,'caderno_id'=>$job->caderno_id,'quantidade'=>$count,'filtros'=>$this->filters($job)],'Confirmação explícita do PCM.');
        });
        return $this->summary($userId,$token);
    }
    public function process(int $userId,string $token): array
    {
        $deadline=microtime(true)+8;
        for ($i=0;$i<5 && microtime(true)<$deadline;$i++) {
            $itemId=null;
            try {
                $done=$this->jobs()->getConnection()->transactional(function() use($userId,$token,&$itemId) {
                    $job=$this->job($userId,$token); $this->lock($job); $job=$this->job($userId,$token);
                    if ($job->estado==='concluido') { return 'done'; }
                    if ($job->estado!=='processando') { throw new \DomainException('Confirme a vinculação antes de processar.'); }
                    $item=$this->items()->find()->where(['lote_id'=>$job->id,'estado'=>'elegivel','selecionado'=>true])->orderBy(['id'=>'ASC'])->first();
                    if (!$item) {
                        $job->estado='concluido'; $this->jobs()->saveOrFail($job);
                        Access::audit(Access::actor($userId),'fim_vinculo_lote',null,null,['lote_id'=>$job->id,'caderno_id'=>$job->caderno_id],null); return 'done';
                    }
                    $itemId=(int)$item->id;
                    $ctx=$this->checkedContext($userId,$job); $filters=$this->filters($job);
                    $activity=(new Activities($this->gateway))->link($userId,(int)$job->caderno_id,$item->os_chave,function($row) use($filters) {
                        if ($reason=$this->reason($row,$filters)) { throw new \DomainException($reason); }
                    });
                    $item->estado='sucesso'; $item->atividade_id=$activity; $item->motivo=null; $this->items()->saveOrFail($item);
                    Access::audit($ctx['user'],'item_vinculo_lote',null,null,['lote_id'=>$job->id,'item_id'=>$item->id,'atividade_id'=>$activity,'caderno_id'=>$job->caderno_id],null);
                    return 'continue';
                });
                if ($done==='done') { break; }
            } catch (\Throwable $error) {
                if ($itemId===null) { throw $error; }
                // The entire failed item (including activity/audits) has rolled back.
                $reason=$error instanceof \DomainException ? $error->getMessage() : 'Falha ao gravar este item; atividade e auditoria deste item foram revertidas. Prepare outra prévia para tentar novamente.';
                $this->jobs()->getConnection()->transactional(function() use($userId,$token,$itemId,$reason) {
                    $job=$this->job($userId,$token); $this->lock($job);
                    $item=$this->items()->get($itemId);
                    if ($item->estado==='elegivel') { $item->estado='erro'; $item->motivo=$reason; $this->items()->saveOrFail($item); }
                });
            }
        }
        return $this->summary($userId,$token);
    }
}
