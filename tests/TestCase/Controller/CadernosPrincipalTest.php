<?php
declare(strict_types=1);
namespace App\Test\TestCase\Controller;
use App\Service\{Access,PeriodoContexto,ListLinks,Curva};
use App\Integration\Totvs\ListOrders;
use Cake\TestSuite\{TestCase,IntegrationTestTrait};
class CadernosPrincipalTest extends TestCase
{
    use IntegrationTestTrait;
    private int $admin;private array $periods=[];private array $areas=[];
    protected function setUp(): void
    {
        parent::setUp();PeriodoContexto::set(null);
        foreach(['EvolucaoPesos','EvolucaoMetas','EvolucaoRegistros','EvolucaoLotes','EvolucaoVersoes','CalendariosOperacionais','EvolucaoSemanas','VinculosItens','VinculosLotes','Auditorias','Apontamentos','Atividades','UsuariosSetores','Usuarios','Cadernos','AreasFabrica','Setores','Entressafras'] as $alias)Access::table($alias)->deleteAll([]);
        $t=Access::table('Usuarios');$now=gmdate('Y-m-d H:i:s');$user=$t->saveOrFail(Access::entity($t,['nome'=>'Admin cadernos','login'=>'admin-cadernos','senha_hash'=>password_hash('teste123',PASSWORD_DEFAULT),'perfil'=>'ADMIN_PCM','ativo'=>true,'created'=>$now,'modified'=>$now]));$this->admin=(int)$user->id;
        foreach(['2026/2027','2027/2028'] as $name){$t=Access::table('Entressafras');$this->periods[]=(int)$t->saveOrFail($t->newEntity(['nome'=>$name,'data_inicio'=>'2026-10-19','data_fim'=>'2027-04-10','status'=>'ativa']))->id;}
        foreach(['CALDEIRA','MOENDA','DESTILARIA','ELETRICA','TURBINAS','FABRICA','UTILIDADES'] as $name){$t=Access::table('AreasFabrica');$this->areas[]=(int)$t->saveOrFail(Access::entity($t,['nome'=>$name,'entressafra_id'=>$this->periods[0]]))->id;}
        $this->session(['Auth'=>['id'=>$user->id,'stamp'=>hash('sha256',$user->senha_hash),'last'=>time()],'Periodo'=>['id'=>$this->periods[0],'usuario_id'=>$user->id]]);
        $this->enableCsrfToken();
    }
    protected function tearDown(): void {PeriodoContexto::set(null);parent::tearDown();}
    private function createBook(string $name='CALDEIRA',?int $area=null): int
    {
        $this->post('/cadernos/add',['nome'=>$name,'area_curva_id'=>$area??$this->areas[0]]);$this->assertRedirect();
        return (int)Access::table('Cadernos')->find()->where(['nome'=>$name])->firstOrFail()->id;
    }
    public function testOnlyNameAndPrincipalAreaAreRequiredAndSevenAreasArePreserved(): void
    {
        $t=Access::table('AreasFabrica');$foreign=$t->saveOrFail(Access::entity($t,['nome'=>'SETOR DE OUTRA ENTRESSAFRA','entressafra_id'=>$this->periods[1]]));
        $before=$t->find()->orderBy(['id'=>'ASC'])->disableHydration()->all()->toList();
        $this->get('/cadernos/add');$this->assertResponseOk();$this->assertResponseContains('Setor principal da Curva S');$this->assertResponseNotContains('Setores executantes');$this->assertResponseNotContains('SETOR DE OUTRA ENTRESSAFRA');
        $dom=new \DOMDocument();@$dom->loadHTML((string)$this->_response->getBody());$xp=new \DOMXPath($dom);
        $this->assertSame(2,$xp->query('//*[@required]')->length);$this->assertSame(7,$xp->query('//select[@name="area_curva_id"]/option[@value!=""]')->length);
        $id=$this->createBook();$book=Access::table('Cadernos')->get($id);
        $this->assertNull($book->setor_id);$this->assertSame($this->areas[0],(int)$book->area_curva_id);$this->assertSame($this->periods[0],(int)$book->entressafra_id);
        $this->assertSame(0,Access::table('Setores')->find()->count());$this->assertSame(0,Access::table('CadernosSetores')->find()->count());
        $this->assertSame(1,Access::table('CadernosAreas')->find()->where(['caderno_id'=>$id,'area_id'=>$this->areas[0]])->count());
        $this->get('/cadernos/view/'.$id);$this->assertResponseOk();$this->assertResponseContains('Vincular O.S.');
        $this->createBook('CALDEIRA AUXILIAR');$this->assertSame(2,Access::table('Cadernos')->find()->where(['area_curva_id'=>$this->areas[0]])->count());
        $this->assertSame($before,$t->find()->orderBy(['id'=>'ASC'])->disableHydration()->all()->toList());
        $this->post('/cadernos/add',['nome'=>'INVÁLIDO','area_curva_id'=>$foreign->id]);$this->assertResponseOk();$this->assertSame(2,Access::table('Cadernos')->find()->count());
        $this->post('/cadernos/add',['nome'=>'SEM SETOR','setor_id'=>999,'setores'=>['_ids'=>[999]]]);$this->assertResponseOk();$this->assertSame(2,Access::table('Cadernos')->find()->count());
    }
    public function testSuccessfulCreateAndEditReturnToPreviousListAndKeepValidationInput(): void
    {
        $table=Access::table('Cadernos');
        for($i=1;$i<=12;$i++)$table->saveOrFail($table->newEntity(['nome'=>'Caderno '.sprintf('%02d',$i),'area_curva_id'=>$this->areas[0],'entressafra_id'=>$this->periods[0],'ativo'=>true]));
        $this->get('/cadernos?q=Caderno&area_curva_id='.$this->areas[0].'&page=2');$this->assertResponseOk();
        $this->assertSession('Caderno','Cadernos.retorno.query.q');$this->session(['Cadernos'=>$_SESSION['Cadernos']]);
        $target=['controller'=>'Cadernos','action'=>'index','?'=>['q'=>'Caderno','area_curva_id'=>(string)$this->areas[0],'page'=>'2']];
        $this->post('/cadernos/add',['nome'=>'Caderno novo','area_curva_id'=>$this->areas[0]]);$this->assertRedirect($target);
        $book=$table->find()->where(['nome'=>'Caderno novo'])->firstOrFail();
        $this->post('/cadernos/edit/'.$book->id,['nome'=>'Caderno editado','area_curva_id'=>$this->areas[0]]);$this->assertRedirect($target);
        $this->get('/cadernos/view/'.$book->id);$this->assertResponseOk();$this->assertResponseContains('Caderno editado');
        $this->post('/cadernos/add',['nome'=>'Nome preservado','area_curva_id'=>'']);$this->assertResponseOk();$this->assertResponseContains('Nome preservado');$this->assertResponseContains('Selecione o setor principal');
        $this->assertSession($this->periods[0],'Periodo.id');
        $this->get('/cadernos?q=NenhumResultado&page=2');$this->assertRedirect(); // expired page safely falls back
    }
    public function testExecutorComesFromTotvsWithoutMappingOrPreviewWritesAndCurveUsesNotebook(): void
    {
        $id=$this->createBook('CALDEIRARIA - MOENDA',$this->areas[1]);
        $row=fn($n,$area)=>['numero'=>$n,'filial'=>'01','codigo_servico'=>'ESCAL','codigo_area'=>$area,'centro_custo'=>'','data_origem'=>'20261001','situacao'=>'L','termino'=>'N','bem'=>'BEM TESTE','servico'=>'ENTRESSAFRA','correspondencias_servico'=>1,'identidades'=>1];
        $source=new ListOrders(fn()=>[$row('000001','CALD'),$row('000002','CALD'),$row('000003','MECANI')]);$s=new ListLinks($source);
        $token=$s->create($this->admin,$id,"000001\n000002\n000003\n000001");$s->prepare($this->admin,$token);
        $this->assertSame(3,$s->summary($this->admin,$token)['counts']['elegivel']);$this->assertSame(0,Access::table('Setores')->find()->count());$this->assertSame(0,Access::table('Atividades')->find()->count());
        $s->confirmEligible($this->admin,$token);$s->process($this->admin,$token);$s->process($this->admin,$token);
        $this->assertSame(3,Access::table('Atividades')->find()->count());$this->assertSame(2,Access::table('Setores')->find()->count());$this->assertSame(2,Access::table('CadernosSetores')->find()->where(['caderno_id'=>$id])->count());
        $a=Access::table('Atividades')->find()->where(['os_numero'=>'000001'])->firstOrFail();$this->assertSame('CALD',$a->area_executante_totvs);$this->assertNull($a->area_curva_id);
        $this->assertSame(3,(new Curva())->report($this->periods[0],(string)$this->areas[1])['metrics']['total']);$this->assertSame(0,(new Curva())->report($this->periods[0],(string)$this->areas[0])['metrics']['total']);
        (new \App\Service\Evolucao())->saveBatch($this->admin,$this->periods[0],'2026-10-19',[['atividade_id'=>$a->id,'percentual'=>80,'revisao'=>0]],bin2hex(random_bytes(16)),true);
        $this->assertSame(26.6667,(new Curva())->report($this->periods[0],(string)$this->areas[1])['metrics']['simples']);
        $this->assertNull((new Curva())->report($this->periods[0],(string)$this->areas[0])['metrics']['simples']);
        $s->confirmEligible($this->admin,$token);$s->process($this->admin,$token);$this->assertSame(3,Access::table('Atividades')->find()->count());
    }
    public function testOrderPresentationUsesRealDescriptionAndPrincipalSectorWithoutVisibleBranch(): void
    {
        $book=$this->createBook('CALDEIRARIA - MOENDA',$this->areas[1]);
        $raw=['numero'=>'006655','filial'=>'01','codigo_servico'=>'ESCAL','codigo_area'=>'CALD','centro_custo'=>'CC-INTERNO','data_origem'=>'20261001','situacao'=>'L','termino'=>'N','bem'=>'BEM-INTERNO','servico'=>'ENTRESSAFRA GENERICA','correspondencias_servico'=>1,'identidades'=>1,'descricao_totvs'=>'Trocar vedação do conjunto'];
        $links=new ListLinks(new ListOrders(fn()=>[$raw]));$token=$links->create($this->admin,$book,'006655');$links->prepare($this->admin,$token);$links->confirmEligible($this->admin,$token);$links->process($this->admin,$token);
        $t=Access::table('Atividades');$activity=$t->find()->where(['os_numero'=>'006655'])->firstOrFail();$activity->descricao_atividade='Descrição específica conferida no caderno';$t->saveOrFail($activity);
        $gateway=new class($raw) implements \App\Integration\Totvs\GatewayInterface {
            public function __construct(private array $row){}
            public function orders(string $search,string $area='',?string $branch=null): array{return [\App\Integration\Totvs\Orders::mapRow($this->row)];}
            public function order(string $key): array{return \App\Integration\Totvs\Orders::mapRow($this->row);}
            public function employees(string $search): array{return [];}
            public function employee(string $key): array{return [];}
        };
        PeriodoContexto::set($this->periods[0]);
        $result=(new \App\Service\OperacaoRapida($gateway))->search(Access::actor($this->admin),'006655',false)['results'][0];
        PeriodoContexto::set(null);
        $this->assertSame('01',$result['filial']);$this->assertSame('CALD',$result['codigo_area']);
        $this->assertSame('Descrição específica conferida no caderno',$result['descricao']);$this->assertSame('MOENDA',$result['setor_apresentacao']);$this->assertSame('Setor',$result['rotulo_setor']);
        foreach(['/lancar-porcentagem?area_id='.$this->areas[1],'/apontamentos/add?atividade_id='.$activity->id,'/atividades/view/'.$activity->id,'/atividades','/cadernos/'.$book.'/vincular-lista?token='.$token] as $url){
            $this->get($url);$this->assertResponseOk();$dom=new \DOMDocument();@$dom->loadHTML((string)$this->_response->getBody());$text=$dom->textContent;
            $this->assertStringContainsString('006655',$text);$this->assertStringContainsString('Descrição específica conferida no caderno',$text);$this->assertStringContainsString('MOENDA',$text);
            $this->assertDoesNotMatchRegularExpression('/Filial|TOTVS 01|C\.C\./i',$text);
        }
        $activity->descricao_atividade=null;$t->saveOrFail($activity);
        $record=$activity->toArray();$record['caderno']=['area_curva_id'=>$this->areas[1]];$record['snapshot_totvs']=json_decode($record['snapshot_totvs'],true);
        $presentation=\App\Service\OsApresentacao::record($record);
        $this->assertSame('MOENDA',$presentation['setor_apresentacao']);$this->assertSame('Trocar vedação do conjunto',$presentation['descricao']);
        $display=\App\Service\OsApresentacao::activity($activity);$this->assertSame('Trocar vedação do conjunto',$display['descricao']);
        $unlinked=\App\Service\OsApresentacao::source($raw,'TOTVS 01 / CALD');$this->assertSame('Equipe executante (sem caderno)',$unlinked['rotulo_setor']);$this->assertSame('CALD',$unlinked['setor_apresentacao']);
        $this->assertSame('Descrição não informada',\App\Service\OsApresentacao::description(null,['servico'=>'ENTRESSAFRA GENERICA']));
        $audit=\App\Service\OsApresentacao::audit(json_encode(['os_filial'=>'01','os_numero'=>'006655','snapshot'=>json_encode(['centro_custo'=>'CC','filial'=>'01'])]));$this->assertStringNotContainsString('filial',$audit);$this->assertStringNotContainsString('centro_custo',$audit);$this->assertStringContainsString('006655',$audit);
        $this->assertSame('01',$t->get($activity->id)->os_filial);$this->assertSame('CC-INTERNO',json_decode($t->get($activity->id)->snapshot_totvs,true)['centro_custo']);
    }
}
