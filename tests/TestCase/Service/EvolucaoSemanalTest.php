<?php
declare(strict_types=1);
namespace App\Test\TestCase\Service;
use App\Service\{Access,PeriodoContexto,EvolucaoSemanal,Evolucao,Curva,CurvaSemanal};
use Cake\TestSuite\{TestCase,IntegrationTestTrait};

class EvolucaoSemanalTest extends TestCase
{
    use IntegrationTestTrait;
    private int $admin,$one,$two,$season;private array $areas=[],$ids=[];private EvolucaoSemanal $sheet;
    protected function setUp(): void
    {
        parent::setUp();PeriodoContexto::set(null);
        foreach(['Auditorias','Insumos','EvolucaoPesos','EvolucaoMetas','EvolucaoRegistros','EvolucaoLotes','EvolucaoVersoes','CalendariosOperacionais','EvolucaoSemanas','VinculosItens','VinculosLotes','Apontamentos','Atividades','UsuariosSetores','Usuarios','Cadernos','Setores','AreasFabrica','Entressafras'] as $alias)Access::table($alias)->deleteAll([]);
        $stamp='2026-10-14 00:00:00';$t=Access::table('Usuarios');
        foreach(['admin'=>'ADMIN_PCM','one'=>'RESPONSAVEL_SETOR','two'=>'RESPONSAVEL_SETOR'] as $property=>$role)$this->$property=(int)$t->saveOrFail(Access::entity($t,['nome'=>$property,'login'=>'grade-'.$property,'senha_hash'=>password_hash('teste123',PASSWORD_DEFAULT),'perfil'=>$role,'computador_operacional'=>$role!=='ADMIN_PCM','ativo'=>true,'created'=>$stamp,'modified'=>$stamp]))->id;
        $t=Access::table('Entressafras');$this->season=(int)$t->saveOrFail($t->newEntity(['nome'=>'Grade semanal teste','data_inicio'=>'2026-10-14','data_fim'=>'2026-11-09','status'=>'ativa']))->id;
        foreach(['ELÉTRICA','MOENDA'] as $i=>$name){
            $t=Access::table('AreasFabrica');$area=$t->saveOrFail(Access::entity($t,['nome'=>$name,'entressafra_id'=>$this->season]));$this->areas[]=(int)$area->id;
            $t=Access::table('Setores');$sector=$t->saveOrFail($t->newEntity(['nome'=>$name,'area_totvs'=>'G'.$i,'filial_totvs'=>'01','ativo'=>true]));
            $t=Access::table('Cadernos');$book=$t->saveOrFail($t->newEntity(['nome'=>'Caderno '.$name,'setor_id'=>$sector->id,'entressafra_id'=>$this->season,'area_curva_id'=>$area->id,'ativo'=>true]));
            for($j=0;$j<2;$j++){$t=Access::table('Atividades');$number=sprintf('%06d',5300+count($this->ids));$this->ids[]=(int)$t->saveOrFail(Access::entity($t,['entressafra_id'=>$this->season,'caderno_id'=>$j===0?$book->id:null,'area_curva_id'=>$j===1?$area->id:null,'setor_executante_id'=>$sector->id,'area_executante_totvs'=>$sector->area_totvs,'identificacao_setor'=>'area_totvs','os_numero'=>$number,'os_filial'=>'01','os_chave'=>\App\Integration\Totvs\Orders::key('01',$number),'descricao_atividade'=>'Manutenção '.$number,'bem'=>'BEM '.$j,'servico'=>'ENTRESSAFRA','situacao_totvs'=>'L','disponivel_totvs'=>true,'snapshot_totvs'=>'{}','criado_por'=>$this->admin,'created'=>$stamp,'modified'=>$stamp]))->id;}
            $t=Access::table('CalendariosOperacionais');$t->saveOrFail(Access::entity($t,['entressafra_id'=>$this->season,'chave'=>'area:'.$area->id,'area_fabrica_id'=>$area->id,'data_inicio'=>'2026-10-14','data_fim'=>'2026-11-09','inicio_confirmado'=>true]));
        }
        PeriodoContexto::set($this->season);$this->sheet=new EvolucaoSemanal(new \DateTimeImmutable('2026-10-28T12:00:00Z'));$u=Access::actor($this->one);$this->session(['Auth'=>['id'=>$u->id,'stamp'=>hash('sha256',$u->senha_hash),'last'=>time()]]);$this->enableCsrfToken();
    }
    protected function tearDown(): void {PeriodoContexto::set(null);parent::tearDown();}
    private function cell(int $i,string $ref,mixed $value): array
    {
        $p=$this->sheet->page(Access::actor($this->one),$this->season,0,sprintf('%06d',5300+$i),1);$c=$p['rows'][0]['cells'][$ref];
        return ['atividade_id'=>$this->ids[$i],'referencia'=>$ref,'percentual'=>$value,'base'=>$c['valor'],'base_registro'=>$c['registro_id'],'revisao'=>$c['revisao']];
    }
    private function save(array $rows,?EvolucaoSemanal $sheet=null): array {return ($sheet??$this->sheet)->save($this->one,$this->season,0,$rows,bin2hex(random_bytes(16)));}
    public function testDynamicStartedWeeksPartialBoundariesSearchAndSector(): void
    {
        $weeks=$this->sheet->weeks($this->season);$this->assertCount(3,$weeks);$this->assertSame('2026-10-14',$weeks[0]['inicio']);$this->assertSame('2026-10-19',$weeks[0]['fim']);$this->assertSame('2026-10-20',$weeks[0]['referencia']);
        $this->assertCount(4,(new EvolucaoSemanal(new \DateTimeImmutable('2026-11-03T03:00:00Z')))->weeks($this->season));
        $this->assertSame(2,$this->sheet->page(Access::actor($this->one),$this->season,$this->areas[0],'',1)['total']);
        $this->assertSame(1,$this->sheet->page(Access::actor($this->two),$this->season,0,'005303',1)['total']);
        $this->assertCount(0,(new EvolucaoSemanal(new \DateTimeImmutable('2026-10-13')))->weeks($this->season));
        $t=Access::table('Entressafras');$s=$t->get($this->season);$s->data_inicio='2026-12-29';$s->data_fim='2027-01-11';$t->saveOrFail($s);
        $cross=(new EvolucaoSemanal(new \DateTimeImmutable('2027-01-05T03:00:00Z')))->weeks($this->season);$this->assertCount(2,$cross);$this->assertSame('2026-12-29',$cross[0]['inicio']);$this->assertSame('2027-01-04',$cross[0]['fim']);
    }
    public function testSeveralWeeksAndOrdersAreAccumulatedNotSummedAndImmediatelyVisible(): void
    {
        $result=$this->save([$this->cell(0,'2026-10-19',10),$this->cell(0,'2026-10-26',30),$this->cell(0,'2026-11-02',50),$this->cell(1,'2026-11-02',100)]);
        $this->assertSame(4,$result['quantidade']);$this->assertSame(50.,(new Evolucao())->state($this->ids[0],'2026-10-28')['percentual']);
        $this->assertEqualsWithDelta(75.,(new Curva())->report($this->season,(string)$this->areas[0])['metrics']['simples'],1e-9);
        $this->assertSame(4,Access::table('EvolucaoRegistros')->find()->count());
    }
    public function testBlankIsNotZeroAndMissingWeekCarriesWithoutWriting(): void
    {
        $this->save([$this->cell(0,'2026-10-19',20),$this->cell(1,'2026-10-19',0),$this->cell(2,'2026-10-19','')]);
        $p=$this->sheet->page(Access::actor($this->one),$this->season,0,'005300',1);$this->assertNull($p['rows'][0]['cells']['2026-10-26']['valor']);$this->assertSame(20.,$p['rows'][0]['cells']['2026-10-26']['acumulado']);
        $this->assertSame(2,Access::table('EvolucaoRegistros')->find()->count());$this->assertSame(0.,$this->sheet->page(Access::actor($this->one),$this->season,0,'005301',1)['rows'][0]['cells']['2026-10-19']['valor']);
    }
    public function testLiveWeeklyCurveCarriesAndRefreshesOnlyUntilNextAccumulatedEntry(): void
    {
        $this->save([$this->cell(0,'2026-10-19',10),$this->cell(0,'2026-11-02',50)]);
        $curve=new CurvaSemanal();$when=new \DateTimeImmutable('2026-11-03T15:00:00Z');
        $before=$curve->report($this->season,$this->areas[0],$when);
        $this->assertSame([5.,5.,25.],array_column(array_slice($before['points'],0,3),'realizado'));
        $this->assertSame(25.,$before['points'][3]['realizado']);
        $this->save([$this->cell(0,'2026-10-19',20)]);
        $after=$curve->report($this->season,$this->areas[0],$when);
        $this->assertSame([10.,10.,25.],array_column(array_slice($after['points'],0,3),'realizado'));
        $this->assertSame(array_column($before['points'],'programado'),array_column($after['points'],'programado'));
        $this->assertSame(3,Access::table('EvolucaoRegistros')->find()->count());
        $this->assertSame(0,Access::table('EvolucaoVersoes')->find()->count());
        $this->assertSame(50.,\App\Service\OrderProgress::status($this->ids[0])['percentual_atual']);
        $this->assertEqualsWithDelta(12.5,(new Curva())->report($this->season)['metrics']['simples'],1e-9);
    }
    public function testImportedOrdersRetainWeeklyHistoryWithoutClosing(): void
    {
        $this->save([$this->cell(0,'2026-10-26',50),$this->cell(1,'2026-11-02',50)]);
        Access::table('Atividades')->updateAll(['created'=>'2026-10-30 12:00:00'],['id IN'=>$this->ids]);
        $curve=new CurvaSemanal();$now=new \DateTimeImmutable('2026-11-03T15:00:00Z');
        $before=$curve->report($this->season,$this->areas[0],$now);
        $this->assertSame([0.,25.,50.,50.],array_column($before['points'],'realizado'));
        $this->save([$this->cell(0,'2026-10-26',70)]);
        $after=$curve->report($this->season,$this->areas[0],$now);
        $this->assertSame([0.,35.,60.,60.],array_column($after['points'],'realizado'));
        $past=$curve->report($this->season,$this->areas[0],new \DateTimeImmutable('2026-10-28T15:00:00Z'));
        $this->assertNull($past['points'][3]['realizado']);
        $this->assertSame(0,Access::table('EvolucaoVersoes')->find()->count());
    }
    public function testRevisionsAppendAndStaleCellDoesNotOverwriteOtherUser(): void
    {
        $a=$this->cell(0,'2026-10-19',10);$this->save([$a]);
        try{$this->sheet->save($this->two,$this->season,0,[$a],bin2hex(random_bytes(16)));$this->fail();}catch(\DomainException $ex){$this->assertStringContainsString('outra pessoa',$ex->getMessage());}
        $this->save([$this->cell(0,'2026-10-19',20)]);$history=(new Evolucao())->history($this->ids[0]);$this->assertCount(2,$history);$this->assertSame(10.,(float)$history[0]['percentual']);$this->assertSame(20.,(float)$history[1]['percentual']);
        $audit=Access::table('Auditorias')->find()->where(['acao'=>'evolucao_correcao'])->firstOrFail();$this->assertSame(10.,(float)json_decode($audit->antes,true)['percentual']);$this->assertSame($this->one,(int)$audit->usuario_id);
    }
    public function testReductionRequiresConfirmationAndReasonForOperationalUser(): void
    {
        $this->save([$this->cell(0,'2026-10-19',50),$this->cell(0,'2026-10-26',60)]);$r=$this->cell(0,'2026-10-26',40);
        try{$this->save([$r]);$this->fail();}catch(\DomainException $ex){$this->assertStringContainsString('justificativa',$ex->getMessage());}
        $this->save([$r+['confirmar_reducao'=>true,'motivo'=>'Percentual digitado incorretamente']]);$this->assertSame(40.,(new Evolucao())->state($this->ids[0],'2026-10-26')['percentual']);
    }
    public function testAtomicInvalidCellNoChangesAndFutureWeekRejected(): void
    {
        foreach([[$this->cell(0,'2026-10-19',10),$this->cell(1,'2026-10-19',101)],[['atividade_id'=>$this->ids[0],'referencia'=>'2026-11-09','percentual'=>10,'base'=>null,'revisao'=>0]]] as $rows){try{$this->save($rows);$this->fail();}catch(\DomainException){$this->assertSame(0,Access::table('EvolucaoRegistros')->find()->count());}}
    }
    public function testRetryAndUnchangedCellsNeverDuplicate(): void
    {
        $r=$this->cell(0,'2026-10-19',10);$key=bin2hex(random_bytes(16));$a=$this->sheet->save($this->one,$this->season,0,[$r],$key);$this->assertSame($a,$this->sheet->save($this->one,$this->season,0,[$r],$key));
        $this->assertSame(0,$this->save([$this->cell(0,'2026-10-19',10)])['quantidade']);$this->assertSame(1,Access::table('EvolucaoRegistros')->find()->count());
    }
    public function testLegacyReferencesAreDisplayedAndCorrectedWithoutRewritingOldRecords(): void
    {
        $legacy=new Evolucao();foreach(['2026-10-14'=>5,'2026-10-19'=>12] as $reference=>$value)$legacy->saveBatch($this->admin,$this->season,$reference,[['atividade_id'=>$this->ids[0],'percentual'=>$value,'revisao'=>0]],bin2hex(random_bytes(16)));
        $cell=$this->cell(0,'2026-10-19',17);$this->assertSame(12.,$cell['base']);$this->save([$cell]);
        $history=(new Evolucao())->history($this->ids[0]);$this->assertCount(3,$history);$this->assertSame([5.,12.,17.],array_map(static fn($r)=>(float)$r['percentual'],$history));$this->assertSame('2026-10-19',$history[2]['referencia']);
    }
    public function testBlankCannotSilentlyEraseExistingHistoricalEntry(): void
    {
        $this->save([$this->cell(0,'2026-10-19',0)]);$this->expectException(\DomainException::class);$this->expectExceptionMessage('não apaga');$this->save([$this->cell(0,'2026-10-19','')]);
    }
    public function testClosuresPreservedAndExplicitRevisionRetainsPreviousVersion(): void
    {
        $this->save([$this->cell(0,'2026-10-19',20)],new EvolucaoSemanal(new \DateTimeImmutable('2026-10-19T12:00:00Z')));
        $curva=new CurvaSemanal();$when=new \DateTimeImmutable('2026-10-28T15:00:00Z');$version=$curva->close($this->admin,$this->season,$this->areas[0],'2026-10-20',null,null,$when);$original=Access::table('EvolucaoVersoes')->get($version)->conteudo;
        $this->assertEqualsWithDelta(10.,json_decode($original,true)['realizado'],1e-9);
        $this->save([$this->cell(0,'2026-10-19',30)]);$report=$curva->report($this->season,$this->areas[0],$when);$this->assertTrue($report['points'][0]['revisao_pendente']);$this->assertEqualsWithDelta(15.,$report['points'][0]['realizado'],1e-9);$this->assertEqualsWithDelta(10.,$report['points'][0]['realizado_publicado'],1e-9);
        $new=$curva->close($this->admin,$this->season,$this->areas[0],'2026-10-20','Revisão solicitada após correção',$version,$when);$this->assertNotSame($version,$new);$this->assertSame($original,Access::table('EvolucaoVersoes')->get($version)->conteudo);
        $report=$curva->report($this->season,$this->areas[0],$when);$this->assertFalse($report['points'][0]['revisao_pendente']);$this->assertSame(2,$report['points'][0]['revisao_fechamento']);$this->assertEqualsWithDelta(15.,$report['points'][0]['realizado'],1e-9);
    }
    public function testReloadAndEditRecoverExactSavedCellsIncludingZeroAndPreviousWeeks(): void
    {
        $result=$this->save([$this->cell(0,'2026-10-19',0),$this->cell(0,'2026-10-26',35),$this->cell(1,'2026-11-02',50)]);
        $this->assertSame(3,$result['quantidade']);
        foreach($result['celulas'] as $saved)$this->assertSame($saved['percentual'],(float)Access::table('EvolucaoRegistros')->get($saved['registro_id'])->percentual);
        $this->save([$this->cell(0,'2026-10-26',45)]);
        $reader=new EvolucaoSemanal(new \DateTimeImmutable('2026-10-28T12:00:00Z'));
        $rows=$reader->page(Access::actor($this->one),$this->season,$this->areas[0],'',1)['rows'];
        $this->assertSame(0.,$rows[0]['cells']['2026-10-19']['valor']);
        $this->assertSame(45.,$rows[0]['cells']['2026-10-26']['valor']);
        $this->assertSame(50.,$rows[1]['cells']['2026-11-02']['valor']);
        $filtered=$reader->page(Access::actor($this->one),$this->season,0,'005300',99);
        $this->assertSame(1,$filtered['page']);$this->assertSame($rows[0]['cells'],$filtered['rows'][0]['cells']);
        $this->assertCount(3,(new Evolucao())->history($this->ids[0]));
    }
    public function testSavedValuesSurviveRealPaginationAndSearch(): void
    {
        $table=Access::table('Atividades');$prototype=$table->get($this->ids[0])->toArray();unset($prototype['id']);
        for($i=0;$i<51;$i++){
            $data=$prototype;$data['os_numero']=sprintf('%06d',6000+$i);$data['os_chave']=\App\Integration\Totvs\Orders::key('01',$data['os_numero']);
            $table->saveOrFail(Access::entity($table,$data));
        }
        $reader=new EvolucaoSemanal(new \DateTimeImmutable('2026-10-28T12:00:00Z'));$user=Access::actor($this->one);
        $page=$reader->page($user,$this->season,$this->areas[0],'',2);$this->assertSame(2,$page['pages']);
        $row=$page['rows'][0];$c=$row['cells']['2026-10-26'];
        $saved=$reader->save($this->one,$this->season,$this->areas[0],[['atividade_id'=>$row['id'],'referencia'=>'2026-10-26','percentual'=>50,'base'=>$c['valor'],'base_registro'=>$c['registro_id'],'revisao'=>$c['revisao']]],bin2hex(random_bytes(16)));
        $this->assertSame(1,$saved['quantidade']);$reader->page($user,$this->season,$this->areas[0],'',1);
        $this->assertSame(50.,$reader->page($user,$this->season,$this->areas[0],'',2)['rows'][0]['cells']['2026-10-26']['valor']);
        $this->assertSame(50.,$reader->page($user,$this->season,0,$row['numero'],1)['rows'][0]['cells']['2026-10-26']['valor']);
    }
    public function testStaleEditRollsBackEveryCellAndRetainsAuditHistory(): void
    {
        $this->save([$this->cell(0,'2026-10-19',10)]);
        $stale=$this->cell(0,'2026-10-19',25);
        $other=$this->cell(0,'2026-10-19',30);
        $this->sheet->save($this->two,$this->season,0,[$other],bin2hex(random_bytes(16)));
        $count=Access::table('Auditorias')->find()->count();
        try{$this->save([$this->cell(1,'2026-10-19',50),$stale]);$this->fail('Stale batch accepted');}
        catch(\DomainException $e){$this->assertStringContainsString('outra pessoa',$e->getMessage());}
        $this->assertSame(30.,$this->cell(0,'2026-10-19',0)['base']);
        $this->assertNull($this->cell(1,'2026-10-19',0)['base']);
        $this->assertSame($count,Access::table('Auditorias')->find()->count());
        $this->assertSame($this->two,(int)Access::table('EvolucaoRegistros')->find()->orderByDesc('id')->firstOrFail()->usuario_id);
    }
    public function testDatabaseFailureHasNoFalseHttpSuccessOrPartialWrites(): void
    {
        $db=Access::table('Atividades')->getConnection();
        $db->execute("CREATE TEMP TRIGGER fail_percentage BEFORE INSERT ON evolucao_registros WHEN NEW.percentual=77 BEGIN SELECT RAISE(ABORT,'Simulated database failure'); END");
        try{
            $rows=[$this->cell(0,'2026-10-19',25),$this->cell(1,'2026-10-19',77)];
            $this->configRequest(['headers'=>['X-Requested-With'=>'XMLHttpRequest']]);
            $this->post('/lancar-porcentagem?area_id='.$this->areas[0],['chave'=>bin2hex(random_bytes(16)),'alteracoes_json'=>json_encode($rows)]);
            $this->assertResponseCode(503);$this->assertResponseContains('"ok":false');$this->assertResponseNotContains('sucesso');
            foreach(['EvolucaoRegistros','EvolucaoLotes','Auditorias'] as $table)$this->assertSame(0,Access::table($table)->find()->count());
        }finally{$db->execute('DROP TRIGGER IF EXISTS fail_percentage');}
    }
    public function testHttpReloadRendersPersistedZeroAndCorrectionWithoutCache(): void
    {
        $url='/lancar-porcentagem?area_id='.$this->areas[0];
        foreach([0,35] as $value){
            $r=$this->cell(0,'2026-10-19',$value);
            $this->configRequest(['headers'=>['X-Requested-With'=>'XMLHttpRequest']]);
            $this->post($url,['chave'=>bin2hex(random_bytes(16)),'alteracoes_json'=>json_encode([$r])]);
            $this->assertResponseOk();$this->assertResponseContains('"quantidade":1');
            $this->get($url);$this->assertResponseOk();$this->assertHeader('Cache-Control','no-store, private');
            $doc=new \DOMDocument();@$doc->loadHTML((string)$this->_response->getBody());$xp=new \DOMXPath($doc);
            $node=$xp->query('//*[@data-percentage and @data-id="'.$this->ids[0].'" and @data-reference="2026-10-19"]')->item(0);
            $this->assertNotNull($node);$this->assertSame((string)$value,$node->getAttribute('value'));$this->assertSame((string)$value,$node->getAttribute('data-base'));
        }
    }
    public function testHttpGridAndDraftValidationAndPermissions(): void
    {
        $this->get('/lancar-porcentagem?area_id='.$this->areas[0]);$this->assertResponseOk();$html=(string)$this->_response->getBody();$doc=new \DOMDocument();@$doc->loadHTML($html);$xp=new \DOMXPath($doc);$this->assertSame(1,$xp->query('//*[@id="porcentagens-salvar"]')->length);$this->assertGreaterThanOrEqual(3,$xp->query('//*[@data-week-header]')->length);$this->assertResponseContains('Mais recente');
        if(getenv('EVOLUCAO_SEMANAL_PREVIEW'))file_put_contents(TMP.'evolucao-semanal-preview.html',$html);
        $this->configRequest(['headers'=>['X-Requested-With'=>'XMLHttpRequest']]);$r=$this->cell(0,'2026-10-19',25);$this->post('/lancar-porcentagem?area_id='.$this->areas[0],['chave'=>bin2hex(random_bytes(16)),'alteracoes_json'=>json_encode([$r])]);$this->assertResponseOk();$this->assertResponseContains('"quantidade":1');
        $this->get('/usuarios');$this->assertResponseCode(403);
    }
}
