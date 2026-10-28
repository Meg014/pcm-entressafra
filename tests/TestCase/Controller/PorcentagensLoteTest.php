<?php
declare(strict_types=1);
namespace App\Test\TestCase\Controller;
use App\Service\{Access,PeriodoContexto,PorcentagensLote,Evolucao,OperacaoRapida,Curva};
use Cake\TestSuite\{TestCase,IntegrationTestTrait};
class PorcentagensLoteTest extends TestCase
{
    use IntegrationTestTrait;
    private int $admin;private int $responsible;private int $season;private array $areas=[],$teams=[],$ids=[];private string $reference;
    protected function setUp(): void
    {
        parent::setUp();PeriodoContexto::set(null);
        foreach(['PendenciasOrdens','PendenciasConsultas','EvolucaoPesos','EvolucaoMetas','EvolucaoRegistros','EvolucaoLotes','EvolucaoVersoes','CalendariosOperacionais','EvolucaoSemanas','VinculosItens','VinculosLotes','Auditorias','Apontamentos','Atividades','UsuariosSetores','Usuarios','Cadernos','AreasFabrica','Setores','Entressafras'] as $alias)Access::table($alias)->deleteAll([]);
        $t=Access::table('Usuarios');foreach(['ADMIN_PCM','RESPONSAVEL_SETOR'] as $role){$u=$t->saveOrFail(Access::entity($t,['nome'=>$role,'login'=>$role,'senha_hash'=>password_hash('teste123',PASSWORD_DEFAULT),'perfil'=>$role,'ativo'=>true,'created'=>gmdate('Y-m-d H:i:s'),'modified'=>gmdate('Y-m-d H:i:s')]));if($role==='ADMIN_PCM')$this->admin=(int)$u->id;else $this->responsible=(int)$u->id;}
        $t=Access::table('Entressafras');$this->season=(int)$t->saveOrFail($t->newEntity(['nome'=>'2026/2027','data_inicio'=>'2026-09-01','data_fim'=>'2027-04-10','status'=>'ativa']))->id;
        foreach(['MOENDA','CALDEIRA'] as $name){$t=Access::table('AreasFabrica');$this->areas[]=(int)$t->saveOrFail(Access::entity($t,['nome'=>$name,'entressafra_id'=>$this->season]))->id;}
        foreach(['MECANI','ELETRI','CALDEI'] as $name){$t=Access::table('Setores');$this->teams[]=(int)$t->saveOrFail(Access::entity($t,['nome'=>$name,'area_totvs'=>$name,'filial_totvs'=>'01','ativo'=>true]))->id;}
        $t=Access::table('UsuariosSetores');$t->saveOrFail(Access::entity($t,['usuario_id'=>$this->responsible,'setor_id'=>$this->teams[0]]));
        foreach($this->areas as $area){$t=Access::table('Cadernos');$b=$t->saveOrFail(Access::entity($t,['nome'=>'Caderno '.$area,'entressafra_id'=>$this->season,'area_curva_id'=>$area,'ativo'=>true]));foreach($this->teams as $team){$t=Access::table('CadernosSetores');$t->saveOrFail(Access::entity($t,['caderno_id'=>$b->id,'setor_id'=>$team]));}
            for($i=0;$i<($area===$this->areas[0]?260:1);$i++){$t=Access::table('Atividades');$number=str_pad((string)(6000+count($this->ids)),6,'0',STR_PAD_LEFT);$id=(int)$t->saveOrFail(Access::entity($t,['entressafra_id'=>$this->season,'caderno_id'=>$b->id,'setor_executante_id'=>$this->teams[$i%3],'area_executante_totvs'=>['MECANI','ELETRI','CALDEI'][$i%3],'os_numero'=>$number,'os_filial'=>'01','os_chave'=>\App\Integration\Totvs\Orders::key('01',$number),'servico'=>'ENTRESSAFRA GENERICA','descricao_atividade'=>'Atividade específica '.$number,'bem'=>'Equipamento '.$number,'snapshot_totvs'=>'{}','situacao_totvs'=>'L','disponivel_totvs'=>true,'criado_por'=>$this->admin,'created'=>gmdate('Y-m-d H:i:s'),'modified'=>gmdate('Y-m-d H:i:s')]))->id;$this->ids[]=$id;}
        }
        PeriodoContexto::set($this->season);$this->reference=(new OperacaoRapida())->reference($this->season)[0];$this->login($this->admin);$this->enableCsrfToken();
    }
    protected function tearDown(): void {PeriodoContexto::set(null);parent::tearDown();}
    private function login(int $id): void {$u=Access::actor($id);$this->session(['Auth'=>['id'=>$id,'stamp'=>hash('sha256',$u->senha_hash),'last'=>time()],'Periodo'=>['id'=>$this->season,'usuario_id'=>$id]]);}
    private function row(int $index,float $value,float $base=0,int $revision=0): array {return ['atividade_id'=>$this->ids[$index],'percentual'=>$value,'base'=>$base,'revisao'=>$revision,'referencia'=>$this->reference];}
    public function testSectorUsesPrincipalAreaAcrossTeamsAndPaginationAndNumberSearch(): void
    {
        $s=new PorcentagensLote();$admin=Access::actor($this->admin);$one=$s->page($admin,$this->season,$this->areas[0],'',1);$six=$s->page($admin,$this->season,$this->areas[0],'',6);
        $this->assertSame(260,$one['total']);$this->assertCount(50,$one['rows']);$this->assertCount(10,$six['rows']);$this->assertSame('006000',$one['rows'][0]['numero']);$this->assertSame('006002',$one['rows'][2]['numero']);
        $this->assertSame(1,$s->page($admin,$this->season,$this->areas[0],'006025',1)['total']);$this->assertSame(1,$s->page($admin,$this->season,$this->areas[0],'006260',1)['total']);
        $this->get('/lancar-porcentagem?area_id='.$this->areas[0]);$this->assertResponseOk();$this->assertResponseContains('Atividade específica 006000');
        $dom=new \DOMDocument();@$dom->loadHTML((string)$this->_response->getBody());$xp=new \DOMXPath($dom);$weeks=count((new \App\Service\EvolucaoSemanal())->weeks($this->season));$this->assertSame(4+$weeks,$xp->query('//main//table/thead/tr/th')->length);$this->assertSame(50*$weeks,$xp->query('//*[@data-percentage]')->length);
        $this->assertSame(1,$xp->query('//button[@id="porcentagens-salvar"]')->length);
        foreach(['setor_id','caderno_id','referencia','observacoes','motivo'] as $field)$this->assertSame(0,$xp->query('//*[@name="'.$field.'"]')->length);
        $this->assertResponseNotContains('Sem atualização na semana');$this->assertResponseNotContains('Lançamento em lote');
    }
    public function testNumberSearchWithoutSectorAndWithDifferentSectorCanSave(): void
    {
        $s=new PorcentagensLote();$admin=Access::actor($this->admin);
        $this->assertSame(0,$s->page($admin,$this->season,0,'',1)['total']);
        foreach([0,$this->areas[0]] as $area){
            $page=$s->page($admin,$this->season,$area,'006260',1);
            $this->assertSame(1,$page['total']);$this->assertSame($this->ids[260],$page['rows'][0]['id']);
            $this->get('/lancar-porcentagem?area_id='.$area.'&os=006260');$this->assertResponseOk();
            $this->assertResponseContains('Atividade específica 006260');$this->assertResponseContains('id="porcentagens-salvar"');
            $html=(string)$this->_response->getBody();$this->assertLessThan(strpos($html,'name="area_id"'),strpos($html,'name="os"'));
        }
        $this->post('/lancar-porcentagem?area_id='.$this->areas[0].'&os=006260',['chave'=>bin2hex(random_bytes(16)),'alteracoes_json'=>json_encode([$this->row(260,30)])]);
        $this->assertRedirect();$this->assertSame(30.0,(new Evolucao())->state($this->ids[260],$this->reference)['percentual']);
    }
    public function testNumberSearchNeverBypassesExecutorPermissions(): void
    {
        $s=new PorcentagensLote();$user=Access::actor($this->responsible);
        $this->assertSame(0,$s->page($user,$this->season,0,'006001',1)['total']);
        $this->assertSame(1,$s->page($user,$this->season,0,'006000',1)['total']);
        try{$s->save($this->responsible,$this->season,0,[$this->row(1,10)],bin2hex(random_bytes(16)));$this->fail('Unauthorized O.S. accepted');}
        catch(\DomainException){$this->assertSame(0,Access::table('EvolucaoRegistros')->find()->count());}
    }
    public function testAtomicSaveMoreThanTwoHundredAndIdempotency(): void
    {
        $s=new PorcentagensLote();$rows=[];for($i=0;$i<250;$i++)$rows[]=$this->row($i,35);$key=bin2hex(random_bytes(16));
        $result=$s->save($this->admin,$this->season,$this->areas[0],$rows,$key);$this->assertSame(250,$result['quantidade']);$this->assertSame($result,$s->save($this->admin,$this->season,$this->areas[0],$rows,$key));
        $this->assertSame(250,Access::table('EvolucaoRegistros')->find()->count());$this->assertSame(0.0,(new Evolucao())->state($this->ids[259],$this->reference)['percentual']);$this->assertSame(250,Access::table('Auditorias')->find()->where(['acao'=>'evolucao_lancamento'])->count());
        $this->assertSame(0,Access::table('Apontamentos')->find()->count());
    }
    public function testAccumulatedCarryForwardOnlyModifiedRowsAndImmediateCurve(): void
    {
        $e=new Evolucao();$e->saveBatch($this->admin,$this->season,'2026-10-12',[['atividade_id'=>$this->ids[0],'percentual'=>20,'revisao'=>0]],bin2hex(random_bytes(16)));
        $s=new PorcentagensLote();$this->assertSame(20.0,$s->page(Access::actor($this->admin),$this->season,$this->areas[0],'006000',1)['rows'][0]['percentual']);
        $result=$s->save($this->admin,$this->season,$this->areas[0],[$this->row(0,35,20),$this->row(1,0)],bin2hex(random_bytes(16)));$this->assertSame(1,$result['quantidade']);
        $state=(new Evolucao())->state($this->ids[0],$this->reference);$this->assertSame(35.0,$state['percentual']);$this->assertSame(15.0,$state['variacao']);$this->assertSame(2,Access::table('EvolucaoRegistros')->find()->count());
        $s->save($this->admin,$this->season,$this->areas[0],[$this->row(0,35,35,1)],bin2hex(random_bytes(16)));$this->assertSame(2,Access::table('EvolucaoRegistros')->find()->count());
        $this->assertEqualsWithDelta(35/260,(new Curva())->report($this->season,(string)$this->areas[0])['metrics']['simples'],0.0001);
    }
    public function testReductionStaleAndInvalidValueRejectEntireBatch(): void
    {
        $s=new PorcentagensLote();$s->save($this->admin,$this->season,$this->areas[0],[$this->row(0,20)],bin2hex(random_bytes(16)));
        foreach([[$this->row(1,15),$this->row(0,10,20,1)],[$this->row(1,15),$this->row(0,40)],[$this->row(1,15),$this->row(2,101)]] as $rows){
            try{$s->save($this->admin,$this->season,$this->areas[0],$rows,bin2hex(random_bytes(16)));$this->fail('Invalid batch accepted');}catch(\DomainException){$this->assertSame(1,Access::table('EvolucaoRegistros')->find()->count());}
        }
        $this->assertSame(20.0,(new Evolucao())->state($this->ids[0],$this->reference)['percentual']);
    }
    public function testPermissionsAndBookMembershipAreRevalidated(): void
    {
        $s=new PorcentagensLote();$r=Access::actor($this->responsible);$page=$s->page($r,$this->season,$this->areas[0],'',1);$this->assertSame(87,$page['total']);
        try{$s->save($this->responsible,$this->season,$this->areas[0],[$this->row(1,10)],bin2hex(random_bytes(16)));$this->fail('Unauthorized executor accepted');}catch(\DomainException){$this->assertSame(0,Access::table('EvolucaoRegistros')->find()->count());}
        $t=Access::table('Atividades');$a=$t->get($this->ids[0]);$a->caderno_id=null;$t->saveOrFail($a);
        $this->expectException(\DomainException::class);$s->save($this->admin,$this->season,$this->areas[0],[$this->row(0,10)],bin2hex(random_bytes(16)));
    }
    public function testHttpSavesBatchAndKeepsSectorAndRejectsOldIndividualPath(): void
    {
        $url='/lancar-porcentagem?area_id='.$this->areas[0].'&os=0060&pagina=2';$this->configRequest(['headers'=>['X-Requested-With'=>'XMLHttpRequest']]);
        $this->post($url,['chave'=>bin2hex(random_bytes(16)),'alteracoes_json'=>json_encode([$this->row(0,30),$this->row(55,50)])]);$this->assertResponseOk();$data=json_decode((string)$this->_response->getBody(),true);$this->assertSame(2,$data['quantidade']);
        $this->get($url);$this->assertResponseOk();$this->assertResponseContains('value="50"');
        $this->post('/lancar-porcentagem',['atividade_id'=>$this->ids[0],'percentual'=>70]);$this->assertResponseOk();$this->assertResponseContains('lançamento individual foi encerrado');$this->assertSame(2,Access::table('EvolucaoRegistros')->find()->count());
    }
}
