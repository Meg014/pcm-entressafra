<?php
declare(strict_types=1);
namespace App\Service;
use App\Integration\Totvs\GatewayInterface;
final class TimeEntries
{
    public function __construct(private GatewayInterface $totvs) {}
    public static function formInput(array $input): array
    {
        if (array_intersect(['hora_inicio_manha','hora_fim_manha','hora_inicio_tarde','hora_fim_tarde'], array_keys($input))) {
            foreach (['manha','tarde'] as $period) {
                $from=$input['hora_inicio_'.$period]??''; $to=$input['hora_fim_'.$period]??'';
                if ($from === '' && $to === '') { $input['inicio_'.$period]=null; $input['fim_'.$period]=null; continue; }
                $converted=self::formInput(['data_servico'=>$input['data_servico']??null,'hora_inicial'=>$from,'hora_final'=>$to,'termina_dia_seguinte'=>$input['termina_dia_seguinte']??'0']);
                $input['inicio_'.$period]=$converted['inicio']; $input['fim_'.$period]=$converted['fim'];
            }
            EntryPeriods::intervals($input);
            $input['inicio']=$input['inicio_manha']??$input['inicio_tarde'];
            $input['fim']=$input['fim_tarde']??$input['fim_manha'];
            return $input;
        }
        if (!array_intersect(['data_servico','hora_inicial','hora_final','termina_dia_seguinte'],array_keys($input))) { return $input; }
        $date=$input['data_servico']??null;$from=$input['hora_inicial']??null;$to=$input['hora_final']??null;
        if(!is_string($date)||!is_string($from)||!is_string($to))throw new \DomainException('Informe a data do serviço e as duas horas.');
        $date=trim($date);if(preg_match('/^\d{8}$/D',$date))$date=substr($date,0,2).'/'.substr($date,2,2).'/'.substr($date,4,4);
        if(!preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/D',$date,$parts)||!checkdate((int)$parts[2],(int)$parts[1],(int)$parts[3]))throw new \DomainException('Informe uma data real no formato DD/MM/AAAA.');
        $time=static function(string $v): string {
            $v=trim($v);if(preg_match('/^\d{4}$/D',$v))$v=substr($v,0,2).':'.substr($v,2,2);
            if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$v))throw new \DomainException('Informe horas válidas entre 00:00 e 23:59, no formato HH:MM.');return $v;
        };
        $next=$input['termina_dia_seguinte']??'0';
        if(!in_array($next,['0','1',0,1,false,true],true))throw new \DomainException('Opção de término no dia seguinte inválida.');
        $next=in_array($next,['1',1,true],true);
        if ($next) { throw new \DomainException('O apontamento deve começar e terminar na mesma data.'); }
        $day=\DateTimeImmutable::createFromFormat('!d/m/Y',$date,new \DateTimeZone('America/Sao_Paulo'));
        $input['inicio']=$day->format('Y-m-d').'T'.$time($from);
        $input['fim']=$day->format('Y-m-d').'T'.$time($to);
        self::interval($input['inicio'],$input['fim']);return $input;
    }
    public static function interval(string $start, string $end): array
    {
        $zone = new \DateTimeZone('America/Sao_Paulo');
        $parse = static function (string $value) use ($zone): \DateTimeImmutable {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, $zone);
            if (!$date || $date->format('Y-m-d\TH:i') !== $value) { throw new \DomainException('Informe datas e horários válidos.'); }
            return $date->setTimezone(new \DateTimeZone('UTC'));
        };
        $from = $parse($start); $to = $parse($end);
        if (substr($start,0,10) !== substr($end,0,10)) { throw new \DomainException('O apontamento deve começar e terminar na mesma data.'); }
        if ($to <= $from) { throw new \DomainException('O fim deve ser posterior ao início.'); }
        return [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s'), (int)(($to->getTimestamp() - $from->getTimestamp()) / 60)];
    }
    public function conflicts(string $employee, string $start, string $end, ?int $except = null): array
    {
        $where = ['funcionario_chave' => $employee, 'cancelado' => false, 'inicio <' => $end, 'fim >' => $start];
        if ($except !== null) { $where['Apontamentos.id !='] = $except; }
        $rows = Access::table('Apontamentos')->find()->where($where)->orderBy(['inicio' => 'ASC'])->all();
        $result = [];
        foreach ($rows as $row) {
            $overlap=false; foreach (EntryPeriods::stored($row) as [$from,$to]) { if ($from->format('Y-m-d H:i:s') < $end && $to->format('Y-m-d H:i:s') > $start) { $overlap=true; } }
            if (!$overlap) { continue; }
            $snapshot = json_decode($row->snapshot, true, 512, JSON_THROW_ON_ERROR);
            $result[] = ['nome' => $row->funcionario_nome, 'matricula' => $row->funcionario_matricula, 'filial' => $row->funcionario_filial, 'inicio' => $row->inicio->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i'), 'fim' => $row->fim->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i'), 'os' => $snapshot['os_numero'], 'setor' => $snapshot['setor_nome'], 'caderno' => $snapshot['caderno_nome'], 'descricao' => $row->descricao];
        }
        return $result;
    }
    public function save(int $userId, array $input, ?int $id = null, bool $validateOnly = false): int
    {
        $input=self::formInput($input);
        $user = Access::actor($userId);
        $table = Access::table('Apontamentos');
        try {
            return $table->getConnection()->transactional(function () use ($user, $table, $input, $id, $validateOnly): int {
                if ($id !== null && $table->getConnection()->getDriver() instanceof \Cake\Database\Driver\Postgres) {
                    $table->getConnection()->execute('SELECT id FROM apontamentos WHERE id = ? FOR UPDATE', [$id]);
                }
                $existing = $id ? $table->get($id) : null;
                if($existing && $existing->controle_pcm==='lancado') { throw new \DomainException('Apontamento já lançado no TOTVS. Reabra a confirmação pelo PCM, com justificativa, antes de corrigir.'); }
                $before = $existing?->toArray();
                if ($existing?->cancelado) { throw new \DomainException('Apontamento cancelado não pode ser editado.'); }
                if (!$existing) {
                    $activityId=$input['atividade_id']??null;
                    if (!is_scalar($activityId) || !ctype_digit((string)$activityId) || (int)$activityId<1 || !Access::table('Atividades')->exists(['id'=>(int)$activityId])) { throw new \DomainException('Selecione uma O.S. cadastrada e válida da lista.'); }
                }
                $activity = Access::table('Atividades')->get($existing?->atividade_id ?? (int)$input['atividade_id']);
                if(!$existing && (Access::operational($user)||($input['consultar_totvs']??'')==='1')){$source=$this->totvs->order($activity->os_chave);if(($source['numero']??null)!==$activity->os_numero||($source['filial']??null)!==$activity->os_filial||($source['codigo_area']??null)!==$activity->area_executante_totvs||!\App\Integration\Totvs\Orders::available($source))throw new \DomainException('O.S. indisponível ou divergente no TOTVS. Nenhum apontamento salvo.');}
                if(!$existing && Access::operational($user)) { (new OperacaoRapida())->activity($user,(int)$activity->id); }
                if(!$existing && $activity->disponivel_totvs!==true) { throw new \DomainException('O.S. não está disponível para novo apontamento. Solicite a sincronização pelo PCM; o histórico permanece preservado.'); }
                $notebook = $activity->caderno_id ? Access::table('Cadernos')->get($activity->caderno_id) : null;
                Access::activity($user,$activity);
                if (!$existing) {
                    PeriodoContexto::writable((int)$activity->entressafra_id);
                }
                if (!$existing && ($activity->setor_executante_id===null || $activity->identificacao_setor!=='area_totvs')) { throw new \DomainException('Setor executante da O.S. pendente de identificação pelo PCM.'); }
                if (!$existing) {
                    foreach(['entressafra_id'=>$activity->entressafra_id,'setor_id'=>$activity->setor_executante_id,'caderno_id'=>$notebook?->id] as $field=>$expected) {
                        $value=$input[$field]??'';
                        if ($value!=='' && $value!==null && (!is_scalar($value) || !ctype_digit((string)$value) || (int)$value!==(int)$expected)) { throw new \DomainException('A O.S. selecionada não corresponde aos filtros. Pesquise e selecione novamente.'); }
                    }
                }
                if ($existing && $user->perfil === 'RESPONSAVEL_SETOR') { throw new \Cake\Http\Exception\ForbiddenException('Correções são exclusivas do PCM até definição da política de autorização.'); }
                $reason = trim((string)($input['motivo'] ?? ''));
                if ($existing && $reason === '') { throw new \DomainException('Informe o motivo da correção.'); }
                $periods = EntryPeriods::intervals($input);
                $start=$periods[0][0]; $end=$periods[array_key_last($periods)][1]; $minutes=array_sum(array_column($periods,2));
                $periodData=array_fill_keys(EntryPeriods::FIELDS,null);
                foreach (['manha','tarde'] as $period) {
                    if (!empty($input['inicio_'.$period])) {
                        [$periodData['inicio_'.$period],$periodData['fim_'.$period]]=self::interval($input['inicio_'.$period],$input['fim_'.$period]);
                    }
                }
                if ($existing) {
                    foreach ($periodData as $field=>$value) {
                        $historical=!$existing->inicio_manha && !$existing->inicio_tarde;
                        $old=$existing->$field?->format('Y-m-d H:i:s');
                        if ($historical && $field==='inicio_manha') { $old=$existing->inicio->format('Y-m-d H:i:s'); }
                        if ($historical && $field==='fim_manha') { $old=$existing->fim->format('Y-m-d H:i:s'); }
                        if (!array_intersect(EntryPeriods::FIELDS,array_keys($input))) { continue; }
                        if ($old!==$value) { Access::admin($user); }
                    }
                }
                if ($existing && ($minutes!==(int)$existing->minutos || $start!==$existing->inicio->format('Y-m-d H:i:s') || $end!==$existing->fim->format('Y-m-d H:i:s'))) { Access::admin($user); }
                $description = $input['descricao'] ?? null;
                if (!is_string($description) || trim($description) === '' || mb_strlen($description) > 10000) { throw new \DomainException('Informe o serviço realizado, com até 10.000 caracteres.'); }
                if ($existing) {
                    // A correção preserva a identidade histórica do funcionário e da O.S.
                    $employee = ['chave' => $existing->funcionario_chave, 'nome' => $existing->funcionario_nome, 'matricula' => $existing->funcionario_matricula, 'filial' => $existing->funcionario_filial, 'vinculo' => $existing->funcionario_vinculo];
                    $snapshot = json_decode($existing->snapshot, true, 512, JSON_THROW_ON_ERROR);
                } else {

                    $sector=Access::table('Setores')->get($activity->setor_executante_id);
                    if (!$sector->ativo || $sector->area_totvs!==$activity->area_executante_totvs || $sector->filial_totvs!==$activity->os_filial) { throw new \DomainException('Mapeamento do setor executante divergente da área original da O.S. Solicite conferência ao PCM.'); }
                    $employee = $this->totvs->employee((string)($input['funcionario_chave'] ?? ''));
                    foreach (['chave', 'nome', 'matricula', 'filial', 'vinculo'] as $field) {
                        if (!isset($employee[$field]) || !is_string($employee[$field]) || ($field !== 'filial' && trim($employee[$field]) === '') || strlen($employee[$field]) > ($field === 'matricula' || $field === 'filial' ? 100 : 255)) { throw new \DomainException('Identificação do funcionário incompleta ou ambígua.'); }
                    }
                    if ($employee['chave'] !== ($input['funcionario_chave'] ?? null) || ($employee['ativo'] ?? false) !== true) { throw new \DomainException('Funcionário não apto para novo apontamento. Férias, afastamentos e outras situações exigem regra ou autorização definida pelo PCM.'); }
                    if (($employee['identificacao']??'')==='matricula_010' && $table->find()->where(['cancelado'=>false,'funcionario_chave <> funcionario_matricula'])->count()>0) { throw new \DomainException('Há apontamentos ativos com a identificação anterior. Solicite análise do histórico antes de habilitar novas matrículas; nenhum registro foi alterado.'); }
                    $sector = Access::table('Setores')->get($activity->setor_executante_id);
                    $season = Access::table('Entressafras')->get($activity->entressafra_id);
                    $snapshot = ['funcionario' => $employee, 'os_numero' => $activity->os_numero, 'os_filial' => $activity->os_filial, 'os_chave' => $activity->os_chave, 'servico' => $activity->servico, 'bem' => $activity->bem, 'setor_id' => $sector->id, 'setor_nome' => $sector->nome,'area_executante_totvs'=>$activity->area_executante_totvs,'area_fisica_referencia'=>$notebook?->area_fisica, 'caderno_id' => $notebook?->id, 'caderno_nome' => ($notebook?->nome??'Sem caderno'), 'entressafra_id' => $season->id, 'entressafra_nome' => $season->nome];
                }
                $conflicts=[]; foreach ($periods as [$from,$to]) { $conflicts=array_merge($conflicts,$this->conflicts($employee['chave'],$from,$to,$id)); }
                if ($conflicts) { throw new ConflictException($conflicts); }
                if ($validateOnly) { return 0; }
                $now = gmdate('Y-m-d H:i:s');
                $data = ['atividade_id' => $activity->id, 'funcionario_chave' => $employee['chave'], 'funcionario_nome' => $employee['nome'], 'funcionario_matricula' => $employee['matricula'], 'funcionario_filial' => $employee['filial'], 'funcionario_vinculo' => $employee['vinculo'], 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'inicio' => $start, 'fim' => $end, 'minutos' => $minutes, 'descricao' => $description, 'alterado_por' => $user->id, 'modified' => $now];
                if (!$existing) { $data += ['criado_por' => $user->id, 'created' => $now, 'cancelado' => false, 'controle_pcm' => 'pendente']; }
                else { $data['controle_pcm'] = 'pendente'; }
                $data += $periodData;
                $data['revisao_pcm']=$existing?(int)$existing->revisao_pcm+1:0;
                $entity = $existing ?? Access::entity($table, []);
                foreach ($data as $field => $value) { $entity->set($field, $value); }
                $table->saveOrFail($entity);
                Access::audit($user, $existing ? 'correcao' : 'criacao', (int)$entity->id, $before, $entity->toArray(), $reason ?: null);
                return (int)$entity->id;
            });
        } catch (\Cake\Database\Exception\QueryException $e) {
            if (str_contains($e->getMessage(), '23P01')) {
                $old = $id ? $table->get($id) : null;
                $conflicts=[];
                foreach (EntryPeriods::intervals($input) as [$start,$end]) {
                    $conflicts=array_merge($conflicts,$this->conflicts($old?->funcionario_chave ?? (string)$input['funcionario_chave'], $start, $end, $id));
                }
                throw new ConflictException($conflicts);
            }
            throw $e;
        }
    }
    public function saveBatch(int $userId, array $input): array
    {
        $rows = $input['apontamentos'] ?? null;
        if (!is_array($rows) || !$rows || !array_is_list($rows)) { throw new \DomainException('Adicione pelo menos um apontamento.'); }
        if (isset($input['quantidade_linhas']) && (string)$input['quantidade_linhas'] !== (string)count($rows)) {
            throw new \DomainException('O formulário recebido está incompleto. Reduza o lote e tente novamente. Nenhum apontamento foi salvo.');
        }
        $errors = []; $prepared = [];
        foreach ($rows as $index => $row) {
            try {
                if (!is_array($row)) { throw new \DomainException('Linha inválida.'); }
                foreach (['funcionario_chave','funcionario_rotulo','data_servico','hora_inicial','hora_final','inicio','fim','descricao'] as $field) {
                    if (isset($row[$field]) && !is_string($row[$field])) { throw new \DomainException('Preencha os campos da linha com valores válidos.'); }
                }
                $row['atividade_id'] = $input['atividade_id'] ?? null;
                $row['consultar_totvs'] = '1';
                $normalized = self::formInput($row);
                $this->save($userId, $row, null, true);
                $prepared[$index] = $normalized;
            } catch (ConflictException $e) { $errors[$index] = $this->batchConflict($e); }
            catch (\DomainException $e) { $errors[$index] = $e->getMessage(); }
        }
        foreach ($prepared as $i => $a) {
            foreach ($prepared as $j => $b) {
                if ($j <= $i || empty($a['funcionario_chave']) || $a['funcionario_chave'] !== ($b['funcionario_chave'] ?? null)) { continue; }
                $overlap=false; foreach (EntryPeriods::intervals($a) as [$af,$at]) { foreach (EntryPeriods::intervals($b) as [$bf,$bt]) { if ($af < $bt && $at > $bf) { $overlap=true; } } }
                if ($overlap) {
                    $message = 'Funcionário '.($a['funcionario_rotulo'] ?? $a['funcionario_chave']).': conflito entre linhas '.($i+1).' e '.($j+1).' ('.$a['inicio'].' até '.$a['fim'].' / '.$b['inicio'].' até '.$b['fim'].').';
                    $errors[$i] = $message; $errors[$j] = $message;
                }
            }
        }
        if ($errors) { throw new BatchEntryException($errors); }
        return Access::table('Apontamentos')->getConnection()->transactional(function () use ($prepared, $userId): array {
            $ids = [];
            foreach ($prepared as $index => $row) {
                try { $ids[] = $this->save($userId, $row); }
                catch (ConflictException $e) { throw new BatchEntryException([$index => $this->batchConflict($e)]); }
                catch (\DomainException $e) { throw new BatchEntryException([$index => $e->getMessage()]); }
            }
            return $ids;
        });
    }
    private function batchConflict(ConflictException $error): string
    {
        return 'Conflito de horário: '.implode('; ', array_map(static fn(array $c): string => $c['nome'].' · Matrícula '.$c['matricula'].' · '.$c['inicio'].' até '.$c['fim'].' · O.S. '.$c['os'], $error->conflicts));
    }
    public function change(int $userId, int $id, string $action, string $reason): void
    {
        $user = Access::actor($userId); Access::pcm($user);
        if (trim($reason) === '') { throw new \DomainException('Informe o motivo ou referência da confirmação.'); }
        if (!in_array($action, ['cancelar', 'pendente', 'conferido', 'lancado'], true)) { throw new \DomainException('Operação inválida.'); }
        $table = Access::table('Apontamentos');
        $table->getConnection()->transactional(function () use ($user, $table, $id, $action, $reason): void {
            if ($table->getConnection()->getDriver() instanceof \Cake\Database\Driver\Postgres) { $table->getConnection()->execute('SELECT id FROM apontamentos WHERE id = ? FOR UPDATE', [$id]); }
            $row = $table->get($id);
            if ($row->cancelado) { throw new \DomainException('Apontamento já cancelado.'); }
            if($row->controle_pcm==='lancado' && $action!=='pendente') { throw new \DomainException('Reabra a confirmação com justificativa antes de alterar o apontamento.'); }
            $before = $row->toArray();
            if ($action === 'cancelar') { $row->cancelado = true; } else { $row->controle_pcm = $action; }
            $row->alterado_por = $user->id; $row->modified = gmdate('Y-m-d H:i:s');
            $row->revisao_pcm=(int)$row->revisao_pcm+1;
            if($action==='lancado'){$row->confirmado_por=$user->id;$row->confirmado_em=gmdate('Y-m-d H:i:s');}
            elseif($action==='pendente'){$row->confirmado_por=null;$row->confirmado_em=null;}
            $table->saveOrFail($row);
            Access::audit($user, $action, $id, $before, $row->toArray(), $reason);
        });
    }
}
