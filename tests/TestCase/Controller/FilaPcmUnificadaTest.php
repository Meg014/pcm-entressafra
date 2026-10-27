<?php
declare(strict_types=1);
namespace App\Test\TestCase\Controller;

use App\Service\{Access,Activities,TimeEntries,Insumos,FilaPcm,PeriodoContexto};
use App\Test\Support\{FakeTotvs,CompletedOrder};
use Cake\TestSuite\{TestCase,IntegrationTestTrait};

class FilaPcmUnificadaTest extends TestCase
{
    use IntegrationTestTrait;
    private int $admin;private int $pcm;private int $responsible;private int $season;private array $activities=[];

    protected function setUp(): void
    {
        parent::setUp();PeriodoContexto::set(null);
        foreach(['Auditorias','Insumos','EvolucaoPesos','EvolucaoMetas','EvolucaoRegistros','EvolucaoLotes','EvolucaoVersoes','CalendariosOperacionais','EvolucaoSemanas','VinculosItens','VinculosLotes','Apontamentos','Atividades','PendenciasOrdens','PendenciasConsultas','UsuariosSetores','Usuarios','Cadernos','Setores','AreasFabrica','Entressafras'] as $alias)Access::table($alias)->deleteAll([]);
        $now=gmdate('Y-m-d H:i:s');$t=Access::table('Usuarios');
        foreach(['admin'=>'ADMIN_PCM','pcm'=>'PCM','responsible'=>'RESPONSAVEL_SETOR'] as $property=>$role){
            $this->$property=(int)$t->saveOrFail(Access::entity($t,['nome'=>$role,'login'=>'fila-'.$property,'senha_hash'=>password_hash('Teste-2026!',PASSWORD_DEFAULT),'perfil'=>$role,'ativo'=>true,'computador_operacional'=>$property==='responsible','created'=>$now,'modified'=>$now]))->id;
        }
        $t=Access::table('Entressafras');$this->season=(int)$t->saveOrFail($t->newEntity(['nome'=>'2026/2027','data_inicio'=>'2026-09-01','data_fim'=>'2027-04-30','status'=>'ativa']))->id;
        $t=Access::table('AreasFabrica');$area=$t->saveOrFail(Access::entity($t,['nome'=>'MOENDA','entressafra_id'=>$this->season]));
        $t=Access::table('Setores');$sector=$t->saveOrFail($t->newEntity(['nome'=>'Mecânica','ativo'=>true,'area_totvs'=>'A0','filial_totvs'=>'01']));
        $t=Access::table('UsuariosSetores');$t->saveOrFail(Access::entity($t,['usuario_id'=>$this->responsible,'setor_id'=>$sector->id]));
        $t=Access::table('Cadernos');$book=$t->saveOrFail($t->newEntity(['nome'=>'Caderno Moenda','entressafra_id'=>$this->season,'setor_id'=>$sector->id,'area_curva_id'=>$area->id,'ativo'=>true]));
        foreach(['os-0','os-2','os-3'] as $key){$id=(new Activities(new FakeTotvs()))->link($this->admin,(int)$book->id,$key);CompletedOrder::set($id,$this->admin);$this->activities[]=$id;}
        $this->mockService(\App\Integration\Totvs\GatewayInterface::class,fn()=>new FakeTotvs());
        $this->login($this->admin);$this->enableCsrfToken();PeriodoContexto::set($this->season);
    }
    protected function tearDown(): void
    {
        PeriodoContexto::set(null);Access::table('Auditorias')->deleteAll(['insumo_id IS NOT'=>null]);Access::table('Insumos')->deleteAll([]);parent::tearDown();
    }
    private function login(int $actor): void
    {
        $user=Access::actor($actor);$this->session(['Auth'=>['id'=>$actor,'stamp'=>hash('sha256',$user->senha_hash),'last'=>time()],'Periodo'=>['id'=>$this->season,'usuario_id'=>$actor]]);
    }
    private function hours(int $index=0,string $employee='pessoa-1'): int
    {
        return (new TimeEntries(new FakeTotvs()))->save($this->admin,['atividade_id'=>$this->activities[$index],'funcionario_chave'=>$employee,'inicio'=>'2026-10-21T07:00','fim'=>'2026-10-21T09:00','descricao'=>'Serviço de teste '.$index]);
    }
    private function supplies(int $index=0,int $count=1): array
    {
        $rows=array_fill(0,$count,['funcionario_chave'=>'pessoa-1','data'=>'21/10/2026','material'=>'Rolamento da O.S. '.$index,'quantidade'=>'2,5']);
        return (new Insumos(new FakeTotvs()))->saveBatch($this->admin,['atividade_id'=>$this->activities[$index],'insumos'=>$rows]);
    }
    private function selection(array $ids,string $alias='Insumos'): array
    {
        return array_map(static fn(int $id):array=>['id'=>$id,'revisao'=>(int)Access::table($alias)->get($id)->revisao_pcm],$ids);
    }
    private function postSelection(array $ids,string $category='insumos',string $action='lancado',string $reason=''): array
    {
        $revisions=[];foreach($this->selection($ids,$category==='horas'?'Apontamentos':'Insumos') as $row)$revisions[$row['id']]=$row['revisao'];
        return ['categoria'=>$category,'selecionados'=>$ids,'revisoes'=>$revisions,'acao'=>$action,'confirmar'=>'1','motivo'=>$reason];
    }
    public function testQueueIncludesHoursOnlySuppliesOnlyAndBothWithoutMultiplyingTotals(): void
    {
        $service=new FilaPcm();$actor=Access::actor($this->admin);$this->assertSame(0,$service->queueCount($actor));
        $hour=$this->hours();$only=$this->supplies(1);$this->hours(2,'pessoa-2');$this->hours(2,'pessoa-3');$both=$this->supplies(2,3);
        Access::table('Insumos')->updateAll(['created'=>'2026-09-01 00:00:00'],['id'=>$only[0]]);
        $rows=$service->queue($actor);$this->assertCount(3,$rows);$this->assertSame(3,$service->queueCount($actor));$this->assertSame($this->activities[1],(int)$rows[0]['id']);
        $byId=array_column($rows,null,'id');
        $this->assertSame(1,(int)$byId[$this->activities[0]]['apontamentos_pendentes']);$this->assertSame(0,(int)$byId[$this->activities[0]]['insumos_pendentes']);
        $this->assertSame(0,(int)$byId[$this->activities[1]]['apontamentos_pendentes']);$this->assertSame(1,(int)$byId[$this->activities[1]]['insumos_pendentes']);
        $this->assertSame(2,(int)$byId[$this->activities[2]]['apontamentos_pendentes']);$this->assertSame(3,(int)$byId[$this->activities[2]]['insumos_pendentes']);$this->assertSame(240,(int)$byId[$this->activities[2]]['minutos']);
        $service->confirm($this->pcm,$this->activities[0],$this->selection([$hour],'Apontamentos'),'lancado','',true);$this->assertSame(2,$service->queueCount($actor));
        $service->confirmSupplies($this->pcm,$this->activities[1],$this->selection($only),'lancado','',true);$this->assertSame(1,$service->queueCount($actor));
    }
    public function testQueueShowsRealEvolutionIndependentlyOfReceiptsAndTotvsStatus(): void
    {
        Access::table('EvolucaoRegistros')->deleteAll(['atividade_id'=>$this->activities[0]]);
        CompletedOrder::set($this->activities[1],$this->admin,50);
        $this->hours();$this->supplies(1);$this->hours(2,'pessoa-2');$this->supplies(2);
        Access::table('Atividades')->updateAll(['situacao_totvs'=>'F'],['id'=>$this->activities[0]]);
        $service=new FilaPcm();$actor=Access::actor($this->admin);$rows=array_column($service->queue($actor),null,'id');
        foreach([0,50,100] as $index=>$percentage)$this->assertSame((float)$percentage,$rows[$this->activities[$index]]['percentual_atual']);
        $this->assertSame(3,$service->queueCount($actor));
        $this->get('/fila-pcm');$this->assertResponseOk();$this->assertResponseContains('Evolução (%)');$this->assertResponseContains('0%');$this->assertResponseContains('50%');$this->assertResponseContains('100%');
        $this->get('/fila-pcm/os/'.$this->activities[1]);$this->assertResponseOk();$this->assertResponseContains('Percentual atual de evolução:');$this->assertResponseContains('50%');
        $db=Access::table('Atividades')->getConnection();$hours=$db->execute('SELECT * FROM apontamentos ORDER BY id')->fetchAll('assoc');$supplies=$db->execute('SELECT * FROM insumos ORDER BY id')->fetchAll('assoc');
        [$reference]=(new \App\Service\OperacaoRapida())->reference($this->season);$evolution=new \App\Service\Evolucao();$state=$evolution->state($this->activities[1],$reference);
        $evolution->saveBatch($this->admin,$this->season,$reference,[['atividade_id'=>$this->activities[1],'percentual'=>70,'revisao'=>$state['revisao']]],bin2hex(random_bytes(16)),true);
        $updated=array_column($service->queue($actor),null,'id');$this->assertSame(70.0,$updated[$this->activities[1]]['percentual_atual']);$this->assertSame(3,$service->queueCount($actor));
        $this->assertSame($hours,$db->execute('SELECT * FROM apontamentos ORDER BY id')->fetchAll('assoc'));$this->assertSame($supplies,$db->execute('SELECT * FROM insumos ORDER BY id')->fetchAll('assoc'));
        $this->get('/fila-pcm/os/'.$this->activities[2]);$this->assertResponseOk();$this->assertResponseContains('Apontamentos de horas');$this->assertResponseContains('Insumos utilizados');
    }
    public function testConfirmAndReopenAreIndependentAndKeepAuditHistory(): void
    {
        $hour=$this->hours();$supplies=$this->supplies(0,2);$service=new FilaPcm();
        $this->assertSame(2,$service->confirmSupplies($this->pcm,$this->activities[0],$this->selection($supplies),'lancado','Referência material',true));
        $this->assertSame('pendente',Access::table('Apontamentos')->get($hour)->controle_pcm);
        foreach($supplies as $id){$row=Access::table('Insumos')->get($id);$this->assertSame('lancado',$row->controle_pcm);$this->assertSame(1,(int)$row->revisao_pcm);$this->assertSame($this->pcm,(int)$row->confirmado_por);$this->assertNotNull($row->confirmado_em);}
        $this->assertSame(2,Access::table('Auditorias')->find()->where(['acao'=>'insumo_confirmacao_totvs'])->count());
        $this->assertSame(0,Access::table('Auditorias')->find()->where(['acao'=>'confirmacao_totvs'])->count());
        $service->confirmSupplies($this->pcm,$this->activities[0],$this->selection([$supplies[0]]),'pendente','Quantidade conferida novamente',true);
        $reopened=Access::table('Insumos')->get($supplies[0]);$this->assertSame('pendente',$reopened->controle_pcm);$this->assertSame(2,(int)$reopened->revisao_pcm);$this->assertNull($reopened->confirmado_em);$this->assertNull($reopened->confirmado_por);
        $service->confirm($this->admin,$this->activities[0],$this->selection([$hour],'Apontamentos'),'lancado','Horas lançadas',true);
        $queue=$service->queue(Access::actor($this->admin));$this->assertCount(1,$queue);$this->assertSame(0,(int)$queue[0]['apontamentos_pendentes']);$this->assertSame(1,(int)$queue[0]['insumos_pendentes']);
        $this->assertSame(3,Access::table('Auditorias')->find()->where(['insumo_id'=>$supplies[0]])->count());
    }
    public function testInvalidSelectionsStaleRevisionsAndForeignOrdersRollBack(): void
    {
        $supplies=$this->supplies(0,2);$foreign=$this->supplies(1);$service=new FilaPcm();
        $cases=[[$this->selection([$supplies[0],$foreign[0]]),'lancado','',true],[$this->selection([$supplies[0],$supplies[0]]),'lancado','',true],[[$this->selection([$supplies[0]])[0],['id'=>$supplies[1],'revisao'=>99]],'lancado','',true],[$this->selection($supplies),'lancado','',false],[$this->selection($supplies),'pendente','',true],[$this->selection($supplies),'cancelar','',true]];
        foreach($cases as [$selection,$action,$reason,$confirmed]){
            try{$service->confirmSupplies($this->pcm,$this->activities[0],$selection,$action,$reason,$confirmed);$this->fail('Invalid batch accepted');}catch(\DomainException){$this->assertSame(0,Access::table('Insumos')->find()->where(['controle_pcm'=>'lancado'])->count());}
        }
        $this->assertSame(0,Access::table('Auditorias')->find()->where(['acao'=>'insumo_confirmacao_totvs'])->count());
        $stale=$this->selection($supplies);$service->confirmSupplies($this->pcm,$this->activities[0],$stale,'lancado','',true);
        $this->expectException(\DomainException::class);$service->confirmSupplies($this->pcm,$this->activities[0],$stale,'lancado','',true);
    }
    public function testUnifiedHttpPageHasIndependentFormsAndAdminOnlyEditHour(): void
    {
        $supply=$this->supplies();$this->get('/fila-pcm');$this->assertResponseOk();$this->assertResponseContains('Ver registros');$this->assertResponseContains('Insumos pendentes');$this->assertResponseNotContains('Ver apontamentos');
        $this->get('/fila-pcm/os/'.$this->activities[0]);$this->assertResponseOk();$this->assertResponseContains('Nenhum apontamento de horas registrado.');$this->assertResponseContains('Rolamento da O.S. 0');
        $hour=$this->hours();$this->get('/fila-pcm/os/'.$this->activities[0]);$this->assertResponseOk();$this->assertResponseContains('MOENDA');$this->assertResponseContains('100%');$this->assertResponseContains('Bem de teste');$this->assertResponseContains('Editar hora');$this->assertResponseContains('Recebimento de insumo');
        $dom=new \DOMDocument();@$dom->loadHTML((string)$this->_response->getBody());$xp=new \DOMXPath($dom);
        $this->assertSame(1,$xp->query('//form[@id="horas-controle"]//input[@name="categoria" and @value="horas"]')->length);$this->assertSame(1,$xp->query('//form[@id="insumos-controle"]//input[@name="categoria" and @value="insumos"]')->length);$this->assertSame(0,$xp->query('//form//form')->length);
        if(getenv('FILA_PREVIEW'))file_put_contents(TMP.'fila-unificada-preview.html',(string)$this->_response->getBody());
        $this->login($this->pcm);$this->get('/fila-pcm/os/'.$this->activities[0]);$this->assertResponseOk();$this->assertResponseNotContains('Editar hora');
        $this->post('/fila-pcm/os/'.$this->activities[0],$this->postSelection($supply));$this->assertResponseCode(302);$this->assertSame('pendente',Access::table('Apontamentos')->get($hour)->controle_pcm);$this->assertSame('lancado',Access::table('Insumos')->get($supply[0])->controle_pcm);
        $this->post('/fila-pcm/os/'.$this->activities[0],$this->postSelection($supply,'insumos','pendente','Conferência física'));$this->assertResponseCode(302);
        $this->get('/fila-pcm/os/'.$this->activities[0]);$this->assertResponseContains('Confirmação de insumo');$this->assertResponseContains('Reabertura de insumo');
        $this->post('/fila-pcm/os/'.$this->activities[0],['categoria'=>'outra']+$this->postSelection([$hour],'horas'));$this->assertResponseOk();$this->assertResponseContains('Categoria inválida');$this->assertSame('pendente',Access::table('Apontamentos')->get($hour)->controle_pcm);
    }
    public function testHoursOnlyPageAndIndependentPaginationKeepEveryHistoryAccessible(): void
    {
        $this->hours();$this->get('/fila-pcm/os/'.$this->activities[0]);$this->assertResponseContains('Nenhum insumo registrado.');
        for($i=1;$i<=10;$i++)$this->hours(0,'pessoa-'.($i+1));$this->supplies(0,11);
        $this->get('/fila-pcm/os/'.$this->activities[0].'?page=2&insumos_pagina=2&historico_pagina=2');$this->assertResponseOk();
        $dom=new \DOMDocument();@$dom->loadHTML((string)$this->_response->getBody());$xp=new \DOMXPath($dom);
        $this->assertSame(1,$xp->query('//*[@id="horas"]//tbody/tr')->length);$this->assertSame(1,$xp->query('//*[@id="insumos"]//tbody/tr')->length);$this->assertSame(2,$xp->query('//*[@id="historico"]/details')->length);
        foreach($xp->query('//nav[@aria-label="Paginação de insumos"]/a') as $link){$this->assertStringContainsString('/fila-pcm/os/'.$this->activities[0],$link->getAttribute('href'));$this->assertStringContainsString('historico_pagina=2',$link->getAttribute('href'));}
    }
    public function testPeriodsPermissionsAndClosedSeasonCannotBeBypassed(): void
    {
        $supplies=$this->supplies();$this->login($this->responsible);$this->get('/fila-pcm');$this->assertResponseCode(403);$this->post('/fila-pcm/os/'.$this->activities[0],$this->postSelection($supplies));$this->assertResponseCode(403);
        $this->login($this->admin);$t=Access::table('Entressafras');$other=$t->saveOrFail($t->newEntity(['nome'=>'Outra','data_inicio'=>'2026-09-01','data_fim'=>'2027-04-30','status'=>'planejamento']));
        $base=Access::table('Atividades')->get($this->activities[0])->toArray();unset($base['id']);$base['entressafra_id']=$other->id;$base['caderno_id']=null;$base['os_chave']='outra-os';$base['os_numero']='OUTRA';
        PeriodoContexto::set(null);$activity=Access::table('Atividades')->saveOrFail(Access::entity(Access::table('Atividades'),$base));PeriodoContexto::set($this->season);
        PeriodoContexto::set(null);$foreignData=Access::table('Insumos')->get($supplies[0])->toArray();unset($foreignData['id']);$foreignData['atividade_id']=$activity->id;$foreignData['material']='Material de outra entressafra';
        $foreign=Access::table('Insumos')->saveOrFail(Access::entity(Access::table('Insumos'),$foreignData));PeriodoContexto::set($this->season);
        $this->assertSame(1,(new FilaPcm())->queueCount(Access::actor($this->admin)));$this->assertCount(1,(new FilaPcm())->queue(Access::actor($this->admin)));
        $this->get('/fila-pcm/os/'.$this->activities[0]);$this->assertResponseOk();$this->assertResponseNotContains('Material de outra entressafra');
        $this->post('/fila-pcm/os/'.$this->activities[0],$this->postSelection([(int)$foreign->id]));$this->assertResponseOk();$this->assertSame('pendente',Access::table('Insumos')->get($foreign->id)->controle_pcm);
        $this->get('/fila-pcm/os/'.$activity->id);$this->assertResponseCode(403);
        $this->post('/fila-pcm/os/'.$activity->id,$this->postSelection($supplies));$this->assertResponseCode(403);
        $t->updateAll(['status'=>'encerrada'],['id'=>$this->season]);$this->get('/fila-pcm/os/'.$this->activities[0]);$this->assertResponseOk();
        $this->post('/fila-pcm/os/'.$this->activities[0],$this->postSelection($supplies));$this->assertResponseCode(403);$this->assertSame('pendente',Access::table('Insumos')->get($supplies[0])->controle_pcm);
    }
    private function filterScenario(): array
    {
        Access::table('EvolucaoRegistros')->deleteAll(['atividade_id'=>$this->activities[0]]);
        CompletedOrder::set($this->activities[1],$this->admin,50);
        $this->hours();$this->supplies(1);$this->hours(2,'pessoa-2');$this->supplies(2,2);
        $activity=Access::table('Atividades')->get($this->activities[0]);
        $book=Access::table('Cadernos')->get($activity->caderno_id);
        $areas=Access::table('AreasFabrica');
        $area=$areas->saveOrFail(Access::entity($areas,['nome'=>'CALDEIRARIA','entressafra_id'=>$this->season]));
        $books=Access::table('Cadernos');
        $other=$books->saveOrFail($books->newEntity(['nome'=>'Caderno Caldeiraria','entressafra_id'=>$this->season,'setor_id'=>$book->setor_id,'area_curva_id'=>$area->id,'ativo'=>true]));
        Access::table('Atividades')->updateAll(['caderno_id'=>$other->id],['id'=>$this->activities[2]]);
        // Evolution, not the TOTVS closed flag, defines completion.
        Access::table('Atividades')->updateAll(['situacao_totvs'=>'F'],['id'=>$this->activities[0]]);
        Access::table('Atividades')->updateAll(['situacao_totvs'=>'L'],['id'=>$this->activities[2]]);
        return ['moenda'=>(string)$book->area_curva_id,'caldeiraria'=>(string)$area->id];
    }
    public function testQueueFiltersSectorEvolutionAndCombinationsWithoutWriting(): void
    {
        $sectors=$this->filterScenario();$service=new FilaPcm();$actor=Access::actor($this->admin);
        $db=Access::table('Atividades')->getConnection();$before=[];
        foreach(['atividades','apontamentos','insumos','evolucao_registros','auditorias'] as $table) {
            $before[$table]=$db->execute('SELECT * FROM '.$table.' ORDER BY id')->fetchAll('assoc');
        }
        foreach(['zero'=>0,'andamento'=>1,'concluidas'=>2] as $filter=>$index) {
            $rows=$service->queue($actor,null,['evolucao'=>$filter]);
            $this->assertSame([$this->activities[$index]],array_map('intval',array_column($rows,'id')));
            $this->assertSame(1,$service->queueCount($actor,['evolucao'=>$filter]));
        }
        $this->assertCount(2,$service->queue($actor,null,['setor'=>$sectors['moenda']]));
        $this->assertCount(1,$service->queue($actor,null,['setor'=>$sectors['caldeiraria']]));
        $this->assertSame([],$service->queue($actor,null,['setor'=>$sectors['moenda'],'evolucao'=>'concluidas']));
        $rows=$service->queue($actor,null,['setor'=>$sectors['caldeiraria'],'evolucao'=>'concluidas']);
        $this->assertSame(1,(int)$rows[0]['apontamentos_pendentes']);$this->assertSame(2,(int)$rows[0]['insumos_pendentes']);$this->assertSame(120,(int)$rows[0]['minutos']);
        $this->assertSame(100.0,$rows[0]['percentual_atual']);
        $this->get('/fila-pcm?setor='.$sectors['caldeiraria'].'&evolucao=concluidas');$this->assertResponseOk();
        $this->assertResponseContains('Ver registros');$this->assertResponseContains('100%');
        $this->assertResponseContains('/fila-pcm/os/'.$this->activities[2]);
        $this->get('/fila-pcm/os/'.$this->activities[2]);$this->assertResponseOk();$this->assertResponseContains('Apontamentos de horas');$this->assertResponseContains('Insumos utilizados');
        foreach($before as $table=>$rows) { $this->assertSame($rows,$db->execute('SELECT * FROM '.$table.' ORDER BY id')->fetchAll('assoc')); }
    }
    public function testQueueFilterFormClearInvalidInputAndSeasonIsolation(): void
    {
        $sectors=$this->filterScenario();
        $areas=Access::table('AreasFabrica');$foreign=$areas->saveOrFail(Access::entity($areas,['nome'=>'FORA DO PERÍODO','entressafra_id'=>null]));
        $this->get('/fila-pcm?setor='.$sectors['moenda'].'&evolucao=andamento');$this->assertResponseOk();
        $this->assertResponseNotContains('FORA DO PERÍODO');
        $dom=new \DOMDocument();@$dom->loadHTML((string)$this->_response->getBody());$xp=new \DOMXPath($dom);
        $this->assertSame(1,$xp->query('//form[@id="fila-filtros" and @method="get"]')->length);
        $this->assertSame(1,$xp->query('//select[@name="setor"]/option[@selected and @value="'.$sectors['moenda'].'"]')->length);
        $this->assertSame(1,$xp->query('//select[@name="evolucao"]/option[@selected and @value="andamento"]')->length);
        $this->assertSame('/fila-pcm',$xp->query('//a[text()="Limpar filtros"]')->item(0)->getAttribute('href'));
        $this->assertSame(1,$xp->query('//table/tbody/tr')->length);
        $this->get('/fila-pcm');$this->assertResponseOk();
        $dom=new \DOMDocument();@$dom->loadHTML((string)$this->_response->getBody());$xp=new \DOMXPath($dom);
        $this->assertSame(3,$xp->query('//table/tbody/tr')->length);
        foreach(['setor='.$foreign->id,'setor=1%20OR%201=1','setor[]=1','evolucao[]=zero','evolucao=invalida'] as $query) {
            $this->get('/fila-pcm?'.$query);$this->assertResponseCode(400);
        }
        $this->login($this->pcm);$this->get('/fila-pcm?evolucao=concluidas');$this->assertResponseOk();
        $this->login($this->responsible);$this->get('/fila-pcm?evolucao=concluidas');$this->assertResponseCode(403);
    }
    public function testQueueFiltersBeforePaginationAndPreservesQuery(): void
    {
        $sectors=$this->filterScenario();$activityTable=Access::table('Atividades');$supplyTable=Access::table('Insumos');
        $base=$activityTable->get($this->activities[0])->toArray();unset($base['id']);
        $supply=$supplyTable->find()->where(['atividade_id'=>$this->activities[1]])->firstOrFail()->toArray();unset($supply['id']);
        $ids=[];
        for($i=0;$i<11;$i++) {
            $data=array_replace($base,['os_chave'=>'filtro-'.$i,'os_numero'=>'FILTRO-'.str_pad((string)$i,2,'0',STR_PAD_LEFT)]);
            $id=(int)$activityTable->saveOrFail(Access::entity($activityTable,$data))->id;$ids[]=$id;
            CompletedOrder::set($id,$this->admin);
            $supplyTable->saveOrFail(Access::entity($supplyTable,array_replace($supply,['atividade_id'=>$id])));
        }
        $filters=['setor'=>$sectors['moenda'],'evolucao'=>'concluidas'];$service=new FilaPcm();$actor=Access::actor($this->admin);
        $first=$service->queuePage($actor,1,$filters);$last=$service->queuePage($actor,2,$filters);
        $this->assertSame(11,$first['total']);$this->assertSame(2,$first['pages']);$this->assertCount(10,$first['rows']);$this->assertCount(1,$last['rows']);
        $this->assertSame($ids,array_map('intval',array_column(array_merge($first['rows'],$last['rows']),'id')));
        $this->assertSame(2,$service->queuePage($actor,999,$filters)['page']);
        $this->get('/fila-pcm?'.http_build_query($filters));$this->assertResponseOk();
        $dom=new \DOMDocument();@$dom->loadHTML((string)$this->_response->getBody());$xp=new \DOMXPath($dom);
        $next=$xp->query('//nav[@aria-label="Paginação"]/a[text()="Próxima"]')->item(0)->getAttribute('href');
        parse_str((string)parse_url($next,PHP_URL_QUERY),$query);
        foreach (['pagina'=>'2']+$filters as $key=>$value) { $this->assertSame($value,$query[$key]); }
        $this->assertSame((string)$this->season,$query['entressafra_id']);
        $this->get($next);$this->assertResponseOk();$this->assertResponseContains('Página 2 de 2');$this->assertResponseContains('FILTRO-10');$this->assertResponseNotContains('FILTRO-00');
        if(getenv('FILA_FILTER_PREVIEW'))file_put_contents(TMP.'fila-filtros-preview.html',(string)$this->_response->getBody());
    }
    public function testQueueZeroFilterIncludesMissingAndExplicitZeroAndFollowsEvolutionChanges(): void
    {
        $this->filterScenario();$service=new FilaPcm();$actor=Access::actor($this->admin);
        CompletedOrder::set($this->activities[1],$this->admin,0);
        $this->assertSame(2,$service->queueCount($actor,['evolucao'=>'zero']));
        foreach([1,99] as $percentage) {
            CompletedOrder::set($this->activities[1],$this->admin,$percentage);
            $rows=$service->queue($actor,null,['evolucao'=>'andamento']);
            $this->assertSame([$this->activities[1]],array_map('intval',array_column($rows,'id')));
            $this->assertSame((float)$percentage,$rows[0]['percentual_atual']);
        }
        [$reference]=(new \App\Service\OperacaoRapida())->reference($this->season);
        $evolution=new \App\Service\Evolucao();$state=$evolution->state($this->activities[1],$reference);
        $evolution->saveBatch($this->admin,$this->season,$reference,[['atividade_id'=>$this->activities[1],'percentual'=>100,'revisao'=>$state['revisao']]],bin2hex(random_bytes(16)),true);
        $this->assertSame(2,$service->queueCount($actor,['evolucao'=>'concluidas']));
        $this->assertSame(0,$service->queueCount($actor,['evolucao'=>'andamento']));
    }
    public function testQueueSectorFallbackForOrderWithoutNotebook(): void
    {
        $this->supplies();$activity=Access::table('Atividades')->get($this->activities[0]);
        Access::table('Atividades')->updateAll(['caderno_id'=>null],['id'=>$activity->id]);
        $service=new FilaPcm();$actor=Access::actor($this->admin);$key='equipe_'.$activity->setor_executante_id;
        $this->assertArrayHasKey($key,$service->sectorOptions($actor));
        $rows=$service->queue($actor,null,['setor'=>$key,'evolucao'=>'concluidas']);
        $this->assertCount(1,$rows);$this->assertSame((int)$activity->id,(int)$rows[0]['id']);
        $this->get('/fila-pcm?setor='.$key.'&evolucao=concluidas');$this->assertResponseOk();$this->assertResponseContains('Ver registros');
    }
    private function descriptionScenario(): array
    {
        $sectors=$this->filterScenario();
        foreach (['005316','005525','005897'] as $i=>$number) {
            Access::table('Atividades')->updateAll(['os_numero'=>$number,'os_filial'=>'01','os_chave'=>\App\Integration\Totvs\Orders::key('01',$number)],['id'=>$this->activities[$i]]);
        }
        return $sectors;
    }
    private function descriptionRow(string $number,string $description,string $branch='01'): array
    {
        return ['numero'=>$number,'filial'=>$branch,'codigo_servico'=>'1','codigo_area'=>'A0','centro_custo'=>'CC','data_origem'=>'20260101','situacao'=>'C','termino'=>'S','bem'=>'BEM DO TOTVS','servico'=>'ENTRESSAFRA','correspondencias_servico'=>1,'identidades'=>1,'descricao_totvs'=>$description];
    }
    public function testLiveDescriptionsAreBatchedForHoursSuppliesBothAndFilteredQueue(): void
    {
        $sectors=$this->descriptionScenario();$calls=new \ArrayObject();
        $descriptions=['005316'=>'MANUTENÇÃO CORRETIVA ILUMINAÇÃO','005525'=>'DESMONTAGEM E MONTAGEM CORRENTE E TALISCAS DA ESTEIRA METALICA','005897'=>'DESMONTAGEM 4 T'];
        $this->mockService(\App\Integration\Totvs\ListOrders::class,fn()=>new \App\Integration\Totvs\ListOrders(function($sql,$params)use(&$calls,$descriptions){
            $calls[]=$params;$out=[];
            foreach($descriptions as $number=>$description) { if(in_array($number,$params,true))$out[]=$this->descriptionRow($number,$description); }
            return $out;
        }));
        $db=Access::table('Atividades')->getConnection();$before=[];
        foreach(['atividades','apontamentos','insumos','evolucao_registros','auditorias'] as $table)$before[$table]=$db->execute('SELECT * FROM '.$table.' ORDER BY id')->fetchAll('assoc');
        $this->get('/fila-pcm');$this->assertResponseOk();$this->assertCount(1,$calls);
        foreach($descriptions as $number=>$description) { $this->assertResponseContains($number);$this->assertResponseContains($description); }
        $this->assertResponseNotContains('Descrição não informada');$this->assertResponseContains('Bem de teste');
        $this->assertSame('01',$calls[0]['filial']);$this->assertSame(['005316','005525','005897'],[$calls[0]['n0'],$calls[0]['n1'],$calls[0]['n2']]);
        foreach($this->activities as $i=>$id) {
            $this->get('/fila-pcm/os/'.$id);$this->assertResponseOk();$this->assertResponseContains(array_values($descriptions)[$i]);
        }
        $this->get('/fila-pcm?setor='.$sectors['caldeiraria'].'&evolucao=concluidas');$this->assertResponseOk();
        $this->assertResponseContains('DESMONTAGEM 4 T');$this->assertResponseNotContains('MANUTENÇÃO CORRETIVA ILUMINAÇÃO');
        $this->assertSame('005897',$calls[count($calls)-1]['n0']);$this->assertSame('',$calls[count($calls)-1]['n1']);
        foreach($before as $table=>$rows)$this->assertSame($rows,$db->execute('SELECT * FROM '.$table.' ORDER BY id')->fetchAll('assoc'));
    }
    public function testLocalDescriptionKeepsPriorityAndDoesNotRequireTotvsLookup(): void
    {
        $this->descriptionScenario();
        (new \App\Service\DescricaoAtividade())->save($this->admin,$this->activities[0],'Complemento local conferido','PCM');
        $calls=new \ArrayObject();
        $this->mockService(\App\Integration\Totvs\ListOrders::class,fn()=>new \App\Integration\Totvs\ListOrders(function($sql,$params)use(&$calls){$calls[]=$params;return [];}));
        $this->get('/fila-pcm');$this->assertResponseOk();$this->assertResponseContains('Complemento local conferido');
        $this->assertNotContains('005316',$calls[0]);
        $this->get('/fila-pcm/os/'.$this->activities[0]);$this->assertResponseOk();$this->assertResponseContains('Complemento local conferido');$this->assertCount(1,$calls);
        $this->assertSame('Complemento local conferido',Access::table('Atividades')->get($this->activities[0])->descricao_atividade);
    }
    public function testMissingAmbiguousOrUnavailableTotvsKeepsQueueAndDetailAccessible(): void
    {
        $this->descriptionScenario();$table=Access::table('Atividades');
        $snapshot=json_decode($table->get($this->activities[0])->snapshot_totvs,true);$snapshot['descricao_totvs']='Descrição histórica preservada';
        $table->updateAll(['snapshot_totvs'=>json_encode($snapshot,JSON_THROW_ON_ERROR)],['id'=>$this->activities[0]]);
        foreach(['missing','unavailable','ambiguous','blank','wrong_branch'] as $mode) {
            $this->mockService(\App\Integration\Totvs\ListOrders::class,fn()=>new \App\Integration\Totvs\ListOrders(function()use($mode){
                if($mode==='unavailable')throw new \RuntimeException('Credenciais privadas não devem aparecer');
                if($mode==='missing')return [];
                $row=$this->descriptionRow('005316',$mode==='blank'?'':'Descrição que não pode substituir o histórico',$mode==='wrong_branch'?'02':'01');
                return $mode==='ambiguous'?[$row,$row]:[$row];
            }));
            $this->get('/fila-pcm');$this->assertResponseOk();$this->assertResponseContains('Descrição histórica preservada');$this->assertResponseContains('Ver registros');
            $this->assertResponseNotContains('Credenciais privadas');$this->assertResponseNotContains('Descrição que não pode substituir');
            $this->get('/fila-pcm/os/'.$this->activities[0]);$this->assertResponseOk();$this->assertResponseContains('Descrição histórica preservada');
        }
    }
}
