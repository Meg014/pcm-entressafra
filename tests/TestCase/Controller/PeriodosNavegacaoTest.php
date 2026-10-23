<?php
declare(strict_types=1);
namespace App\Test\TestCase\Controller;

use App\Service\{Access,Users,PeriodoContexto,SetoresApresentacao,ClassificacaoCurva,ListLinks,Curva,Evolucao};
use Cake\TestSuite\{TestCase,IntegrationTestTrait};

class PeriodosNavegacaoTest extends TestCase
{
    use IntegrationTestTrait;
    private int $admin;private int $computer;private array $seasons=[];private array $books=[];private array $areas=[];private array $activities=[];
    protected function setUp(): void
    {
        parent::setUp();PeriodoContexto::set(null);
        foreach(['EvolucaoPesos','EvolucaoMetas','EvolucaoRegistros','EvolucaoLotes','EvolucaoVersoes','CalendariosOperacionais','EvolucaoSemanas','VinculosItens','VinculosLotes','Auditorias','Apontamentos','Atividades','UsuariosSetores','Usuarios','Cadernos','AreasFabrica','Setores','Entressafras'] as $alias)Access::table($alias)->deleteAll([]);
        $stamp=gmdate('Y-m-d H:i:s');$t=Access::table('Usuarios');$this->admin=(int)$t->saveOrFail(Access::entity($t,['nome'=>'Admin períodos','login'=>'periodo-admin','senha_hash'=>password_hash('teste123',PASSWORD_DEFAULT),'perfil'=>'ADMIN_PCM','ativo'=>true,'created'=>$stamp,'modified'=>$stamp]))->id;
        $t=Access::table('Setores');$sector=(int)$t->saveOrFail($t->newEntity(['nome'=>'Mecânica','area_totvs'=>'MECANI','filial_totvs'=>'01','ativo'=>true]))->id;
        foreach([['2026/2027','2026-10-19','2027-04-10','ativa'],['2025/2026','2025-10-19','2026-04-10','encerrada']] as $i=>[$name,$start,$end,$status]){
            $t=Access::table('Entressafras');$season=$t->saveOrFail($t->newEntity(['nome'=>$name,'data_inicio'=>$start,'data_fim'=>$end,'status'=>$status]));$this->seasons[]=(int)$season->id;
            $t=Access::table('Cadernos');$book=$t->saveOrFail($t->newEntity(['nome'=>'Caderno período '.$i,'entressafra_id'=>$season->id,'setor_id'=>$sector,'ativo'=>true]));$this->books[]=(int)$book->id;
            $t=Access::table('Atividades');$this->activities[]=(int)$t->saveOrFail(Access::entity($t,['entressafra_id'=>$season->id,'caderno_id'=>$book->id,'setor_executante_id'=>$sector,'area_executante_totvs'=>'MECANI','identificacao_setor'=>'area_totvs','os_numero'=>$i?'009999':'005300','os_filial'=>'01','os_chave'=>'periodo-os-'.$i,'servico'=>'ENTRESSAFRA '.$name,'bem'=>'Bem período '.$i,'situacao_totvs'=>'L','disponivel_totvs'=>true,'snapshot_totvs'=>'{}','criado_por'=>$this->admin,'created'=>$stamp,'modified'=>$stamp]))->id;
        }
        foreach(['Moenda','Destilaria'] as $name){$t=Access::table('AreasFabrica');$this->areas[]=(int)$t->saveOrFail(Access::entity($t,['nome'=>$name]))->id;}
        $this->computer=(new Users())->save($this->admin,['nome'=>'Computador Mecânica','login'=>'periodo-computer','senha'=>'teste123','perfil'=>'RESPONSAVEL_SETOR','computador_operacional'=>'1','ativo'=>'1','setores'=>[$sector]]);
        $this->auth($this->admin);
    }
    protected function tearDown(): void {PeriodoContexto::set(null);parent::tearDown();}
    private function auth(int $id,?int $season=null): void
    {$u=Access::actor($id);$this->session(['Auth'=>['id'=>$id,'stamp'=>hash('sha256',$u->senha_hash),'last'=>time()],'Periodo'=>$season===null?[]:['id'=>$season,'usuario_id'=>$id]]);}
    private function select(int $index=0): void
    {$this->enableCsrfToken();$this->post('/escolher-entressafra/entrar',['periodo_id'=>$this->seasons[$index]]);$this->assertRedirect('/controle-pcm');$this->assertSession($this->seasons[$index],'Periodo.id');$this->session(['Periodo'=>$_SESSION['Periodo']]);}
    public function testAdministrativeLoginStartsAtSelectionAndClearsPreviousPeriod(): void
    {
        $this->enableCsrfToken();$this->post('/login',['login'=>'periodo-admin','senha'=>'teste123']);$this->assertRedirect('/escolher-entressafra');$this->assertSession(null,'Periodo.id');
        $this->get('/controle-pcm');$this->assertRedirect('/escolher-entressafra');$this->get('/escolher-entressafra');$this->assertResponseOk();$this->assertResponseContains('2026/2027');$this->assertResponseContains('2025/2026');
    }
    public function testNotebookListAndDetailExposePresetListTool(): void
    {
        $this->select();$path='/cadernos/'.$this->books[0].'/vincular-lista';
        foreach(['/cadernos','/cadernos/view/'.$this->books[0]] as $url){$this->get($url);$this->assertResponseOk();$this->assertResponseContains($path);$this->assertResponseContains('Vincular O.S.');}
        $this->get($path);$this->assertResponseOk();$this->assertResponseContains('Caderno período 0');$this->assertResponseContains('Conferir');
        $dom=new \DOMDocument();@$dom->loadHTML((string)$this->_response->getBody());$xp=new \DOMXPath($dom);
        $this->assertSame(0,$xp->query('//select')->length);$this->assertSame(1,$xp->query('//textarea[@name="numeros"]')->length);
        $this->assertResponseContains('Pendente de confirmação administrativa');
    }
    public function testSpecificDescriptionIsAuditedAndNeverUsesGenericNotebookText(): void
    {
        $this->select();$id=$this->activities[0];$t=Access::table('Cadernos');$b=$t->get($this->books[0]);$b->descricao='TEXTO GERAL DO CADERNO';$t->saveOrFail($b);
        $this->get('/controle-pcm');$this->assertResponseOk();$this->assertResponseContains('Descrição não informada');$this->assertResponseNotContains('TEXTO GERAL DO CADERNO');
        $this->assertResponseNotContains('<th>Equipamento</th>');$this->assertResponseContains('/atividades/view/'.$id);
        $this->post('/atividades/descricao/'.$id,['descricao_atividade'=>'Revisão específica conferida no caderno','descricao_origem'=>'Caderno de teste, linha 1']);$this->assertRedirect('/atividades/view/'.$id);
        $this->assertSame(1,Access::table('Auditorias')->find()->where(['acao'=>'descricao_atividade_atualizada'])->count());
        $this->assertSame('Revisão específica conferida no caderno',Access::table('Atividades')->get($id)->descricao_atividade);$this->get('/controle-pcm?q=conferida');$this->assertResponseOk();$this->assertResponseContains('Revisão específica conferida no caderno');
        $this->get('/atividades/view/'.$id);$this->assertResponseOk();$this->assertResponseContains('Revisão específica conferida no caderno');$this->assertResponseContains('Serviço TOTVS:');$this->assertResponseContains('Equipamento:');$this->assertResponseContains('Histórico de porcentagens');$this->assertResponseContains('Setor:');
        $this->post('/atividades/descricao/'.$id,['descricao_atividade'=>'','descricao_origem'=>'']);$this->assertRedirect();$this->assertSame('Revisão específica conferida no caderno',Access::table('Atividades')->get($id)->descricao_atividade);
    }
    public function testChoicePersistsOnNavigationReloadAndSwitch(): void
    {
        $this->select();$this->assertSession($this->seasons[0],'Periodo.id');
        foreach(['/controle-pcm','/cadernos','/setores','/atividades','/controle-pcm'] as $path){$this->get($path);$this->assertResponseOk();$this->assertSession($this->seasons[0],'Periodo.id');$this->assertResponseContains('Entressafra 2026/2027');$this->assertResponseContains('Trocar entressafra');}
        $this->select(1);$this->get('/atividades');$this->assertResponseOk();$this->assertResponseContains('009999');$this->assertResponseNotContains('005300');$this->assertSession($this->seasons[1],'Periodo.id');
    }
    public function testForeignQueryBodyAndPathCannotOverrideSession(): void
    {
        $this->select();$this->get('/controle-pcm?entressafra_id='.$this->seasons[1]);$this->assertResponseCode(403);
        foreach(['/cadernos/view/'.$this->books[1],'/atividades/view/'.$this->activities[1],'/evolucao/historico/'.$this->activities[1],'/cadernos/'.$this->books[1].'/vincular-lista'] as $path){$this->get($path);$this->assertContains($this->_response->getStatusCode(),[403,404]);}
        $this->post('/lancar-porcentagem',['entressafra_id'=>$this->seasons[1],'atividade_id'=>$this->activities[1],'percentual'=>80]);$this->assertResponseCode(403);
        $this->post('/cadernos/add',['entressafra_id'=>$this->seasons[1],'nome'=>'Tentativa cruzada']);$this->assertResponseCode(403);
        $this->assertSame(0,Access::table('EvolucaoRegistros')->find()->count());$this->assertSession($this->seasons[0],'Periodo.id');
    }
    public function testClosedPeriodAllowsHistoryAndBlocksAllOperationalWrites(): void
    {
        $this->select(1);foreach(['/controle-pcm','/apontamentos','/evolucao/historico/'.$this->activities[1],'/setores'] as $path){$this->get($path);$this->assertResponseOk();$this->assertResponseContains('consulta histórica');}
        foreach(['/lancar-porcentagem','/apontamentos/add','/cadernos/'.$this->books[1].'/vincular-lista','/evolucao/configuracao','/setores/curva/'.$this->areas[0]] as $path){$this->post($path,['percentual'=>30,'numeros'=>'005300']);$this->assertResponseCode(403);}
        $this->assertSame(0,Access::table('Apontamentos')->find()->count());$this->assertSame(0,Access::table('EvolucaoRegistros')->find()->count());
    }
    public function testComputerIgnoresStoredChoiceAndAlwaysUsesActiveWithoutSwitch(): void
    {
        $this->auth($this->computer,$this->seasons[1]);$this->get('/apontamentos');$this->assertResponseOk();$this->assertResponseContains('Entressafra 2026/2027');$this->assertResponseNotContains('Trocar entressafra');
        $this->get('/escolher-entressafra');$this->assertResponseCode(403);$this->enableCsrfToken();$this->post('/escolher-entressafra/entrar',['periodo_id'=>$this->seasons[1]]);$this->assertResponseCode(403);
        $this->get('/apontamentos?entressafra_id='.$this->seasons[1]);$this->assertResponseCode(403);
    }
    public function testOrmAndRawReportsEnforceSameSelectedContext(): void
    {
        PeriodoContexto::set($this->seasons[0]);$this->assertSame(1,Access::table('Atividades')->find()->count());$this->assertSame(1,Access::table('Cadernos')->find()->count());
        try{(new Curva())->report($this->seasons[1]);$this->fail();}catch(\Cake\Http\Exception\ForbiddenException){}
        try{(new Evolucao())->activities($this->seasons[1]);$this->fail();}catch(\Cake\Http\Exception\ForbiddenException){}
        try{Access::table('Cadernos')->get($this->books[1]);$this->fail();}catch(\Cake\Datasource\Exception\RecordNotFoundException){}
        PeriodoContexto::set(null);
    }
    public function testSectorDatesDoNotInventStartAndKeepManyToManyAssociations(): void
    {
        $this->select();$this->get('/setores/curva/'.$this->areas[0]);$this->assertResponseOk();$this->assertResponseContains('2027-04-10');
        $service=new SetoresApresentacao();$this->assertSame('',$service->configuration($this->seasons[0],$this->areas[0])['data_inicio']);$this->assertSame('2027-03-26',$service->configuration($this->seasons[0],$this->areas[1])['data_fim']);
        $t=Access::table('Cadernos');$extra=$t->saveOrFail($t->newEntity(['nome'=>'Segundo caderno da Moenda','entressafra_id'=>$this->seasons[0],'setor_id'=>$t->get($this->books[0])->setor_id,'ativo'=>true]));
        foreach($this->areas as $i=>$area)$this->post('/setores/curva/'.$area,['data_inicio'=>'','data_fim'=>$i?'2027-03-26':'2027-04-10','cadernos'=>$i?[$this->books[0]]:[$this->books[0],$extra->id]]);
        $this->assertRedirect();$this->assertSame(2,Access::table('CadernosAreas')->find()->where(['caderno_id'=>$this->books[0]])->count());$this->assertNull(Access::table('Cadernos')->get($this->books[0])->area_curva_id);
        $this->assertNull(Access::table('Atividades')->get($this->activities[0])->area_fabrica_id);$this->assertNull(Access::table('Atividades')->get($this->activities[0])->area_curva_id);
        $this->assertSame('',$service->configuration($this->seasons[0],$this->areas[0])['data_inicio']);
        $this->assertCount(2,$service->configuration($this->seasons[0],$this->areas[0])['cadernos']);
    }
    public function testSectorRejectsForeignBooksAndPreservesPrincipalClassification(): void
    {
        $this->select();$this->post('/setores/curva/'.$this->areas[0],['data_inicio'=>'','data_fim'=>'2027-04-10','cadernos'=>[$this->books[1]]]);$this->assertResponseOk();$this->assertResponseContains('Caderno de outra entressafra');
        $s=new ClassificacaoCurva();$s->apply($this->admin,$s->preview($this->admin,$this->seasons[0],'caderno',[$this->books[0]],$this->areas[0],'Conferência principal.'));
        $this->post('/setores/curva/'.$this->areas[0],['data_inicio'=>'','data_fim'=>'2027-04-10','cadernos'=>[]]);$this->assertResponseOk();$this->assertResponseContains('classificação principal');
        $this->assertSame($this->areas[0],(int)Access::table('Cadernos')->get($this->books[0])->area_curva_id);
        $this->assertSame(1,(new Curva())->report($this->seasons[0],(string)$this->areas[0])['metrics']['total']);
    }
    public function testMenuHasOnlyPrimaryAdministrativeLinksAndOldRoutesStillWork(): void
    {
        $this->select();$this->get('/cadernos');$this->assertResponseOk();$doc=new \DOMDocument();@$doc->loadHTML((string)$this->_response->getBody());$xp=new \DOMXPath($doc);$paths=[];foreach($xp->query('//nav[@aria-label="Cadastros administrativos"]//a') as $link)$paths[]=$link->getAttribute('href');
        $this->assertSame(['/entressafras','/setores','/cadernos','/os-sem-caderno','/usuarios','/atividades'],$paths);
        foreach(['/vinculos-lote','/lancar-porcentagem','/prazos-entressafra','/organizacao-curva','/setores?equipes=1'] as $path){$this->get($path);$this->assertResponseOk();}
        $this->get('/cadernos/view/'.$this->books[0]);$this->assertResponseContains('Vincular O.S.');$this->get('/cadernos/'.$this->books[0].'/vincular-lista');$this->assertResponseContains('Conferir');$this->assertResponseNotContains('id="bulk-services"');
    }
    public function testPeriodSelectionRequiresCsrfAndDoesNotChangeBusinessData(): void
    {
        $before=Access::table('Auditorias')->find()->count();$this->post('/escolher-entressafra/entrar',['periodo_id'=>$this->seasons[0]]);$this->assertResponseCode(403);$this->select();$this->select(1);$this->assertSame($before,Access::table('Auditorias')->find()->count());$this->assertSame(2,Access::table('Atividades')->find()->count());
    }
}
