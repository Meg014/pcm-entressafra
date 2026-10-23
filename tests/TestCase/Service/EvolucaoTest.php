<?php
declare(strict_types=1);
namespace App\Test\TestCase\Service;
use App\Service\{Access,Activities,Evolucao,Users,TimeEntries,ConflictException,EvolucaoExcel};
use App\Test\Support\FakeTotvs;
use Cake\TestSuite\{TestCase,IntegrationTestTrait};
use Cake\Datasource\ConnectionManager;

class EvolucaoTest extends TestCase
{
    use IntegrationTestTrait;
    private Evolucao $service;
    private int $admin;
    private int $pcm;
    private int $responsible;
    private int $season;
    private array $activities=[];
    private array $sectors=[];
    private array $books=[];
    protected function setUp(): void
    {
        parent::setUp();
        foreach(['EvolucaoPesos','EvolucaoMetas','EvolucaoRegistros','EvolucaoLotes','EvolucaoVersoes','CalendariosOperacionais','EvolucaoSemanas','VinculosItens','VinculosLotes','Auditorias','Apontamentos','Atividades','UsuariosSetores','Usuarios','Cadernos','Setores','AreasFabrica','Entressafras'] as $alias) { Access::table($alias)->deleteAll([]); }
        $t=Access::table('Usuarios'); $now=gmdate('Y-m-d H:i:s');
        $this->admin=(int)$t->saveOrFail(Access::entity($t,['nome'=>'Administrador de teste','login'=>'evolucao-admin','senha_hash'=>password_hash('teste123',PASSWORD_DEFAULT),'perfil'=>'ADMIN_PCM','ativo'=>true,'created'=>$now,'modified'=>$now]))->id;
        $t=Access::table('Entressafras'); $this->season=(int)$t->saveOrFail($t->newEntity(['nome'=>'Teste evolução','data_inicio'=>'2026-09-01','data_fim'=>'2026-10-20','status'=>'ativa']))->id;
        foreach(['A0','A1'] as $i=>$area) {
            $t=Access::table('Setores');$sector=$t->saveOrFail($t->newEntity(['nome'=>'Setor '.$i,'ativo'=>true,'area_totvs'=>$area,'filial_totvs'=>'01']));$this->sectors[]=(int)$sector->id;
            $t=Access::table('Cadernos');$book=$t->saveOrFail($t->newEntity(['nome'=>'Caderno '.$i,'entressafra_id'=>$this->season,'setor_id'=>$sector->id,'ativo'=>true]));$this->books[]=(int)$book->id;
            $this->activities[]=(new Activities(new FakeTotvs()))->link($this->admin,(int)$book->id,'os-'.$i);
        }
        $users=new Users();
        $this->pcm=$users->save($this->admin,['nome'=>'PCM teste','login'=>'evolucao-pcm','senha'=>'teste123','perfil'=>'PCM','ativo'=>'1']);
        $this->responsible=$users->save($this->admin,['nome'=>'Responsável teste','login'=>'evolucao-resp','senha'=>'teste123','perfil'=>'RESPONSAVEL_SETOR','ativo'=>'1','setores'=>[$this->sectors[0]]]);
        $this->service=new Evolucao(); $this->service->configureCalendar($this->admin,$this->season,array_column($this->service->calendar($this->season),'referencia'));
    }
    private function save(string $week,float $value,int $index=0,?int $user=null,int $revision=0,string $reason=''): array
    { return $this->service->saveBatch($user??$this->admin,$this->season,$week,[['atividade_id'=>$this->activities[$index],'percentual'=>$value,'revisao'=>$revision,'motivo'=>$reason]],bin2hex(random_bytes(16))); }
    private function auth(int $id): void
    { $u=Access::actor($id); $this->session(['Periodo'=>['id'=>$this->season,'usuario_id'=>$id],'Auth'=>['id'=>$id,'stamp'=>hash('sha256',$u->senha_hash),'last'=>time()]]); }
    public function testAccumulatedSequenceAndMissingWeekCarryForward(): void
    {
        foreach(['2026-09-07'=>0,'2026-09-14'=>10,'2026-09-21'=>50,'2026-09-28'=>100] as $date=>$value) { $this->save($date,$value,0,$this->responsible); }
        $s=$this->service->state($this->activities[0],'2026-10-05');
        $this->assertSame(100.0,$s['percentual']);$this->assertTrue($s['pendente']);
        $this->assertCount(4,$this->service->history($this->activities[0]));
        $this->assertSame(50.0,$this->service->state($this->activities[0],'2026-09-28')['anterior']);
    }
    public function testOptionalWeeklyUpdatesCarryForwardAndNeverCreateArtificialZeros(): void
    {
        $this->service->version($this->admin,$this->season,'pesos','Pesos',[$this->activities[0]=>40,$this->activities[1]=>60],true);
        $this->save('2026-09-07',50,0,$this->responsible);
        $before=Access::table('EvolucaoRegistros')->find()->count();
        foreach(['2026-09-14','2026-09-21','2026-09-28'] as $week) {
            $report=$this->service->report($this->season,$this->service->activities($this->season),$week);
            $this->assertSame(20.0,$report['realizado']);$this->assertSame(0.0,$report['variacao']);
            $this->assertSame(['total'=>2,'sem_inicio'=>1,'andamento'=>1,'concluidas'=>0,'pendentes'=>2],$report['counts']);
            $this->assertSame(0.0,$this->service->state($this->activities[1],$week)['percentual']);
        }
        $this->assertSame($before,Access::table('EvolucaoRegistros')->find()->count());
        $this->assertCount(0,$this->service->history($this->activities[1]));
        $this->save('2026-10-05',50,0,$this->responsible);
        $confirmed=$this->service->report($this->season,$this->service->activities($this->season),'2026-10-05');
        $this->assertSame(20.0,$confirmed['realizado']);$this->assertSame(0.0,$confirmed['variacao']);$this->assertSame(1,$confirmed['counts']['pendentes']);
    }
    public function testNeverUpdatedOrdersProduceZeroCurveWithoutAnyWeeklyRecords(): void
    {
        $this->service->version($this->admin,$this->season,'pesos','Pesos',array_fill_keys($this->activities,1),true);
        Access::table('Atividades')->updateAll(['disponivel_totvs'=>false,'situacao_totvs'=>'Encerrada'],['id'=>$this->activities[0]]);
        $report=$this->service->report($this->season,$this->service->activities($this->season),'2026-10-12');
        $this->assertSame(0.0,$report['realizado']);$this->assertSame(0.0,$report['variacao']);
        $this->assertSame(2,$report['counts']['sem_inicio']);$this->assertSame(2,$report['counts']['pendentes']);
        $this->assertSame(0,Access::table('EvolucaoRegistros')->find()->count());
    }
    public function testPartialHttpBatchSavesOnlyFilledOrderAndUnupdatedCompletionIsInformative(): void
    {
        $this->auth($this->admin);$this->enableCsrfToken();
        $this->post('/evolucao/correcoes?entressafra_id='.$this->season.'&referencia=2026-09-07',['chave'=>bin2hex(random_bytes(16)),'alteracoes'=>[['atividade_id'=>$this->activities[0],'percentual'=>100,'revisao'=>0]]]);
        $this->assertRedirect();$this->assertSame(1,Access::table('EvolucaoRegistros')->find()->count());
        $report=$this->service->report($this->season,$this->service->activities($this->season),'2026-09-14');
        $this->assertSame(1,$report['counts']['concluidas']);$this->assertSame(1,$report['counts']['sem_inicio']);$this->assertSame(2,$report['counts']['pendentes']);
        $this->get('/evolucao/correcoes?entressafra_id='.$this->season.'&referencia=2026-09-14');$this->assertResponseOk();
        $this->assertResponseContains('Não é obrigatório atualizar todas as O.S. toda semana.');$this->assertResponseContains('Sem atualização na semana');
    }
    public function testRequestedAccumulatedSequenceCalculatesDifferencesWithoutSummingInputs(): void
    {
        foreach (['2026-09-01'=>[20,20],'2026-09-07'=>[30,10],'2026-09-14'=>[55,25],'2026-09-21'=>[80,25],'2026-09-28'=>[100,20]] as $week=>[$value,$delta]) {
            $this->save($week,$value,0,$this->responsible);
            $state=$this->service->state($this->activities[0],$week);
            $this->assertSame((float)$value,$state['percentual']);$this->assertSame((float)$delta,$state['variacao']);$this->assertFalse($state['pendente']);
        }
        $this->save('2026-10-05',100,0,$this->responsible);
        $confirmed=$this->service->state($this->activities[0],'2026-10-05');
        $this->assertSame(0.0,$confirmed['variacao']);$this->assertFalse($confirmed['pendente']);
        $missing=$this->service->state($this->activities[0],'2026-10-12');
        $this->assertSame(100.0,$missing['percentual']);$this->assertTrue($missing['pendente']);
    }
    public function testBulkConfirmationOfUnchangedAccumulatedValueKeepsWeightedCurve(): void
    {
        $this->service->version($this->admin,$this->season,'pesos','Pesos conferidos',array_fill_keys($this->activities,1),true);
        foreach(['2026-09-07','2026-09-14'] as $week) {
            $rows=[];foreach($this->activities as $id){$rows[]=['atividade_id'=>$id,'percentual'=>30,'revisao'=>0];}
            $this->service->saveBatch($this->admin,$this->season,$week,$rows,bin2hex(random_bytes(16)));
        }
        $report=$this->service->report($this->season,$this->service->activities($this->season),'2026-09-14');
        $this->assertSame(30.0,$report['realizado']);$this->assertSame(0.0,$report['variacao']);$this->assertSame(0,$report['counts']['pendentes']);
        $this->assertSame(0.0,$this->service->state($this->activities[1],'2026-09-14')['variacao']);
    }
    public function testBulkIsAtomicAndIdempotent(): void
    {
        $rows=[];foreach($this->activities as $id){$rows[]=['atividade_id'=>$id,'percentual'=>'10.25','revisao'=>0];}$key=bin2hex(random_bytes(16));
        $a=$this->service->saveBatch($this->admin,$this->season,'2026-09-07',$rows,$key);
        $this->assertSame($a,$this->service->saveBatch($this->admin,$this->season,'2026-09-07',$rows,$key));
        $this->assertSame(2,Access::table('EvolucaoRegistros')->find()->count());
        try{$this->service->saveBatch($this->responsible,$this->season,'2026-09-14',$rows,bin2hex(random_bytes(16)));$this->fail();}catch(\Cake\Http\Exception\ForbiddenException){}
        $this->assertTrue($this->service->state($this->activities[0],'2026-09-14')['pendente']);
    }
    public function testRetroactiveInsertionAndHistoricalCutoff(): void
    {
        $this->save('2026-09-21',50);$this->save('2026-09-14',10);
        $this->assertSame(10.0,$this->service->state($this->activities[0],'2026-09-21')['anterior']);
        $this->assertSame(0.0,$this->service->state($this->activities[0],'2026-09-21','2000-01-01 00:00:00')['percentual']);
        $this->expectException(\DomainException::class);$this->save('2026-09-07',60);
    }
    public function testCorrectionRequiresPcmReasonAndRetainsHistory(): void
    {
        $this->save('2026-09-07',50);
        try{$this->save('2026-09-14',10,0,$this->responsible);$this->fail();}catch(\Cake\Http\Exception\ForbiddenException){}
        try{$this->save('2026-09-07',40,0,$this->pcm,1);$this->fail();}catch(\DomainException){}
        $this->save('2026-09-07',40,0,$this->pcm,1,'Conferência física corrigida');
        $h=$this->service->history($this->activities[0]);$this->assertCount(2,$h);$this->assertSame(50.0,(float)$h[0]['percentual']);
        $this->assertSame(40.0,$this->service->state($this->activities[0],'2026-09-07')['percentual']);
    }
    public function testStaleRevisionCannotOverwriteAnotherUser(): void
    { $this->save('2026-09-07',10);$this->expectException(\DomainException::class);$this->save('2026-09-07',20,0,$this->pcm,0,'Correção'); }
    public function testDuplicateRowRollsBackWholeBatch(): void
    {
        $row=['atividade_id'=>$this->activities[0],'percentual'=>20,'revisao'=>0];
        try{$this->service->saveBatch($this->admin,$this->season,'2026-09-07',[$row,$row],bin2hex(random_bytes(16)));$this->fail();}catch(\DomainException){}
        $this->assertSame(0,Access::table('EvolucaoRegistros')->find()->count());
    }
    public function testClosedTotvsOrderRetainsPercentagesAndAcceptsPhysicalUpdate(): void
    {
        $this->save('2026-09-07',10);Access::table('Atividades')->updateAll(['disponivel_totvs'=>false,'situacao_totvs'=>'Encerrada'],['id'=>$this->activities[0]]);
        $this->save('2026-09-14',50,0,$this->responsible);$this->assertSame(50.0,$this->service->state($this->activities[0],'2026-09-14')['percentual']);
    }
    public function testNoWeightDoesNotBlockProgressAndCurveRemainsPending(): void
    {
        $this->save('2026-09-07',50);$r=$this->service->report($this->season,$this->service->activities($this->season),'2026-09-07');
        $this->assertNull($r['realizado']);$this->assertSame(1,$r['counts']['andamento']);$this->assertSame(1,$r['counts']['pendentes']);
    }
    public function testApprovedWeightsTargetsAndNoDuplicateConsolidation(): void
    {
        $this->save('2026-09-07',50);$this->save('2026-09-07',100,1);
        $weights=[$this->activities[0]=>40,$this->activities[1]=>60];
        $this->service->version($this->admin,$this->season,'pesos','Pesos conferidos',$weights,true);
        $dates=array_column($this->service->calendar($this->season),'referencia');$targets=[];foreach($dates as $i=>$d){$targets[$d]=round($i/(count($dates)-1)*100,2);}
        $this->service->version($this->admin,$this->season,'metas','Linha original',$targets,true);
        $t=Access::table('CadernosSetores');$t->saveOrFail(Access::entity($t,['caderno_id'=>$this->books[0],'setor_id'=>$this->sectors[1]]));
        $r=$this->service->report($this->season,$this->service->activities($this->season),'2026-09-07');
        $this->assertSame(80.0,$r['realizado']);$this->assertSame(2,$r['counts']['total']);$this->assertNotNull($r['programado']);
        $this->assertSame(55.0,Evolucao::weighted([1=>50,2=>100,3=>0],[1=>40,2=>35,3=>25]));
        $filtered=$this->service->report($this->season,[$this->service->activities($this->season)[0]],'2026-09-07');$this->assertNull($filtered['programado']);$this->assertSame(50.0,$filtered['realizado']);
    }
    public function testApprovalIsAdminOnlyAndMissingWeightsCannotBeApproved(): void
    {
        try{$this->service->version($this->pcm,$this->season,'pesos','Sem permissão',[],true);$this->fail();}catch(\Cake\Http\Exception\ForbiddenException){}
        $this->expectException(\DomainException::class);$this->service->version($this->admin,$this->season,'pesos','Incompleto',[$this->activities[0]=>1],true);
    }
    public function testPlanMustBeMonotoneCompleteAndVersionsRemain(): void
    {
        try{$this->service->version($this->admin,$this->season,'metas','Inválido',['2026-09-07'=>50,'2026-09-14'=>10]);$this->fail();}catch(\DomainException){}
        $this->service->version($this->pcm,$this->season,'metas','Rascunho',['2026-09-07'=>10]);
        $this->service->version($this->pcm,$this->season,'metas','Replanejamento',['2026-09-07'=>20]);
        $this->assertCount(2,$this->service->versions($this->season,'metas'));
        $this->expectException(\DomainException::class);$this->service->version($this->admin,$this->season,'metas','Incompleto',['2026-09-07'=>100],true);
    }
    public function testPartialCalendarAndOutOfRange(): void
    {
        $dates=array_column($this->service->calendar($this->season),'referencia');$this->assertSame('2026-09-01',$dates[0]);$this->assertSame('2026-10-20',end($dates));$this->assertContains('2026-09-07',$dates);
        $this->expectException(\DomainException::class);$this->service->configureCalendar($this->pcm,$this->season,['2027-01-01']);
    }
    public function testPercentBoundsAndInjectionAreRejected(): void
    {
        foreach([-1,101,'0;DROP TABLE atividades','NaN','1.123'] as $n){try{Evolucao::number($n);$this->fail();}catch(\DomainException){$this->assertTrue(true);}}
    }
    public function testRoutesAllRolesAndSectorHistoryAuthorization(): void
    {
        foreach([$this->admin,$this->pcm,$this->responsible] as $id){$this->auth($id);$this->get('/lancar-porcentagem?entressafra_id='.$this->season);$this->assertResponseOk();$this->assertResponseContains('Setor da Curva S');
            foreach(['/evolucao/configuracao','/controle-pcm'] as $url){$this->get($url.'?entressafra_id='.$this->season);$this->assertResponseCode($id===$this->responsible?403:200);}}
        $this->auth($this->responsible);$this->get('/evolucao/historico/'.$this->activities[1]);$this->assertResponseCode(403);
        $this->get('/evolucao/historico/'.$this->activities[0]);$this->assertResponseOk();
        $this->session(['Auth'=>[]]);$this->get('/evolucao');$this->assertRedirect('/login');
    }
    public function testHttpBulkSaveAndCsrfProtection(): void
    {
        $this->auth($this->admin);$url='/evolucao/correcoes?entressafra_id='.$this->season.'&referencia=2026-09-07';
        $data=['chave'=>bin2hex(random_bytes(16)),'alteracoes'=>[['atividade_id'=>$this->activities[0],'percentual'=>10,'revisao'=>0]]];
        $this->post($url,$data);$this->assertResponseCode(403);
        $this->enableCsrfToken();$this->post($url,$data);$this->assertRedirect();$this->assertSame(10.0,$this->service->state($this->activities[0],'2026-09-07')['percentual']);
    }
    public function testFullDatetimesDifferentDaysConsecutiveAndOver24Hours(): void
    {
        $entries=new TimeEntries(new FakeTotvs());
        $base=['atividade_id'=>$this->activities[0],'funcionario_chave'=>'pessoa-1','descricao'=>'Serviço de teste'];
        foreach(['2026-10-01','2026-10-02'] as $d){$entries->save($this->admin,$base+['inicio'=>$d.'T07:00','fim'=>$d.'T09:00']);}
        $entries->save($this->admin,$base+['inicio'=>'2026-10-02T09:00','fim'=>'2026-10-02T10:00']);
        $id=$entries->save($this->admin,$base+['inicio'=>'2026-10-03T23:00','fim'=>'2026-10-05T01:00']);$this->assertSame(1560,Access::table('Apontamentos')->get($id)->minutos);
        $conflicts=$entries->conflicts('pessoa-1','2026-10-04 04:00:00','2026-10-04 05:00:00');$this->assertCount(1,$conflicts);
        $this->assertSame(4,Access::table('Apontamentos')->find()->count());
    }
    public function testExcelPreviewRealXlsxAndFormulaRejection(): void
    {
        $book=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$sheet=$book->getActiveSheet();$sheet->setTitle('Dados');$sheet->fromArray([['atividade_id','peso'],[$this->activities[0],40]]);
        $file=tempnam(TMP,'pesos');
        try{(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($file);$this->assertSame([(string)$this->activities[0]=>'40'],(new EvolucaoExcel())->read($file,'pesos'));
            $sheet->setCellValue('B2','=1+1');(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($file);$this->expectException(\DomainException::class);(new EvolucaoExcel())->read($file,'pesos');
        }finally{unlink($file);$book->disconnectWorksheets();}
    }
    public function testTrendUsesObservedHistoryAndDoesNotReplaceTargets(): void
    {
        $this->service->version($this->admin,$this->season,'pesos','Pesos',array_fill_keys($this->activities,1),true);
        $this->save('2026-09-07',10);$this->save('2026-09-14',50);
        $r=$this->service->report($this->season,$this->service->activities($this->season),'2026-09-14');
        $this->assertNull($r['programado']);
        $future=array_values(array_filter($r['points'],fn($p)=>$p['referencia']==='2026-09-21'))[0];
        $this->assertNull($future['realizado']);$this->assertSame(45.0,$future['tendencia']);
    }
    public function testIdempotencyPayloadReuseIsRejected(): void
    {
        $key=bin2hex(random_bytes(16));$rows=[['atividade_id'=>$this->activities[0],'percentual'=>0,'revisao'=>0]];
        $this->service->saveBatch($this->admin,$this->season,'2026-09-07',$rows,$key);
        $rows[0]['percentual']=10;
        $this->expectException(\DomainException::class);$this->service->saveBatch($this->admin,$this->season,'2026-09-07',$rows,$key);
    }
    public function testNewActivityWithoutWeightMakesWholeSeasonPending(): void
    {
        $this->service->version($this->admin,$this->season,'pesos','Escopo original',array_fill_keys($this->activities,1),true);
        (new Activities(new FakeTotvs()))->link($this->admin,$this->books[0],'nova-os');
        $r=$this->service->report($this->season,$this->service->activities($this->season),'2026-09-07');$this->assertNull($r['realizado']);$this->assertSame(3,$r['counts']['total']);
        $this->assertCount(1,$this->service->versions($this->season,'pesos'));
        Access::table('Atividades')->updateAll(['created'=>'2040-01-01 00:00:00'],['os_chave'=>'nova-os']);
        $historical=$this->service->report($this->season,$this->service->activities($this->season),'2026-09-07',asOf:gmdate('Y-m-d H:i:s'));
        $this->assertSame(2,$historical['counts']['total']);$this->assertTrue($historical['escopo_completo']);$this->assertSame(0.0,$historical['realizado']);
    }
    public function testSharedNotebookAllowsOnlyAuthorizedExecutingSector(): void
    {
        $t=Access::table('CadernosSetores');$t->saveOrFail(Access::entity($t,['caderno_id'=>$this->books[0],'setor_id'=>$this->sectors[1]]));
        Access::table('Atividades')->updateAll(['caderno_id'=>$this->books[0]],['id'=>$this->activities[1]]);
        $this->save('2026-09-07',10,0,$this->responsible);
        $this->expectException(\Cake\Http\Exception\ForbiddenException::class);$this->save('2026-09-07',10,1,$this->responsible);
    }
    public function testExcelTemplateDownloadsAndManagerDataCannotBeChangedByResponsible(): void
    {
        $this->auth($this->admin);$this->get('/evolucao/modelo/pesos?entressafra_id='.$this->season);$this->assertResponseOk();$this->assertStringStartsWith('PK',(string)$this->_response->getBody());
        $this->enableCsrfToken();$this->auth($this->responsible);$this->post('/evolucao/configuracao?entressafra_id='.$this->season,['acao'=>'versao','tipo'=>'pesos','valores'=>array_fill_keys($this->activities,1),'nome'=>'Tentativa']);$this->assertResponseCode(403);
        $this->assertCount(0,$this->service->versions($this->season,'pesos'));
    }
    public function testFuturePhysicalExecutionIsRejected(): void
    {
        $t=Access::table('Entressafras');$future=$t->saveOrFail($t->newEntity(['nome'=>'Futuro','data_inicio'=>'2040-01-01','data_fim'=>'2040-01-31','status'=>'planejamento']));
        $this->service->configureCalendar($this->admin,(int)$future->id,['2040-01-02']);
        $this->expectException(\DomainException::class);$this->service->saveBatch($this->admin,(int)$future->id,'2040-01-02',[['atividade_id'=>$this->activities[0],'percentual'=>10,'revisao'=>0]],bin2hex(random_bytes(16)));
    }
    public function testEmptyHistoricalCutoffIsAValidCurrentManagerQuery(): void
    {
        $this->auth($this->admin);$this->get('/controle-pcm?entressafra_id='.$this->season.'&pesos=&metas=&registrado_ate=');$this->assertResponseOk();
    }
}
