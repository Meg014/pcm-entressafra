<?php
declare(strict_types=1);
namespace App\Controller;
use App\Service\Access;
use App\Service\Evolucao;
use App\Service\EvolucaoExcel;

class EvolucaoController extends OperacaoController
{
    private function context(Evolucao $service): array
    {
        $season=(int)$this->request->getQuery('entressafra_id',0);
        if (!$season) { $seasons=$this->fetchTable('Entressafras'); $season=(int)(($seasons->find()->where(['status'=>'ativa'])->first()??$seasons->find()->orderBy(['id'=>'DESC'])->first())?->id??0); }
        $weeks=$season?$service->calendar($season):[];
        $today=(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        $past=array_values(array_filter(array_column($weeks,'referencia'),fn($d)=>$d<=$today));
        $reference=(string)$this->request->getQuery('referencia',($past?end($past):($weeks[0]['referencia']??$today)));
        if ($weeks && !in_array($reference,array_column($weeks,'referencia'),true)) { throw new \Cake\Http\Exception\BadRequestException('Referência fora do calendário.'); }
        $this->options(); $this->set(compact('season','weeks','reference'));
        return [$season,$weeks,$reference];
    }
    private function scoped(Evolucao $s,int $season): array
    {
        $allowed=array_map('intval',Access::sectors($this->currentUser));
        return array_values(array_filter($s->activities($season),function($a)use($allowed) {
            if ($this->currentUser->perfil==='RESPONSAVEL_SETOR' && !in_array((int)$a['setor_executante_id'],$allowed,true)) { return false; }
            foreach(['setor_id'=>'setor_executante_id','caderno_id'=>'caderno_id'] as $filter=>$field) { if ($this->request->getQuery($filter) && (int)$this->request->getQuery($filter)!==(int)$a[$field]) { return false; } }
            foreach(['os'=>'os_numero','bem'=>'bem'] as $filter=>$field) { if ($this->request->getQuery($filter) && mb_stripos((string)$a[$field],(string)$this->request->getQuery($filter))===false) { return false; } }
            return true;
        }));
    }
    public function index()
    {
        $s=new Evolucao(); [$season,$weeks,$reference]=$this->context($s);
        if ($this->request->is('post')) {
            try {
                $data=$this->request->getData();
                $result=$s->saveBatch((int)$this->currentUser->id,$season,$reference,(array)($data['alteracoes']??[]),(string)($data['chave']??''));
                $this->Flash->success($result['quantidade'].' percentuais registrados.');
                return $this->redirect($this->request->getRequestTarget());
            } catch (\DomainException $e) { $this->Flash->error($e->getMessage()); }
        }
        $q=Access::activityScope($this->fetchTable('Atividades')->find(),$this->currentUser)->where(['Atividades.entressafra_id'=>$season])->contain(['Cadernos','SetorExecutante'])->orderBy(['Atividades.os_numero'=>'ASC','Atividades.id'=>'ASC']);
        foreach(['setor_id'=>'setor_executante_id','caderno_id'=>'caderno_id'] as $filter=>$field)if($this->request->getQuery($filter))$q->where(['Atividades.'.$field=>(int)$this->request->getQuery($filter)]);
        foreach(['os'=>'os_numero','bem'=>'bem'] as $filter=>$field)if($this->request->getQuery($filter))$q->where(['Atividades.'.$field.' LIKE'=>'%'.(string)$this->request->getQuery($filter).'%']);
        if($this->request->getQuery('pendentes')){
            $weekIds=$this->fetchTable('EvolucaoSemanas')->find()->select(['id'])->where(['entressafra_id'=>$season,'referencia'=>$reference]);
            $recorded=$this->fetchTable('EvolucaoRegistros')->find()->select(['atividade_id'])->where(['semana_id IN'=>$weekIds]);
            $q->where(['Atividades.id NOT IN'=>$recorded]);
        }
        $total=$q->count();$page=max(1,min((int)$this->request->getQuery('pagina',1),max(1,(int)ceil($total/10))));$rows=[];
        foreach($q->limit(10)->offset(($page-1)*10)->all() as $entity){$a=$entity->toArray()+['caderno_nome'=>$entity->caderno?->nome??'Sem caderno','setor_nome'=>$entity->setor_executante?->nome??'Pendente'];$a['estado']=$s->state((int)$entity->id,$reference);$rows[]=$a;}
        $key=$this->request->is('post')?(string)$this->request->getData('chave'):bin2hex(random_bytes(16)); $this->set(compact('rows','total','page','key'));
    }
    public function historico($id=null): void
    {
        $activity=$this->activity((int)$id);$s=new Evolucao();$historyPage=(new \App\Service\Curva())->historyPage((int)$id,(int)$this->request->getQuery('pagina',1));$rows=$historyPage['rows'];$this->set('historyPage',$historyPage);$cs=new \App\Service\Calendarios();$period=$cs->resolve($activity->toArray());$individual=[];$today=(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        foreach($s->calendar((int)$activity->entressafra_id) as $w){$date=$w['referencia'];$individual[]=['referencia'=>$date,'programado'=>$cs->planned($activity->toArray(),$date),'realizado'=>$date<=$today?$s->state((int)$id,$date)['percentual']:null];}
        $this->set(compact('activity','rows','period','individual'));
    }
    public function configuracao()
    {
        Access::pcm($this->currentUser); $s=new Evolucao(); [$season,$weeks,$reference]=$this->context($s);
        $calendarios=(new \App\Service\Calendarios())->rows($season);$calendarId=(int)$this->request->getQuery('calendario_id',0);
        if($calendarId){$row=array_values(array_filter($calendarios,fn($r)=>(int)$r['id']===$calendarId));if(!$row)throw new \Cake\Http\Exception\BadRequestException('Calendário inválido.');$refs=(new \App\Service\Calendarios())->references($row[0]);$weeks=array_map(fn($d)=>['referencia'=>$d],$refs);$this->set('weeks',$weeks);}
        $areaNames=$this->fetchTable('AreasFabrica')->find('list')->all()->toArray();$calendarLabels=[];foreach($calendarios as $c)$calendarLabels[$c['id']]=($c['area_fabrica_id']?($areaNames[$c['area_fabrica_id']]??$c['chave']):($c['setor_id']?'Setor #'.$c['setor_id']:'Prazo padrão')).' · término '.$c['data_fim'];
        $this->set(compact('calendarios','calendarId','calendarLabels'));
        $preview=null; $data=$this->request->getData();
        if ($this->request->is('post')) {
            try {
                $action=(string)($data['acao']??'');
                if ($action==='calendario') {
                    $dates=preg_split('/\s+/',trim((string)($data['datas']??'')),-1,PREG_SPLIT_NO_EMPTY);
                    $s->configureCalendar((int)$this->currentUser->id,$season,$dates); $this->Flash->success('Referências confirmadas. As referências anteriores foram preservadas.');
                } else {
                    $type=(string)($data['tipo']??'');
                    if (!in_array($type,['pesos','metas'],true)) { throw new \DomainException('Tipo inválido.'); }
                    if ($action==='importar') {
                        $upload=$this->request->getUploadedFile('planilha');
                        if (!$upload || $upload->getError()!==UPLOAD_ERR_OK) { throw new \DomainException('Selecione a planilha .xlsx.'); }
                        $preview=(new EvolucaoExcel())->read($upload->getStream()->getMetadata('uri'),$type);
                        $valid=$type==='pesos'?array_column($s->activities($season),'id'):array_column($weeks,'referencia');
                        foreach($preview as $id=>$value) { if (!in_array((string)$id,array_map('strval',$valid),true)) { throw new \DomainException('A planilha contém identificadores fora da entressafra ou do calendário.'); } Evolucao::number($value,$type==='pesos'?999999999:100); }
                        $data['valores']=$preview; $this->Flash->success('Prévia importada. Confira e confirme uma nova versão; nada foi gravado.');
                    } else {
                        $values=$data['valores']??[];
                        if (isset($data['valores_json'])) {
                            if (!is_string($data['valores_json']) || strlen($data['valores_json'])>2000000) { throw new \DomainException('Conteúdo da versão excede o limite.'); }
                            try { $values=json_decode($data['valores_json'],true,512,JSON_THROW_ON_ERROR); } catch(\JsonException) { throw new \DomainException('Conteúdo da versão inválido.'); }
                        }
                        if (!is_array($values) || count($values)>10000) { throw new \DomainException('Versão inválida ou acima de 10.000 valores.'); }
                        if($type==='pesos'&&($data['parcial']??'')==='1') {
                            $versions=$s->versions($season,'pesos');$latest=$versions[0]??null;
                            if((string)($latest['id']??0)!==(string)($data['base_versao']??''))throw new \DomainException('Outra pessoa alterou os pesos. Atualize a página antes de salvar.');
                            $base=$latest?json_decode($latest['conteudo'],true,512,JSON_THROW_ON_ERROR):[];
                            if(isset($data['importados_json'])){
                                if(!is_string($data['importados_json'])||strlen($data['importados_json'])>2000000)throw new \DomainException('Prévia importada inválida.');
                                try{$base=json_decode($data['importados_json'],true,512,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new \DomainException('Prévia importada inválida.');}
                                if(!is_array($base)||count($base)>10000)throw new \DomainException('Prévia importada inválida.');
                            }
                            foreach($values as $id=>$value){if($value===''||$value===null)unset($base[$id]);else $base[$id]=$value;}$values=$base;
                        }
                        $values=array_filter($values,fn($v)=>$v!=='' && $v!==null);
                        $s->version((int)$this->currentUser->id,$season,$type,(string)($data['nome']??''),$values,($data['aprovar']??'')==='1',$type==='metas'?($calendarId?:null):null,$type==='pesos'&&($data['parcial']??'')==='1'?(int)$data['base_versao']:null);
                        $this->Flash->success('Nova versão preservada no histórico.');
                    }
                }
                if ($preview===null) { return $this->redirect($this->request->getRequestTarget()); }
            } catch (\DomainException $e) { $this->Flash->error($e->getMessage()); }
        }
        $q=$this->fetchTable('Atividades')->find()->select(['id','os_numero','bem','setor_executante_id','caderno_id'])->where(['Atividades.entressafra_id'=>$season])->contain(['Cadernos','SetorExecutante'])->orderBy(['Atividades.os_numero'=>'ASC']);
        $activitiesTotal=$q->count();$weightPages=max(1,(int)ceil($activitiesTotal/10));$weightPage=max(1,min((int)$this->request->getQuery('pagina',1),$weightPages));$activities=[];
        foreach($q->limit(10)->offset(($weightPage-1)*10)->all() as $a)$activities[]=$a->toArray()+['caderno_nome'=>$a->caderno?->nome??'Sem caderno','setor_nome'=>$a->setor_executante?->nome??'Pendente'];
        $weightVersions=$s->versions($season,'pesos'); $planVersions=$s->versions($season,'metas');
        $planVersions=array_values(array_filter($planVersions,fn($v)=>(int)($v['calendario_id']??0)===$calendarId));
        $weights=$weightVersions?json_decode($weightVersions[0]['conteudo'],true):[]; $targets=$planVersions?json_decode($planVersions[0]['conteudo'],true):[];
        if ($preview!==null) { if ($data['tipo']==='pesos') { $weights=$preview; } else { $targets=$preview; } }
        $this->set(compact('activities','weightVersions','planVersions','weights','targets','preview','data'));
        $this->set(compact('weightPage','weightPages','activitiesTotal'));
    }
    public function modelo($type=null)
    {
        Access::pcm($this->currentUser);
        if (!in_array($type,['pesos','metas'],true)) { throw new \Cake\Http\Exception\BadRequestException('Modelo inválido.'); }
        $s=new Evolucao();[$season]=$this->context($s);$selectedWeeks=$s->calendar($season);$cid=(int)$this->request->getQuery('calendario_id');if($type==='metas'&&$cid){$cs=new \App\Service\Calendarios();$rs=array_values(array_filter($cs->rows($season),fn($r)=>(int)$r['id']===$cid));if(!$rs)throw new \Cake\Http\Exception\BadRequestException('Calendário inválido.');$selectedWeeks=array_map(fn($d)=>['referencia'=>$d],$cs->references($rs[0]));}$book=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$sheet=$book->getActiveSheet();$sheet->setTitle('Dados');
        $sheet->fromArray($type==='pesos'?[['atividade_id','peso']]:[['referencia','percentual']]);
        $i=2;
        if ($type==='pesos') {
            foreach($s->activities($season) as $a) { $sheet->setCellValue('A'.$i,(int)$a['id']); $sheet->getComment('A'.$i)->getText()->createTextRun('O.S. '.$a['os_numero'].' / '.$a['os_filial'].' · '.$a['setor_nome'].' · '.$a['caderno_nome']); $i++; }
        } else { foreach($selectedWeeks as $week) { $sheet->setCellValueExplicit('A'.$i++,$week['referencia'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING); } }
        $sheet->getColumnDimension('A')->setAutoSize(true);$sheet->getColumnDimension('B')->setWidth(20);
        $file=tempnam(TMP,'evolucao-modelo-');
        try { (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($file);$bytes=file_get_contents($file); } finally { unlink($file);$book->disconnectWorksheets(); }
        return $this->response->withType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->withHeader('Cache-Control','no-store, private')->withDownload('modelo-'.$type.'.xlsx')->withStringBody($bytes);
    }
    public function controle(): void
    {
        Access::pcm($this->currentUser);$service=new Evolucao();[$season]=$this->context($service);
        $area=$this->request->getQuery('area_id','');$search=$this->request->getQuery('q','');
        if(!is_string($area)||!is_string($search))throw new \Cake\Http\Exception\BadRequestException('Filtro inválido.');
        $demo=$this->request->getQuery('demonstracao')==='eletrica';
        try{$report=(new \App\Service\Curva())->report($season,$area,$search,(int)$this->request->getQuery('pagina',1),$demo);}
        catch(\DomainException $e){throw new \Cake\Http\Exception\BadRequestException($e->getMessage());}
        $areas=$this->fetchTable('AreasFabrica')->find('list')->orderBy(['nome'=>'ASC'])->all()->toArray();
        $seasonRow=$this->fetchTable('Entressafras')->get($season);
        $areaName=$demo?'Elétrica · equipe executante (DEMONSTRAÇÃO)':($area==='pendentes'?'Pendências de classificação':($area!==''?($areas[(int)$area]??'Área'):'Consolidado da entressafra'));
        $this->set(compact('report','area','search','demo','areas','areaName','seasonRow'));
        $this->viewBuilder()->setTemplate('acompanhamento');
    }
    public function analise(): void
    {
        Access::pcm($this->currentUser); $s=new Evolucao(); [$season,$weeks,$reference]=$this->context($s);
        $asOf=$this->request->getQuery('registrado_ate');
        if ($asOf==='') { $asOf=null; }
        if ($asOf!==null && (!is_string($asOf) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D',$asOf))) { throw new \Cake\Http\Exception\BadRequestException('Data de corte inválida.'); }
        $cut=$asOf?(new \DateTimeImmutable($asOf,new \DateTimeZone('America/Sao_Paulo')))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'):null;
        $activities=$this->scoped($s,$season);
        if ($this->request->getQuery('pendentes')) { $s->loadHistory($season);$activities=array_values(array_filter($activities,fn($a)=>$s->state((int)$a['id'],$reference,$cut)['pendente'])); }
        $report=$s->report($season,$activities,$reference,$this->request->getQuery('pesos')?(int)$this->request->getQuery('pesos'):null,$this->request->getQuery('metas')?(int)$this->request->getQuery('metas'):null,$cut);
        $weightVersions=$s->versions($season,'pesos'); $planVersions=$s->versions($season,'metas');
        $end=$season?$this->fetchTable('Entressafras')->get($season)->data_fim->format('Y-m-d'):null;
        $remaining=$end?max(0,(int)(new \DateTimeImmutable('today',new \DateTimeZone('America/Sao_Paulo')))->diff(new \DateTimeImmutable($end))->format('%r%a')):0;
        $long=$this->fetchTable('Apontamentos')->find()->where(['minutos >'=>1440])->count();
        $longRows=$this->fetchTable('Apontamentos')->find()->select(['id','inicio','fim','minutos'])->where(['minutos >'=>1440])->orderBy(['id'=>'ASC'])->limit(100)->all();
        $this->set(compact('report','weightVersions','planVersions','remaining','long','longRows'));
        $this->set('hasScopedCalendars',(bool)(new \App\Service\Calendarios())->rows($season));
    }
}
