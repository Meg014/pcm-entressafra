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
        $this->get('/fila-pcm/os/'.$activity->id);$this->assertResponseCode(403);
        $this->post('/fila-pcm/os/'.$activity->id,$this->postSelection($supplies));$this->assertResponseCode(403);
        $t->updateAll(['status'=>'encerrada'],['id'=>$this->season]);$this->get('/fila-pcm/os/'.$this->activities[0]);$this->assertResponseOk();
        $this->post('/fila-pcm/os/'.$this->activities[0],$this->postSelection($supplies));$this->assertResponseCode(403);$this->assertSame('pendente',Access::table('Insumos')->get($supplies[0])->controle_pcm);
    }
}
