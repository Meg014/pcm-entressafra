<?php
declare(strict_types=1);
namespace App\Test\TestCase\Controller;

use App\Service\{Access,PeriodoContexto,ListLinks,OsSemCaderno};
use App\Integration\Totvs\{PendingOrders,ListOrders,Queries};
use Cake\TestSuite\{TestCase,IntegrationTestTrait};

class OsSemCadernoTest extends TestCase
{
    use IntegrationTestTrait;
    private int $admin;private int $season;private int $other;private int $book;private int $secondBook;
    private array $source=[];private int $reads=0;
    protected function setUp(): void
    {
        parent::setUp();PeriodoContexto::set(null);
        foreach(['PendenciasOrdens','PendenciasConsultas','EvolucaoPesos','EvolucaoMetas','EvolucaoRegistros','EvolucaoLotes','EvolucaoVersoes','CalendariosOperacionais','EvolucaoSemanas','VinculosItens','VinculosLotes','Auditorias','Apontamentos','Atividades','UsuariosSetores','Usuarios','Cadernos','AreasFabrica','Setores','Entressafras'] as $alias)Access::table($alias)->deleteAll([]);
        $this->admin=$this->user('ADMIN_PCM');$this->season=$this->period('2026/2027');$this->other=$this->period('2027/2028');
        $t=Access::table('AreasFabrica');$area=$t->saveOrFail(Access::entity($t,['nome'=>'MOENDA','entressafra_id'=>$this->season]));
        $t=Access::table('Cadernos');$this->book=(int)$t->saveOrFail(Access::entity($t,['nome'=>'CALDEIRARIA - MOENDA','entressafra_id'=>$this->season,'area_curva_id'=>$area->id,'ativo'=>true]))->id;
        $this->secondBook=(int)$t->saveOrFail(Access::entity($t,['nome'=>'OUTRO CADERNO','entressafra_id'=>$this->season,'area_curva_id'=>$area->id,'ativo'=>true]))->id;
        $this->login($this->admin);$this->enableCsrfToken();PeriodoContexto::set($this->season);
    }
    protected function tearDown(): void {PeriodoContexto::set(null);parent::tearDown();}
    private function user(string $role): int
    {
        $t=Access::table('Usuarios');return (int)$t->saveOrFail(Access::entity($t,['nome'=>$role,'login'=>uniqid('user'),'senha_hash'=>password_hash('teste123',PASSWORD_DEFAULT),'perfil'=>$role,'ativo'=>true,'created'=>gmdate('Y-m-d H:i:s'),'modified'=>gmdate('Y-m-d H:i:s')]))->id;
    }
    private function period(string $name): int
    {
        $t=Access::table('Entressafras');return (int)$t->saveOrFail(Access::entity($t,['nome'=>$name,'data_inicio'=>'2026-10-19','data_fim'=>'2027-04-10','status'=>'ativa']))->id;
    }
    private function login(int $id): void
    {
        $u=Access::actor($id);$this->session(['Auth'=>['id'=>$id,'stamp'=>hash('sha256',$u->senha_hash),'last'=>time()],'Periodo'=>['id'=>$this->season,'usuario_id'=>$id]]);
    }
    private function rows(int $count): void
    {
        $this->source=[];foreach(range(1,$count) as $i)$this->source[]=['numero'=>str_pad((string)$i,6,'0',STR_PAD_LEFT),'filial'=>'01','codigo_servico'=>$i%2?'OLD001':'NEW001','servico'=>$i%2?'ENTRESSAFRA SERVICO ANTIGO':'ENTRESSAFRA ATUAL','codigo_area'=>$i%2?'CALD':'MECAN','centro_custo'=>'CC-INTERNO','data_origem'=>'20261001','situacao'=>'L','termino'=>'N','bem'=>'BEM','correspondencias_servico'=>1,'identidades'=>1,'descricao_totvs'=>'Atividade real '.$i];
    }
    private function service(): OsSemCaderno
    {
        $scan=new PendingOrders(function($sql,$params){$this->reads++;$this->assertTrue(Queries::allows($sql));$this->assertSame('01',$params['filial']);$this->assertSame('%ENTRESSAFRA%',$params['entressafra']);return array_slice(array_values(array_filter($this->source,fn($r)=>strcmp($r['numero'],$params['cursor'])>0)),0,201);});
        $links=new ListLinks(new ListOrders(fn($sql,$p)=>array_values(array_filter($this->source,fn($r)=>in_array($r['numero'],array_values($p),true)))));
        return new OsSemCaderno($scan,$links);
    }
    private function ready(OsSemCaderno $s): string
    {
        $token=$s->create($this->admin);for($i=0;$i<50&&$s->job($this->admin,$token)->estado==='consultando';$i++)$s->scan($this->admin,$token);$this->assertSame('pronto',$s->job($this->admin,$token)->estado);return $token;
    }
    private function finish(OsSemCaderno $s,string $token): void
    {
        for($i=0;$i<100&&$s->job($this->admin,$token)->estado==='vinculando';$i++)$s->process($this->admin,$token);
        $this->assertSame('concluido',$s->job($this->admin,$token)->estado);
    }
    public function testFiltersPaginationSelectionAndRenderedHtml(): void
    {
        $this->rows(45);$s=$this->service();$token=$this->ready($s);
        $this->assertSame(45,$s->summary($this->admin,$token)['total']);
        $this->assertSame(23,$s->summary($this->admin,$token,['servico'=>'OLD001'])['total']);
        $this->assertSame(22,$s->summary($this->admin,$token,['equipe'=>'MECAN','situacao'=>'L'])['total']);
        $this->assertSame(1,$s->summary($this->admin,$token,['numero'=>'000045'])['total']);
        $s->select($this->admin,$token,'pagina',['000001','000002']);$s->select($this->admin,$token,'pagina',['000021']);
        $this->assertSame(3,$s->summary($this->admin,$token,[],3)['selected']);
        $s->select($this->admin,$token,'pagina',['000001'],[],['000001','000002']);$this->assertSame(2,$s->summary($this->admin,$token)['selected']);
        $s->select($this->admin,$token,'todas',[],['servico'=>'OLD001']);$this->assertSame(23,$s->summary($this->admin,$token)['selected']);
        foreach([1,2,3] as $page){
            $this->get('/os-sem-caderno?token='.$token.'&pagina='.$page);$this->assertResponseOk();
            $dom=new \DOMDocument();@$dom->loadHTML((string)$this->_response->getBody());$xp=new \DOMXPath($dom);
            $this->assertSame(10,$xp->query('//input[@name="numeros[]"]')->length);
            $this->assertStringNotContainsString('CC-INTERNO',$dom->textContent);$this->assertStringNotContainsString('Filial',$dom->textContent);
            $this->assertResponseContains('data-selected-count>23</span> selecionada(s)');$this->assertResponseContains('OLD001');
            $links=$xp->query('//nav[@aria-label="Paginação"]/a');foreach($links as $link)$this->assertStringContainsString('/os-sem-caderno?',$link->getAttribute('href'));
        }
        PeriodoContexto::set($this->season);$s->select($this->admin,$token,'limpar');$this->assertSame(0,$s->summary($this->admin,$token)['selected']);
        $this->assertSame(0,Access::table('Atividades')->find()->count());
    }
    public function testThousandsStayOnServerAndSelectionCoversEveryPage(): void
    {
        $this->rows(2250);$s=$this->service();$token=$this->ready($s);$this->assertSame(12,$this->reads);
        $s->select($this->admin,$token,'todas');$data=$s->summary($this->admin,$token,[],225);
        $this->assertSame(2250,$data['selected']);$this->assertSame(225,$data['pages']);$this->assertCount(10,$data['display']);
        $before=$this->reads;$s->scan($this->admin,$token);$this->assertSame($before,$this->reads);
        $s->preview($this->admin,$token,$this->book);$plan=$s->summary($this->admin,$token)['plan'];$this->assertSame(2250,$plan['quantidade']);
        $s->confirm($this->admin,$token,$plan['confirmacao']);$this->assertSame(2,Access::table('VinculosLotes')->find()->count());
        $this->assertSame(0,Access::table('Atividades')->find()->count());
    }
    public function testExplicitConfirmationLinksBatchAndPreservesExecutorAndPrincipalSector(): void
    {
        $this->rows(45);$s=$this->service();$token=$this->ready($s);$s->select($this->admin,$token,'todas');$s->preview($this->admin,$token,$this->book);
        $plan=$s->summary($this->admin,$token)['plan'];$this->assertSame(45,$plan['quantidade']);$this->assertSame(0,Access::table('Atividades')->find()->count());
        $s->confirm($this->admin,$token,$plan['confirmacao']);$s->confirm($this->admin,$token,$plan['confirmacao']);$this->assertSame(1,Access::table('VinculosLotes')->find()->count());
        $this->finish($s,$token);$s->process($this->admin,$token);$summary=$s->summary($this->admin,$token);
        $this->assertSame(45,$summary['counts']['sucesso']);$this->assertSame(0,$summary['pendentes']);$this->assertSame(45,Access::table('Atividades')->find()->count());
        $a=Access::table('Atividades')->find()->where(['os_numero'=>'000001'])->firstOrFail();$this->assertSame('CALD',$a->area_executante_totvs);$this->assertSame('MOENDA',\App\Service\OsApresentacao::activity($a)['setor_apresentacao']);
        $a->situacao_totvs='C';$a->disponivel_totvs=false;Access::table('Atividades')->saveOrFail($a);$this->source[0]['situacao']='C';
        $again=$this->ready($s);$this->assertSame(0,$s->summary($this->admin,$again)['pendentes']);$this->assertSame(45,Access::table('Atividades')->find()->count());
    }
    public function testCompetingConfirmationsNeverTransferOrDuplicate(): void
    {
        $this->rows(1);$s=$this->service();$one=$this->ready($s);$two=$this->ready($s);
        foreach([$one=>$this->book,$two=>$this->secondBook] as $token=>$book){$s->select($this->admin,$token,'todas');$s->preview($this->admin,$token,$book);$s->confirm($this->admin,$token,$s->summary($this->admin,$token)['plan']['confirmacao']);}
        $this->finish($s,$one);$this->finish($s,$two);
        $this->assertSame(1,Access::table('Atividades')->find()->count());$this->assertSame($this->book,(int)Access::table('Atividades')->find()->firstOrFail()->caderno_id);
        $this->assertSame(1,$s->summary($this->admin,$two)['counts']['outro']);
    }
    public function testLinkedAfterSummaryRequiresReviewBeforeConfirming(): void
    {
        $this->rows(1);$s=$this->service();$one=$this->ready($s);$two=$this->ready($s);
        foreach([$one,$two] as $token){$s->select($this->admin,$token,'todas');$s->preview($this->admin,$token,$this->book);}
        $s->confirm($this->admin,$one,$s->summary($this->admin,$one)['plan']['confirmacao']);$this->finish($s,$one);
        $this->expectException(\DomainException::class);$this->expectExceptionMessage('outro usuário');$s->confirm($this->admin,$two,$s->summary($this->admin,$two)['plan']['confirmacao']);
    }
    public function testSourceFailureRollsBackBlockAndCanResume(): void
    {
        $this->rows(10);$fail=true;$source=new PendingOrders(function()use(&$fail){if($fail)throw new \RuntimeException('offline');return $this->source;});$s=new OsSemCaderno($source);$token=$s->create($this->admin);
        try{$s->scan($this->admin,$token);$this->fail('Expected source failure');}catch(\DomainException){$this->assertSame(0,$s->job($this->admin,$token)->consultadas);}
        $fail=false;$s->scan($this->admin,$token);$this->assertSame(10,$s->summary($this->admin,$token)['total']);$s->scan($this->admin,$token);$this->assertSame(10,$s->summary($this->admin,$token)['total']);
    }
    public function testAdminOnlyAndPeriodOwnerAndConfirmationAreEnforced(): void
    {
        $this->rows(1);$s=$this->service();$token=$this->ready($s);
        Access::table('Entressafras')->updateAll(['status'=>'encerrada'],['id'=>$this->other]);
        foreach(['PCM','RESPONSAVEL_SETOR'] as $role){$user=$this->user($role);$this->login($user);$this->get('/os-sem-caderno?token='.$token);$this->assertResponseCode(403);$this->post('/os-sem-caderno?token='.$token,['acao'=>'consultar']);$this->assertResponseCode(403);}
        $this->login($this->admin);$this->get('/os-sem-caderno?entressafra_id='.$this->other);$this->assertResponseCode(403);
        PeriodoContexto::set($this->other);try{$s->job($this->admin,$token);$this->fail('Cross-period token accepted');}catch(\DomainException){$this->assertTrue(true);}
        PeriodoContexto::set($this->season);try{$s->job($this->admin,'invalid');$this->fail('Invalid token accepted');}catch(\DomainException){$this->assertTrue(true);}
        $s->select($this->admin,$token,'todas');$s->preview($this->admin,$token,$this->book);
        $this->expectException(\DomainException::class);$s->confirm($this->admin,$token,'wrong-confirmation');
    }
    public function testControllerPreviewAndConfirmationPreserveRouteFiltersAndSelection(): void
    {
        $this->rows(10);$s=$this->service();$token=$this->ready($s);$url='/os-sem-caderno?token='.$token.'&servico=OLD001&pagina=1';
        $this->post($url,['acao'=>'todas']);$this->assertRedirectContains('/os-sem-caderno?');$this->assertRedirectContains('servico=OLD001');
        PeriodoContexto::set($this->season);$this->assertSame(5,$s->summary($this->admin,$token)['selected']);
        $this->post($url,['acao'=>'resumo','caderno_id'=>$this->book]);$this->assertRedirectContains('token='.$token);
        $this->get($url);$this->assertResponseOk();$this->assertResponseContains('Confirmar vínculos');$this->assertResponseContains('5 O.S. para o caderno');
        $dom=new \DOMDocument();@$dom->loadHTML((string)$this->_response->getBody());$xp=new \DOMXPath($dom);
        foreach($xp->query('//section//form[@method="post"]') as $form){$this->assertStringContainsString('token='.$token,$form->getAttribute('action'));$this->assertStringContainsString('servico=OLD001',$form->getAttribute('action'));}
        PeriodoContexto::set($this->season);$plan=$s->summary($this->admin,$token)['plan'];
        $this->post($url,['acao'=>'confirmar','confirmacao'=>$plan['confirmacao']]);$this->assertRedirectContains('/os-sem-caderno?');
        $this->assertSame(0,Access::table('Atividades')->find()->count());
        PeriodoContexto::set($this->season);$this->finish($s,$token);$this->get($url);$this->assertResponseOk();$this->assertResponseContains('5 vinculada(s)');$this->assertResponseContains('0 resultado(s) neste filtro');
    }
    public function testRealDescriptionPriorityLocalUnlinkedAndForeignPeriodDoNotHidePendingOrders(): void
    {
        $this->rows(3);$s=$this->service();$token=$this->ready($s);$s->select($this->admin,$token,'pagina',['000001','000002']);$s->preview($this->admin,$token,$this->book);$s->confirm($this->admin,$token,$s->summary($this->admin,$token)['plan']['confirmacao']);$this->finish($s,$token);
        $t=Access::table('Atividades');$a=$t->find()->where(['os_numero'=>'000001'])->firstOrFail();$a->caderno_id=null;$a->descricao_atividade='Descrição específica revisada';$t->saveOrFail($a);
        PeriodoContexto::set($this->other);$areas=Access::table('AreasFabrica');$area=$areas->saveOrFail(Access::entity($areas,['nome'=>'SETOR OUTRO PERIODO','entressafra_id'=>$this->other]));$books=Access::table('Cadernos');$foreign=$books->saveOrFail(Access::entity($books,['nome'=>'CADERNO OUTRO PERIODO','entressafra_id'=>$this->other,'area_curva_id'=>$area->id,'ativo'=>true]));
        $associations=Access::table('CadernosSetores');$associations->saveOrFail(Access::entity($associations,['caderno_id'=>$foreign->id,'setor_id'=>$a->setor_executante_id]));
        $copy=$a->toArray();unset($copy['id']);$copy['entressafra_id']=$this->other;$copy['caderno_id']=$foreign->id;$copy['os_numero']='000003';$copy['os_chave']=\App\Integration\Totvs\Orders::key('01','000003');$t->saveOrFail(Access::entity($t,$copy));
        PeriodoContexto::set($this->season);$data=$s->summary($this->admin,$token);$this->assertSame(2,$data['total']);$this->assertSame('000001',$data['display'][0]['row']->numero);$this->assertSame('Descrição específica revisada',$data['display'][0]['descricao']);$this->assertSame('000003',$data['display'][1]['row']->numero);
        $fresh=$this->ready($s);$s->select($this->admin,$fresh,'todas');$this->expectException(\Cake\Http\Exception\ForbiddenException::class);$s->preview($this->admin,$fresh,(int)$foreign->id);
    }
    public function testMaintenanceWrongBranchAndIneligibleSituationNeverEnterPendingPopulation(): void
    {
        $this->rows(4);$this->source[1]['servico']='MANUTENCAO CORRETIVA';$this->source[2]['filial']='02';$this->source[3]['situacao']='C';$s=$this->service();$token=$this->ready($s);
        $data=$s->summary($this->admin,$token);$this->assertSame(1,$data['total']);$this->assertSame('OLD001',$data['display'][0]['row']->servico_codigo);
        $otherAdmin=$this->user('ADMIN_PCM');$this->expectException(\DomainException::class);$s->job($otherAdmin,$token);
    }
    public function testAjaxSelectionReturnsSavedCountAndDeselectsOnlyVisiblePage(): void
    {
        $this->rows(45);$s=$this->service();$token=$this->ready($s);$s->select($this->admin,$token,'pagina',['000021']);
        $this->configRequest(['headers'=>['X-Requested-With'=>'XMLHttpRequest']]);
        $this->post('/os-sem-caderno?token='.$token,['acao'=>'pagina','numeros'=>['000001','000002'],'exibidas'=>['000001','000002','000003']]);
        $this->assertResponseOk();$result=json_decode((string)$this->_response->getBody(),true);$this->assertTrue($result['ok']);$this->assertSame(3,$result['selecionadas']);
        $this->configRequest(['headers'=>['X-Requested-With'=>'XMLHttpRequest']]);
        $this->post('/os-sem-caderno?token='.$token,['acao'=>'pagina','numeros'=>[],'exibidas'=>['000001','000002','000003']]);
        $this->assertResponseOk();$result=json_decode((string)$this->_response->getBody(),true);$this->assertSame(1,$result['selecionadas']);
        PeriodoContexto::set($this->season);$this->assertTrue(Access::table('PendenciasOrdens')->find()->where(['consulta_id'=>$s->job($this->admin,$token)->id,'numero'=>'000021'])->firstOrFail()->selecionada);
    }
}
