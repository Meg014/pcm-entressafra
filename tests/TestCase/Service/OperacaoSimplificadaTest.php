<?php
declare(strict_types=1);
namespace App\Test\TestCase\Service;
use App\Service\{Access,Users,Activities,TimeEntries,FilaPcm,OperacaoRapida,Evolucao,ConflictException};
use App\Test\Support\FakeTotvs;
use Cake\TestSuite\{TestCase,IntegrationTestTrait};
class OperacaoSimplificadaTest extends TestCase
{
    use IntegrationTestTrait;
    private int $admin;private int $computer;private array $activities=[];private array $sectors=[];private int $season;private TimeEntries $entries;
    protected function setUp(): void
    {
        parent::setUp();
        foreach(['EvolucaoPesos','EvolucaoMetas','EvolucaoRegistros','EvolucaoLotes','EvolucaoVersoes','CalendariosOperacionais','EvolucaoSemanas','VinculosItens','VinculosLotes','Auditorias','Apontamentos','Atividades','UsuariosSetores','Usuarios','Cadernos','Setores','AreasFabrica','Entressafras'] as $alias){Access::table($alias)->deleteAll([]);}
        $now=gmdate('Y-m-d H:i:s');$t=Access::table('Usuarios');$this->admin=(int)$t->saveOrFail(Access::entity($t,['nome'=>'Admin teste','login'=>'op-admin','senha_hash'=>password_hash('teste123',PASSWORD_DEFAULT),'perfil'=>'ADMIN_PCM','ativo'=>true,'created'=>$now,'modified'=>$now]))->id;
        $t=Access::table('Entressafras');$this->season=(int)$t->saveOrFail($t->newEntity(['nome'=>'Operação teste','data_inicio'=>'2026-09-01','data_fim'=>'2026-10-20','status'=>'ativa']))->id;
        foreach(['A0','A1'] as $i=>$area){$t=Access::table('Setores');$sector=$t->saveOrFail($t->newEntity(['nome'=>'Setor '.$i,'ativo'=>true,'area_totvs'=>$area,'filial_totvs'=>'01']));$this->sectors[]=(int)$sector->id;
            $t=Access::table('Cadernos');$book=$t->saveOrFail($t->newEntity(['nome'=>'Caderno '.$i,'entressafra_id'=>$this->season,'setor_id'=>$sector->id,'descricao'=>'Descrição do caderno','ativo'=>true]));
            $this->activities[]=(new Activities(new FakeTotvs()))->link($this->admin,(int)$book->id,'os-'.$i);}
        $this->computer=(new Users())->save($this->admin,['nome'=>'Computador 1','login'=>'computador-1','senha'=>'teste123','perfil'=>'RESPONSAVEL_SETOR','computador_operacional'=>'1','ativo'=>'1','setores'=>[$this->sectors[0]]]);
        $this->entries=new TimeEntries(new FakeTotvs());
        $s=new Evolucao();$s->configureCalendar($this->admin,$this->season,array_column($s->calendar($this->season),'referencia'));
        $this->mockService(\App\Integration\Totvs\GatewayInterface::class,function(){
            $g=$this->createMock(\App\Integration\Totvs\GatewayInterface::class);$g->method('order')->willReturnCallback(fn($key)=>(new FakeTotvs())->order($key));$g->method('employee')->willReturnCallback(fn($key)=>(new FakeTotvs())->employee($key));$g->method('employees')->willReturnCallback(fn($term)=>(new FakeTotvs())->employees($term));
            $g->method('orders')->willReturnCallback(function($term,$area,$branch){$out=[];foreach(Access::table('Atividades')->find()->all() as $a){if($a->area_executante_totvs!==$area||$a->os_filial!==$branch||($term!==''&&mb_stripos($a->os_numero,$term)===false&&mb_stripos($a->servico,$term)===false))continue;$r=(new FakeTotvs($area))->order($a->os_chave);$r['numero']=$a->os_numero;$out[]=$r;}return $out;});return $g;
        });
        $this->auth($this->admin);
    }
    private function auth(int $id): void {$u=Access::actor($id);$this->session(['Periodo'=>['id'=>$this->season,'usuario_id'=>$id],'Auth'=>['id'=>$id,'stamp'=>hash('sha256',$u->senha_hash),'last'=>time()]]);}
    private function entry(int $index=0,string $start='2026-10-01T07:00',string $end='2026-10-01T09:00'): int
    {return $this->entries->save($this->admin,['atividade_id'=>$this->activities[$index],'funcionario_chave'=>'pessoa-1','inicio'=>$start,'fim'=>$end,'descricao'=>'Serviço de teste']);}
    private function select(int $id): array {return ['id'=>$id,'revisao'=>Access::table('Apontamentos')->get($id)->revisao_pcm];}
    public function testComputerOnlyHasOperationalRoutesAndExistingUsersAreNotConverted(): void
    {
        $this->assertTrue(Access::operational(Access::actor($this->computer)));$this->assertFalse(Access::operational(Access::actor($this->admin)));
        $this->auth($this->computer);
        foreach(['/apontamentos/add','/lancar-porcentagem','/apontamentos'] as $url){$this->get($url);$this->assertResponseOk();}
        foreach(['/usuarios','/setores','/cadernos','/entressafras','/atividades','/atividades/add','/fila-pcm','/controle-pcm','/evolucao','/evolucao/configuracao','/vinculos-lote'] as $url){$this->get($url);$this->assertResponseCode(403);}
        $this->get('/');$this->assertRedirect('/apontamentos/add');
        $this->get('/apontamentos/add');$this->assertResponseNotContains('name="entressafra_id"');$this->assertResponseNotContains('name="setor_id"');$this->assertResponseNotContains('name="caderno_id"');$this->assertResponseNotContains('Cadastros administrativos');
        $this->post('/login',['login'=>'computador-1','senha'=>'teste123']);$this->assertResponseCode(403);
    }
    public function testLinkedOrderLookupRequiresActiveSeasonAndAuthorizedSectorAndPreservesNumber(): void
    {
        Access::table('Atividades')->updateAll(['os_numero'=>'005300'],['id'=>$this->activities[0]]);
        $this->auth($this->computer);$this->get('/operacao/ordens?q=0053');$this->assertResponseOk();$data=json_decode((string)$this->_response->getBody(),true);
        $this->assertCount(1,$data['results']);$this->assertSame('005300',$data['results'][0]['numero']);$this->assertSame('Descrição do caderno',$data['results'][0]['descricao_caderno']);
        $this->get('/operacao/ordens?q=os-1');$this->assertResponseOk();$this->assertSame([],json_decode((string)$this->_response->getBody(),true)['results']);
        $this->get('/operacao/ordens?q='.urlencode("' OR 1=1 --"));$this->assertResponseOk();$this->assertSame([],json_decode((string)$this->_response->getBody(),true)['results']);
        Access::table('Entressafras')->updateAll(['status'=>'encerrada'],['id'=>$this->season]);$this->get('/operacao/ordens?q=0053');$this->assertResponseCode(409);
    }
    public function testComputerCanSaveValidEntryAndLookupEmployeeButCannotConfirm(): void
    {
        $this->auth($this->computer);$this->enableCsrfToken();
        $this->get('/apontamentos/funcionarios?q=Maria');$this->assertResponseOk();$this->assertResponseContains('0001');
        $this->post('/apontamentos/add',['atividade_id'=>$this->activities[0],'funcionario_chave'=>'pessoa-1','inicio'=>'2026-10-01T07:00','fim'=>'2026-10-01T09:00','descricao'=>'Teste operacional']);$this->assertRedirect('/apontamentos/add');
        $entry=Access::table('Apontamentos')->find()->firstOrFail();$this->assertSame($this->computer,(int)$entry->criado_por);$this->assertSame('pendente',$entry->controle_pcm);
        $this->post('/apontamentos/change/'.$entry->id,['acao'=>'lancado','motivo'=>'Tentativa']);$this->assertResponseCode(403);
        $this->post('/fila-pcm/os/'.$this->activities[0],['selecionados'=>[$entry->id],'confirmar'=>'1','acao'=>'lancado']);$this->assertResponseCode(403);
    }
    public function testComputerCannotForgeOtherSectorOrInactiveSeasonOnSave(): void
    {
        try{$this->entries->save($this->computer,['atividade_id'=>$this->activities[1],'funcionario_chave'=>'pessoa-1','inicio'=>'2026-10-01T07:00','fim'=>'2026-10-01T09:00','descricao'=>'Teste']);$this->fail();}catch(\Cake\Http\Exception\ForbiddenException){}
        Access::table('Entressafras')->updateAll(['status'=>'encerrada'],['id'=>$this->season]);$this->expectException(\DomainException::class);
        $this->entries->save($this->computer,['atividade_id'=>$this->activities[0],'funcionario_chave'=>'pessoa-1','inicio'=>'2026-10-01T07:00','fim'=>'2026-10-01T09:00','descricao'=>'Teste']);
    }
    public function testQueueGroupsOnlyPendingEntriesAndOrdersByReceiptNotWorkDate(): void
    {
        $a=$this->entry(1);$b=$this->entry(0,'2026-09-01T07:00','2026-09-01T08:00');
        Access::table('Apontamentos')->updateAll(['created'=>'2026-10-01 10:00:00'],['id'=>$a]);Access::table('Apontamentos')->updateAll(['created'=>'2026-10-01 11:00:00'],['id'=>$b]);
        $q=(new FilaPcm())->queue(Access::actor($this->admin));$this->assertSame($this->activities[1],(int)$q[0]['id']);$this->assertSame(120,(int)$q[0]['minutos']);$this->assertCount(2,$q);
        $this->get('/fila-pcm');$this->assertResponseOk();$this->assertResponseContains('Ver apontamentos');
    }
    public function testMultipleConfirmationsLeaveQueueAndOnlyNewEntryReturns(): void
    {
        $a=$this->entry();$b=$this->entry(0,'2026-10-01T09:00','2026-10-01T10:00');$service=new FilaPcm();
        $this->assertSame(2,$service->confirm($this->admin,$this->activities[0],[$this->select($a),$this->select($b)],'lancado','Lançamento manual conferido',true));
        $this->assertSame([],$service->queue(Access::actor($this->admin)));
        $c=$this->entry(0,'2026-10-02T07:00','2026-10-02T08:00');$q=$service->queue(Access::actor($this->admin));$this->assertCount(1,$q);$this->assertSame(1,(int)$q[0]['quantidade']);
        foreach([$a,$b] as $id){$r=Access::table('Apontamentos')->get($id);$this->assertSame('lancado',$r->controle_pcm);$this->assertSame($this->admin,(int)$r->confirmado_por);$this->assertNotNull($r->confirmado_em);}
        $this->get('/fila-pcm/os/'.$this->activities[0]);$this->assertResponseOk();$this->assertResponseContains('pcm-tratado');
        $this->assertSame(3,Access::table('Apontamentos')->find()->count());$this->assertGreaterThan(0,Access::table('Auditorias')->find()->where(['apontamento_id'=>$a,'acao'=>'confirmacao_totvs'])->count());
    }
    public function testDuplicateStaleOrCrossOrderConfirmationRollsBackAndReopeningRequiresReason(): void
    {
        $a=$this->entry();$b=$this->entry(1,'2026-10-02T07:00','2026-10-02T08:00');$s=new FilaPcm();
        try{$s->confirm($this->admin,$this->activities[0],[$this->select($a),$this->select($b)],'lancado','',true);$this->fail();}catch(\DomainException){}
        $this->assertSame('pendente',Access::table('Apontamentos')->get($a)->controle_pcm);
        $stale=$this->select($a);$s->confirm($this->admin,$this->activities[0],[$stale],'lancado','',true);
        try{$s->confirm($this->admin,$this->activities[0],[$stale],'lancado','',true);$this->fail();}catch(\DomainException){}
        try{$s->confirm($this->admin,$this->activities[0],[$this->select($a)],'pendente','',true);$this->fail();}catch(\DomainException){}
        $s->confirm($this->admin,$this->activities[0],[$this->select($a)],'pendente','Confirmação indevida verificada',true);$this->assertSame('pendente',Access::table('Apontamentos')->get($a)->controle_pcm);
        $this->assertSame(1,Access::table('Auditorias')->find()->where(['apontamento_id'=>$a,'acao'=>'reabertura_totvs'])->count());
    }
    public function testConfirmedEntryCannotBeEditedUntilReopenedWithAudit(): void
    {
        $id=$this->entry();(new FilaPcm())->confirm($this->admin,$this->activities[0],[$this->select($id)],'lancado','',true);
        $this->expectException(\DomainException::class);$this->entries->save($this->admin,['inicio'=>'2026-10-01T07:00','fim'=>'2026-10-01T10:00','descricao'=>'Alteração','motivo'=>'Tentativa'], $id);
    }
    public function testQueueHttpConfirmationRequiresExplicitConsentAndTracksRevision(): void
    {
        $id=$this->entry();$this->enableCsrfToken();$data=['selecionados'=>[$id],'revisoes'=>[$id=>0],'acao'=>'lancado'];
        $this->post('/fila-pcm/os/'.$this->activities[0],$data);$this->assertResponseOk();$this->assertSame('pendente',Access::table('Apontamentos')->get($id)->controle_pcm);
        $this->post('/fila-pcm/os/'.$this->activities[0],$data+['confirmar'=>'1']);$this->assertRedirect();$this->assertSame('lancado',Access::table('Apontamentos')->get($id)->controle_pcm);
    }
    public function testQuickAccumulatedProgressHasAutomaticWeekAndRetroactivePermission(): void
    {
        $this->auth($this->computer);$this->get('/operacao/ordens?q=os-&tipo=evolucao');$this->assertResponseOk();$row=json_decode((string)$this->_response->getBody(),true)['results'][0];
        $t=Access::table('AreasFabrica');$area=$t->saveOrFail(Access::entity($t,['nome'=>'MOENDA','entressafra_id'=>$this->season]));$book=Access::table('Atividades')->get($this->activities[0])->caderno_id;Access::table('Cadernos')->updateAll(['area_curva_id'=>$area->id],['id'=>$book]);
        $this->enableCsrfToken();$this->post('/lancar-porcentagem?area_id='.$area->id,['chave'=>bin2hex(random_bytes(16)),'alteracoes_json'=>json_encode([['atividade_id'=>$this->activities[0],'percentual'=>30,'base'=>0,'revisao'=>0,'referencia'=>$row['referencia']]])]);$this->assertRedirect();
        $state=(new Evolucao())->state($this->activities[0],$row['referencia']);$this->assertSame(30.0,$state['percentual']);$this->assertSame($this->computer,(int)$state['registro']['usuario_id']);
        $this->post('/lancar-porcentagem',['atividade_id'=>$this->activities[0],'percentual'=>20]);$this->assertResponseOk();$this->assertResponseContains('lançamento individual foi encerrado');
        $this->get('/lancar-porcentagem');$this->assertResponseOk();$this->assertResponseNotContains('name="referencia"');$this->assertResponseNotContains('name="setor_id"');$this->assertResponseNotContains('name="caderno_id"');
    }
    public function testNoWeightsShowsUnweightedCountInsteadOfOfficialCurve(): void
    {
        $this->get('/controle-pcm?entressafra_id='.$this->season);$this->assertResponseOk();$this->assertResponseContains('Evolução física simples — não ponderada');$this->assertResponseContains('Não representa a Curva S ponderada oficial');$this->assertResponseContains('Pesos oficiais pendentes');$this->assertResponseContains('10 por página');
    }
    public function testGlobalConflictAcrossOrdersStillUsesFullDatetime(): void
    {
        $this->entry();$this->entry(1,'2026-10-02T07:00','2026-10-02T09:00');$this->entry(1,'2026-10-01T09:00','2026-10-01T10:00');$this->expectException(ConflictException::class);$this->entry(1,'2026-10-01T08:00','2026-10-01T09:30');
    }
    public function testLoginDestinationsAndLegacyConfirmationRequireConsent(): void
    {
        $this->enableCsrfToken();$this->session(['Auth'=>[]]);$this->post('/login',['login'=>'computador-1','senha'=>'teste123']);$this->assertRedirect('/apontamentos/add');
        $this->session(['Auth'=>[]]);$this->post('/login',['login'=>'op-admin','senha'=>'teste123']);$this->assertRedirect('/escolher-entressafra');
        $this->auth($this->admin);
        $id=$this->entry();$this->post('/apontamentos/change/'.$id,['acao'=>'lancado','motivo'=>'Conferido']);$this->assertRedirect();$this->assertSame('pendente',Access::table('Apontamentos')->get($id)->controle_pcm);
        $this->post('/apontamentos/change/'.$id,['acao'=>'lancado','motivo'=>'Conferido','confirmar'=>'1']);$this->assertRedirect();$this->assertSame('lancado',Access::table('Apontamentos')->get($id)->controle_pcm);
    }
    public function testComputerRestrictionIsPreservedUnlessExplicitlyChangedByAdministrator(): void
    {
        (new Users())->save($this->admin,['nome'=>'Computador 1','login'=>'computador-1','perfil'=>'RESPONSAVEL_SETOR','ativo'=>'1','setores'=>[$this->sectors[0]]],$this->computer);
        $this->assertTrue(Access::operational(Access::actor($this->computer)));
        $this->expectException(\DomainException::class);(new Users())->save($this->admin,['nome'=>'Computador 1','login'=>'computador-1','perfil'=>'ADMIN_PCM','ativo'=>'1','setores'=>[$this->sectors[0]]],$this->computer);
    }
}
