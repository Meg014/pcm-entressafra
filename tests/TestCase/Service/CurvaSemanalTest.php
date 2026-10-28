<?php
declare(strict_types=1);
namespace App\Test\TestCase\Service;

use App\Service\{Access,CurvaSemanal,PeriodoContexto};
use Cake\TestSuite\{TestCase,IntegrationTestTrait};

class CurvaSemanalTest extends TestCase
{
    use IntegrationTestTrait;
    private int $admin;private int $pcm;private int $season;private int $area;private int $other;private int $book;private array $orders=[];private int $week;
    protected function setUp(): void
    {
        parent::setUp();PeriodoContexto::set(null);
        foreach(['Auditorias','Insumos','EvolucaoPesos','EvolucaoMetas','EvolucaoRegistros','EvolucaoLotes','EvolucaoVersoes','CalendariosOperacionais','EvolucaoSemanas','VinculosItens','VinculosLotes','Apontamentos','Atividades','UsuariosSetores','Usuarios','Cadernos','Setores','AreasFabrica','Entressafras'] as $alias)Access::table($alias)->deleteAll([]);
        $users=Access::table('Usuarios');$stamp='2026-10-14 12:00:00';
        foreach(['admin'=>'ADMIN_PCM','pcm'=>'PCM'] as $property=>$profile)$this->$property=(int)$users->saveOrFail(Access::entity($users,['nome'=>$profile,'login'=>'semana-'.$property,'senha_hash'=>password_hash('teste123',PASSWORD_DEFAULT),'perfil'=>$profile,'ativo'=>true,'created'=>$stamp,'modified'=>$stamp]))->id;
        $seasons=Access::table('Entressafras');$this->season=(int)$seasons->saveOrFail($seasons->newEntity(['nome'=>'Semanas teste','data_inicio'=>'2026-10-14','data_fim'=>'2026-11-02','status'=>'ativa']))->id;
        $areas=Access::table('AreasFabrica');$this->area=(int)$areas->saveOrFail(Access::entity($areas,['nome'=>'MOENDA','entressafra_id'=>$this->season]))->id;
        $this->other=(int)$areas->saveOrFail(Access::entity($areas,['nome'=>'CALDEIRARIA','entressafra_id'=>$this->season]))->id;
        $sectors=Access::table('Setores');$sector=$sectors->saveOrFail($sectors->newEntity(['nome'=>'Elétrica','ativo'=>true,'area_totvs'=>'ELETRI','filial_totvs'=>'01']));
        $books=Access::table('Cadernos');$this->book=(int)$books->saveOrFail($books->newEntity(['nome'=>'Caderno principal','entressafra_id'=>$this->season,'setor_id'=>$sector->id,'area_curva_id'=>$this->area,'ativo'=>true]))->id;
        $activities=Access::table('Atividades');
        foreach([100,80,50,20] as $i=>$percentage) {
            $number=sprintf('%06d',$i+1);
            $this->orders[]=(int)$activities->saveOrFail(Access::entity($activities,['entressafra_id'=>$this->season,'caderno_id'=>$this->book,'setor_executante_id'=>$sector->id,'area_executante_totvs'=>'ELETRI','identificacao_setor'=>'area_totvs','os_chave'=>\App\Integration\Totvs\Orders::key('01',$number),'os_numero'=>$number,'os_filial'=>'01','servico'=>'ENTRESSAFRA','bem'=>'BEM','situacao_totvs'=>'L','disponivel_totvs'=>true,'snapshot_totvs'=>'{}','criado_por'=>$this->admin,'created'=>$stamp,'modified'=>$stamp]))->id;
        }
        $db=$activities->getConnection();$db->insert('evolucao_semanas',['entressafra_id'=>$this->season,'referencia'=>'2026-10-19']);$this->week=(int)$db->execute('SELECT id FROM evolucao_semanas WHERE entressafra_id=?',[$this->season])->fetch('assoc')['id'];
        foreach([100,80,50,20] as $i=>$value)$this->record($this->orders[$i],$value,1,'2026-10-19 12:00:00');
        PeriodoContexto::set($this->season);$this->auth($this->admin);$this->enableCsrfToken();
    }
    protected function tearDown(): void { PeriodoContexto::set(null);parent::tearDown(); }
    private function auth(int $id): void
    {
        $actor=Access::actor($id);$this->session(['Periodo'=>['id'=>$this->season,'usuario_id'=>$id],'Auth'=>['id'=>$id,'stamp'=>hash('sha256',$actor->senha_hash),'last'=>time()]]);
    }
    private function record(int $order,float $value,int $revision,string $created): void
    {
        Access::table('Atividades')->getConnection()->insert('evolucao_registros',['atividade_id'=>$order,'semana_id'=>$this->week,'percentual'=>$value,'revisao'=>$revision,'usuario_id'=>$this->admin,'observacoes'=>'Teste isolado','motivo'=>'Teste','created'=>$created]);
    }
    private function content(int $id): array { return json_decode(Access::table('EvolucaoVersoes')->get($id)->conteudo,true,512,JSON_THROW_ON_ERROR); }
    private function autoCalendar(int $area, string $start='2026-10-14',string $end='2026-11-02'): void
    {
        $table=Access::table('CalendariosOperacionais');$table->saveOrFail(Access::entity($table,['entressafra_id'=>$this->season,'chave'=>'area:'.$area,'area_fabrica_id'=>$area,'data_inicio'=>$start,'data_fim'=>$end,'inicio_confirmado'=>true,'dias_semana'=>'[1,2,3,4,5,6,7]']));
    }
    public function testAutomaticSectorDurationsDoNotDependOnManualGoalsOrRealized(): void
    {
        $this->autoCalendar($this->area);$this->autoCalendar($this->other,'2026-10-20','2026-10-26');$service=new CurvaSemanal();
        $now=new \DateTimeImmutable('2026-10-27T12:00:00-03:00');
        $this->assertEqualsWithDelta(69.5,$service->report($this->season,$this->area,$now)['points'][1]['programado'],1e-12);
        $this->assertSame(100.,$service->report($this->season,$this->other,$now)['points'][1]['programado']);
        $service->savePlan($this->admin,$this->season,$this->area,['2026-10-27'=>1],'Fixture de meta legada',0);
        Access::table('EvolucaoRegistros')->deleteAll([]);
        $this->assertEqualsWithDelta(69.5,$service->report($this->season,$this->area,$now)['points'][1]['programado'],1e-12);
        $snapshot=$this->content($service->close($this->admin,$this->season,$this->area,'2026-10-27'));
        $this->assertSame(0,$snapshot['realizado']);$this->assertEqualsWithDelta(69.5,$snapshot['programado'],1e-12);$this->assertNull($snapshot['plano_id']);
        $this->assertSame(\App\Service\ProgramacaoAutomatica::VERSION,$snapshot['programacao_automatica']['modelo']);
    }
    public function testZeroFiftyHundredEqualImportanceAndCalendarChangeDoesNotRewriteSnapshots(): void
    {
        $this->autoCalendar($this->area);Access::table('Atividades')->updateAll(['area_curva_id'=>$this->other],['id'=>$this->orders[3]]);
        foreach([0,50,100] as $i=>$percentage)$this->record($this->orders[$i],$percentage,2,'2026-10-19 13:00:00');
        $service=new CurvaSemanal();$id=$service->close($this->admin,$this->season,$this->area,'2026-10-27');
        $before=Access::table('EvolucaoVersoes')->get($id)->conteudo;$snapshot=$this->content($id);
        $this->assertSame(50,$snapshot['realizado']);$this->assertSame(3,$snapshot['quantidade']);
        Access::table('CalendariosOperacionais')->updateAll(['data_fim'=>'2026-10-26'],['area_fabrica_id'=>$this->area]);
        $report=$service->report($this->season,$this->area,new \DateTimeImmutable('2026-10-27T12:00:00-03:00'));
        $this->assertEqualsWithDelta(69.5,$report['latest']['programado'],1e-12);
        $this->assertSame(100.,$report['points'][1]['programado']);$this->assertEqualsWithDelta(-19.5,$report['latest']['diferenca'],1e-12);
        $this->assertSame($before,Access::table('EvolucaoVersoes')->get($id)->conteudo);
    }
    public function testLegacyClosingRemainsByteForByteUnchangedAndIsIdentified(): void
    {
        $this->autoCalendar($this->area);$table=Access::table('EvolucaoVersoes');
        $content=['semana'=>2,'inicio'=>'2026-10-20','fim'=>'2026-10-26','referencia'=>'2026-10-27','programado'=>35,'realizado'=>62.5,'quantidade'=>4,'ordens'=>[],'apurado_em'=>'2026-10-27 03:00:00'];
        $row=$table->saveOrFail(Access::entity($table,['entressafra_id'=>$this->season,'area_curva_id'=>$this->area,'tipo'=>'curva_sem','nome'=>'Fechamento legado','conteudo'=>json_encode($content,JSON_THROW_ON_ERROR),'usuario_id'=>$this->admin,'created'=>'2026-10-27 03:00:00','aprovado_por'=>$this->admin,'aprovado_em'=>'2026-10-27 03:00:00','referencia_semanal'=>'2026-10-27','revisao_semanal'=>1]));
        $before=$row->conteudo;$report=(new CurvaSemanal())->report($this->season,$this->area,new \DateTimeImmutable('2026-10-27T12:00:00-03:00'));
        $this->assertSame(35,$report['latest']['programado']);$this->assertSame(27.5,$report['latest']['diferenca']);
        $this->assertSame('legado',$report['points'][1]['origem_programado_apurado']);
        $this->assertEqualsWithDelta(69.5,$report['points'][1]['programado'],1e-12);$this->assertSame($before,$table->get($row->id)->conteudo);
    }
    public function testWeeklyEvolutionAndFutureRealizationAreSeparateFromProjection(): void
    {
        $this->autoCalendar($this->area);$service=new CurvaSemanal();
        $service->close($this->admin,$this->season,$this->area,'2026-10-20');
        foreach($this->orders as $order)$this->record($order,100,2,'2026-10-26 12:00:00');
        $service->close($this->admin,$this->season,$this->area,'2026-10-27');
        $service->close($this->admin,$this->season,$this->area,'2026-11-03',now:new \DateTimeImmutable('2026-11-03T12:00:00-03:00'));
        $report=$service->report($this->season,$this->area,new \DateTimeImmutable('2026-10-27T12:00:00-03:00'));
        $this->assertSame(37.5,$report['latest']['evolucao_semanal']);$this->assertNull($report['points'][2]['realizado']);
        $this->assertNull($report['points'][2]['tendencia']);$this->assertSame(100.,$report['points'][2]['programado']);
    }
    public function testTuesdayWeeksPartialStartAndFinalWeek(): void
    {
        $weeks=CurvaSemanal::weeks('2026-10-14','2026-11-02');
        $this->assertSame(['semana'=>1,'inicio'=>'2026-10-14','fim'=>'2026-10-19','referencia'=>'2026-10-20'],$weeks[0]);
        $this->assertSame(['semana'=>2,'inicio'=>'2026-10-20','fim'=>'2026-10-26','referencia'=>'2026-10-27'],$weeks[1]);
        $this->assertSame('2026-11-03',$weeks[2]['referencia']);
        $this->assertSame('2026-10-27',CurvaSemanal::weeks('2026-10-20','2026-10-21')[0]['referencia']);
        $this->assertSame('2026-10-21',CurvaSemanal::weeks('2026-10-20','2026-10-21')[0]['fim']);
    }
    public function testSimpleAverageNoWeightsNoGoalsNoHistoryMutationAndIdempotence(): void
    {
        $service=new CurvaSemanal();$before=Access::table('Atividades')->getConnection()->execute('SELECT * FROM evolucao_registros ORDER BY id')->fetchAll('assoc');
        $id=$service->close($this->admin,$this->season,$this->area,'2026-10-27');$snapshot=$this->content($id);
        $this->assertSame(62.5,$snapshot['realizado']);$this->assertSame(4,$snapshot['quantidade']);$this->assertNull($snapshot['programado']);
        $this->assertSame('2026-10-27 02:59:59',$snapshot['corte_utc']);
        $this->assertSame($id,$service->close($this->admin,$this->season,$this->area,'2026-10-27'));
        $this->assertSame(1,Access::table('EvolucaoVersoes')->find()->where(['tipo'=>'curva_sem'])->count());
        $this->assertSame($before,Access::table('Atividades')->getConnection()->execute('SELECT * FROM evolucao_registros ORDER BY id')->fetchAll('assoc'));
        $report=$service->report($this->season,$this->area,new \DateTimeImmutable('2026-10-27T12:00:00-03:00'));
        $this->assertNull($report['latest']['diferenca']);$this->assertNull($report['latest']['tendencia']);
    }
    public function testZeroMissingUniqueRosterAndMainSector(): void
    {
        Access::table('EvolucaoRegistros')->deleteAll(['atividade_id'=>$this->orders[0]]);
        // Secondary associations do not multiply the primary sector's roster.
        $links=Access::table('CadernosAreas');$links->saveOrFail(Access::entity($links,['caderno_id'=>$this->book,'area_id'=>$this->other,'entressafra_id'=>$this->season]));
        Access::table('Atividades')->updateAll(['area_curva_id'=>$this->other],['id'=>$this->orders[3]]);
        $service=new CurvaSemanal();$snapshot=$this->content($service->close($this->admin,$this->season,$this->area,'2026-10-27'));
        $this->assertSame(3,$snapshot['quantidade']);$this->assertEqualsWithDelta(130/3,$snapshot['realizado'],1e-12);
        $this->assertSame(0,$snapshot['ordens'][0]['percentual']);$this->assertNull($snapshot['ordens'][0]['registro_id']);
        $other=$this->content($service->close($this->admin,$this->season,$this->other,'2026-10-27'));$this->assertSame(1,$other['quantidade']);$this->assertSame(20,$other['realizado']);
    }
    public function testMondayCutoffAndLaterBackdatedRecordsCannotChangeConsolidation(): void
    {
        $this->record($this->orders[0],90,2,'2026-10-27 02:59:59');$this->record($this->orders[0],10,3,'2026-10-27 03:00:00');
        $service=new CurvaSemanal();$id=$service->close($this->admin,$this->season,$this->area,'2026-10-27');
        $this->assertSame(60,$this->content($id)['realizado']);
        $this->record($this->orders[1],0,2,'2026-10-27 12:00:00');
        $this->assertSame($id,$service->close($this->admin,$this->season,$this->area,'2026-10-27'));
        $this->assertSame(60,$this->content($id)['realizado']);
        Access::table('Atividades')->updateAll(['area_curva_id'=>$this->other],['id'=>$this->orders[0]]);
        $new=$service->close($this->admin,$this->season,$this->area,'2026-10-27','Correção retroativa autorizada',$id,new \DateTimeImmutable('2026-10-28T12:00:00-03:00'));
        $corrected=$this->content($new);$this->assertSame(20,$corrected['realizado']);$this->assertSame(4,$corrected['quantidade']);$this->assertSame($id,$corrected['anterior']);
        $this->assertSame(60,$this->content($id)['realizado']);$this->assertSame(2,(int)Access::table('EvolucaoVersoes')->get($new)->revisao_semanal);
        $this->expectException(\DomainException::class);$service->close($this->admin,$this->season,$this->area,'2026-10-27','Tentativa com versão antiga',$id);
    }
    public function testPlansAreIndependentVersionedMonotonicAndDoNotRewriteClosures(): void
    {
        $service=new CurvaSemanal();$id=$service->savePlan($this->admin,$this->season,$this->area,['2026-10-20'=>5,'2026-10-27'=>35],'Plano inicial',0);
        $closed=$service->close($this->admin,$this->season,$this->area,'2026-10-27');$this->assertNull($this->content($closed)['programado']);
        $service->savePlan($this->admin,$this->season,$this->area,['2026-10-20'=>10,'2026-10-27'=>40,'2026-11-03'=>100],'Nova programação',$id);
        $this->assertNull($this->content($closed)['programado']);$this->assertSame([],$service->report($this->season,$this->other)['targets']);
        $this->expectException(\DomainException::class);$service->savePlan($this->admin,$this->season,$this->other,['2026-10-20'=>30,'2026-10-27'=>20],'Metas inválidas',0);
    }
    public function testFutureCloseInvalidPlanAndPermissionsAreBlocked(): void
    {
        $service=new CurvaSemanal();
        foreach([
            fn()=>$service->close($this->admin,$this->season,$this->area,'2026-11-03',now:new \DateTimeImmutable('2026-10-27T12:00:00-03:00')),
            fn()=>$service->close($this->admin,$this->season,$this->area,'2026-10-26'),
            fn()=>$service->savePlan($this->admin,$this->season,$this->area,['2026-10-27'=>101],'Inválido',0),
            fn()=>$service->savePlan($this->admin,$this->season,$this->area,['2026-10-28'=>30],'Inválido',0),
            fn()=>$service->savePlan($this->admin,$this->season,$this->area,[],'',0),
        ] as $operation) { try{$operation();$this->fail('Operação inválida aceita');}catch(\DomainException){$this->assertTrue(true);} }
        $this->expectException(\Cake\Http\Exception\ForbiddenException::class);$service->savePlan($this->pcm,$this->season,$this->area,[],'Sem permissão',0);
    }
    public function testOperatingDaysUseExistingCalendarExceptionsAndExposeDifferences(): void
    {
        $calendars=Access::table('CalendariosOperacionais');$calendar=$calendars->saveOrFail(Access::entity($calendars,['entressafra_id'=>$this->season,'chave'=>'area:'.$this->area,'area_fabrica_id'=>$this->area,'data_inicio'=>'2026-10-15','data_fim'=>'2026-10-30','inicio_confirmado'=>true]));
        $service=new CurvaSemanal();$this->assertSame(12,$service->operatingDays($this->season,$this->area,'2026-10-26')['realizados']);
        $service->saveOperatingDays($this->admin,$this->season,$this->area,[1,2,3,4,5],['2026-10-23'],'Regra operacional');
        $days=$service->operatingDays($this->season,$this->area,'2026-10-26');
        $this->assertSame(7,$days['realizados']);$this->assertSame(4,$days['restantes']);$this->assertCount(1,$days['warnings']);
        $this->assertSame('2026-10-15',$calendars->get($calendar->id)->data_inicio->format('Y-m-d'));
    }
    public function testTrendRequiresTwoSnapshotsIsClampedAndNotRealized(): void
    {
        $points=[['referencia'=>'2026-10-13','realizado'=>50,'tendencia'=>null],['referencia'=>'2026-10-20','realizado'=>80,'tendencia'=>null],['referencia'=>'2026-10-27','realizado'=>90,'tendencia'=>null],['referencia'=>'2026-11-03','realizado'=>null,'tendencia'=>null]];
        $trend=CurvaSemanal::trend($points);$this->assertSame(100.0,$trend[3]['tendencia']);$this->assertNull($trend[3]['realizado']);
        $points[1]['realizado']=null;$this->assertSame($points,CurvaSemanal::trend($points));
    }
    public function testLegacyWeightsAreIgnoredAndPlanningDoesNotModifyOperationalData(): void
    {
        (new \App\Service\Evolucao())->version($this->admin,$this->season,'pesos','Pesos legados',array_combine($this->orders,[100,1,1,1]),true);
        $db=Access::table('Atividades')->getConnection();$before=[];
        foreach(['atividades','apontamentos','insumos','evolucao_registros'] as $table)$before[$table]=$db->execute('SELECT * FROM '.$table.' ORDER BY id')->fetchAll('assoc');
        $service=new CurvaSemanal();$service->savePlan($this->admin,$this->season,$this->area,['2026-10-27'=>35],'Programação sem pesos',0);
        $id=$service->close($this->admin,$this->season,$this->area,'2026-10-27');$this->assertSame(62.5,$this->content($id)['realizado']);
        $this->assertNull((new \App\Service\Curva())->report($this->season,(string)$this->area)['official']);
        foreach($before as $table=>$rows)$this->assertSame($rows,$db->execute('SELECT * FROM '.$table.' ORDER BY id')->fetchAll('assoc'));
    }
    public function testCommandDryRunAndRepeatedExecutionAreIdempotent(): void
    {
        $command=new \App\Command\CurvaSemanalCommand();$io=$this->createMock(\Cake\Console\ConsoleIo::class);
        $options=['usuario'=>(string)$this->admin,'entressafra'=>(string)$this->season,'setor'=>(string)$this->area,'referencia'=>'2026-10-27','simular'=>true];
        $this->assertSame(0,$command->execute(new \Cake\Console\Arguments([],$options,[]),$io));
        $this->assertSame(0,Access::table('EvolucaoVersoes')->find()->where(['tipo'=>'curva_sem'])->count());
        $options['simular']=false;
        for($i=0;$i<2;$i++)$this->assertSame(0,$command->execute(new \Cake\Console\Arguments([],$options,[]),$io));
        $this->assertSame(1,Access::table('EvolucaoVersoes')->find()->where(['tipo'=>'curva_sem'])->count());
        $options['usuario']=(string)$this->pcm;$this->assertSame(1,$command->execute(new \Cake\Console\Arguments([],$options,[]),$io));
        PeriodoContexto::set($this->season);
    }
    public function testElectricalSimulationIsAdminOnlyReadOnlyAndReturnsToRealData(): void
    {
        $areas=Access::table('AreasFabrica');$row=$areas->get($this->area);$row->nome='ELÉTRICA';$areas->saveOrFail($row);
        $seasons=Access::table('Entressafras');$season=$seasons->get($this->season);$season->data_fim='2027-04-10';$seasons->saveOrFail($season);
        $this->autoCalendar($this->area,'2026-10-14','2027-04-10');
        (new CurvaSemanal())->close($this->admin,$this->season,$this->area,'2026-10-27',null,null,new \DateTimeImmutable('2026-10-27T12:00:00Z'));
        $db=$areas->getConnection();
        $state=static function()use($db):array{$out=[];foreach(['atividades','apontamentos','insumos','evolucao_registros','evolucao_versoes','calendarios_operacionais','auditorias'] as $table)$out[$table]=$db->execute('SELECT * FROM '.$table.' ORDER BY id')->fetchAll('assoc');return $out;};
        $before=$state();$url='/controle-pcm?area_id='.$this->area;
        $extract=static function(string $html):array{$doc=new \DOMDocument();@$doc->loadHTML($html);$xp=new \DOMXPath($doc);return ['planned'=>$xp->query('//*[@data-series="programado"]')->item(0)->getAttribute('d'),'bar'=>$doc->saveHTML($xp->query('//*[@data-total]')->item(0)),'weeks'=>$xp->query('//*[@data-week]')->length,'realized'=>$xp->query('//*[@data-series="realizado"]')->item(0)->getAttribute('d')];};
        $this->get($url);$this->assertResponseOk();$real=$extract((string)$this->_response->getBody());$this->assertResponseContains('Simular evolução');
        $this->get($url.'&simular=eletrica');$this->assertResponseOk();$this->assertHeader('Cache-Control','no-store, private');
        $this->assertResponseContains('SIMULAÇÃO — DADOS FICTÍCIOS');$this->assertResponseContains('Realizado simulado');$this->assertResponseContains('Tendência simulada');$this->assertResponseContains('Voltar aos dados reais');
        $sim=$extract((string)$this->_response->getBody());
        foreach(['planned','bar','weeks'] as $key)$this->assertSame($real[$key],$sim[$key]);
        $this->assertNotSame($real['realized'],$sim['realized']);
        $this->assertSame($before,$state());
        if(getenv('CURVA_SEMANAL_PREVIEW'))file_put_contents(TMP.'curva-simulacao-preview.html',(string)$this->_response->getBody());
        $this->get($url);$this->assertResponseOk();$this->assertResponseNotContains('data-simulation="true"');$this->assertSame($real,$extract((string)$this->_response->getBody()));
        $this->get('/controle-pcm?area_id='.$this->other.'&simular=eletrica');$this->assertResponseCode(400);
        $this->get('/controle-pcm?area_id='.$this->other);$this->assertResponseOk();$this->assertResponseNotContains('data-simulation="true"');
        $this->auth($this->pcm);$this->get($url.'&simular=eletrica');$this->assertResponseCode(403);
        $this->get($url);$this->assertResponseOk();$this->assertResponseNotContains('Simular evolução');$this->assertResponseNotContains('data-simulation="true"');
        $this->assertSame($before,$state());
    }
    public function testAdminHttpPlanClosingHistoryAndPcmReadOnly(): void
    {
        $table=Access::table('CalendariosOperacionais');$table->saveOrFail(Access::entity($table,['entressafra_id'=>$this->season,'chave'=>'area:'.$this->area,'area_fabrica_id'=>$this->area,'data_inicio'=>'2026-10-14','data_fim'=>'2026-11-02','inicio_confirmado'=>true,'dias_semana'=>'[1,2,3,4,5,6,7]']));
        $url='/curva-s/programacao?area_id='.$this->area;$this->get($url);$this->assertResponseOk();$this->assertResponseContains('2026-10-20');
        $this->post($url,['acao'=>'metas','base'=>'0','metas'=>['2026-10-20'=>'5','2026-10-27'=>'35'],'motivo'=>'Plano HTTP']);$this->assertResponseOk();$this->assertSame(0,Access::table('EvolucaoVersoes')->find()->where(['tipo'=>'curva_meta'])->count());
        $this->post($url,['acao'=>'apurar','referencia'=>'2026-10-27']);$this->assertResponseCode(302);
        $this->get('/controle-pcm?area_id='.$this->area);$this->assertResponseOk();$this->assertResponseContains('63%');$this->assertResponseContains('70%');$this->assertResponseContains('Atrasado');$this->assertResponseContains('data-series="tendencia"');$this->assertResponseNotContains('Pesos oficiais pendentes');
        $this->assertResponseContains('data-value="62.5"');$this->assertResponseContains('-7,00 p.p.');
        $this->assertDoesNotMatchRegularExpression('/\d+[,.]\d+%/',(string)$this->_response->getBody());
        $dom=new \DOMDocument();@$dom->loadHTML((string)$this->_response->getBody());$xp=new \DOMXPath($dom);
        $bar=$xp->query('//*[@data-total]')->item(0);$this->assertNotNull($bar);
        $today=(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        $position=\App\Service\ProgramacaoAutomatica::position((new CurvaSemanal())->automaticCalendar($this->season,$this->area),$today);
        $this->assertSame((string)$position['total'],$bar->getAttribute('data-total'));
        $this->assertSame((string)$position['decorridos'],$bar->getAttribute('data-elapsed'));
        $this->assertSame((string)$position['restantes'],$bar->getAttribute('data-remaining'));
        $this->assertSame(5,$xp->query('//*[contains(@class,"curve-history")]//th')->length);
        $this->assertResponseContains('Aguardando apuração');
        if(getenv('CURVA_SEMANAL_PREVIEW'))file_put_contents(TMP.'curva-semanal-preview.html',(string)$this->_response->getBody());
        $this->get($url);$this->assertResponseOk();$this->assertResponseContains('Histórico de versões');
        $this->auth($this->pcm);$this->get($url);$this->assertResponseCode(403);$this->get('/controle-pcm?area_id='.$this->area);$this->assertResponseOk();
    }
}
