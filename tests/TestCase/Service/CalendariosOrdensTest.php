<?php
declare(strict_types=1);
namespace App\Test\TestCase\Service;
use App\Service\{Access,Users,Activities,TimeEntries,FilaPcm,OperacaoRapida,Evolucao,ConflictException};
use App\Test\Support\FakeTotvs;
use Cake\TestSuite\{TestCase,IntegrationTestTrait};
class CalendariosOrdensTest extends TestCase
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
        $this->mockService(\App\Integration\Totvs\GatewayInterface::class,function(){return new FakeTotvs();});
        $this->auth($this->admin);
    }
    private function auth(int $id): void {$u=Access::actor($id);$this->session(['Periodo'=>['id'=>$this->season,'usuario_id'=>$id],'Auth'=>['id'=>$id,'stamp'=>hash('sha256',$u->senha_hash),'last'=>time()]]);}
    private function entry(int $index=0,string $start='2026-10-01T07:00',string $end='2026-10-01T09:00'): int
    {return $this->entries->save($this->admin,['atividade_id'=>$this->activities[$index],'funcionario_chave'=>'pessoa-1','inicio'=>$start,'fim'=>$end,'descricao'=>'Serviço de teste']);}
    private function select(int $id): array {return ['id'=>$id,'revisao'=>Access::table('Apontamentos')->get($id)->revisao_pcm];}

    private function area(string $name): int { $t=Access::table('AreasFabrica');return (int)$t->saveOrFail(Access::entity($t,['nome'=>$name]))->id; }
    private function schedule(int $area,string $end,bool $confirmed=true): int {
        Access::table('Entressafras')->updateAll(['data_fim'=>'2027-04-10'],['id'=>$this->season]);
        return (new \App\Service\Calendarios())->save($this->admin,$this->season,['area_fabrica_id'=>$area,'data_inicio'=>$confirmed?'2026-10-19':'','data_fim'=>$end,'inicio_confirmado'=>$confirmed?'1':'0']);
    }
    public function testNoCalendarOrNotebookRequiredForHoursProgressAndSectorCurve(): void {
        Access::table('EvolucaoSemanas')->deleteAll([]);
        $id=(new Activities(new FakeTotvs()))->identify($this->computer,'nova-os');
        $service=new Evolucao();
        [$reference,$week]=(new OperacaoRapida())->reference($this->season);
        $this->assertNull($week);
        $this->assertGreaterThan(0,$this->entries->save($this->computer,['atividade_id'=>$id,'funcionario_chave'=>'pessoa-1','data_servico'=>'01/10/2026','hora_inicial'=>'07:00','hora_final'=>'09:00','descricao'=>'Sem calendário ou caderno']));
        $service->saveBatch($this->computer,$this->season,$reference,[['atividade_id'=>$id,'percentual'=>60,'revisao'=>0]],bin2hex(random_bytes(16)));
        $this->assertSame(60.0,$service->state($id,$reference)['percentual']);
        $this->assertCount(1,Access::table('EvolucaoSemanas')->find()->all());
        $this->assertGreaterThan(1,count($service->calendar($this->season)));
        $activities=$service->activities($this->season);
        $sector=array_values(array_filter($activities,fn($a)=>(int)$a['setor_executante_id']===$this->sectors[0]));
        $report=$service->report($this->season,$sector,$reference);
        $this->assertSame(30.0,$report['simple']);
        $this->assertSame(2,$report['counts']['total']);
        $this->assertNull($report['realizado']);
        $this->assertNull($report['programado']);
        $this->assertSame(30.0,array_values(array_filter($report['points'],fn($p)=>$p['referencia']===$reference))[0]['simples']);
        $this->assertSame(20.0,$service->report($this->season,$activities,$reference)['simple']);
        Access::table('Atividades')->updateAll(['disponivel_totvs'=>false,'situacao_totvs'=>'F'],['id'=>$id]);
        $this->assertSame(60.0,(new Evolucao())->state($id,$reference)['percentual']);
        $this->assertCount(1,(new Evolucao())->history($id));
        $this->auth($this->admin);$this->get('/evolucao/analise?entressafra_id='.$this->season.'&setor_id='.$this->sectors[0]);
        $this->assertResponseOk();$this->assertResponseContains('Evolução física simples (não ponderada)');$this->assertResponseContains('30,00%');$this->assertResponseContains('Programação pendente');
    }
    public function testAutomaticReferenceContinuesAfterFirstUnconfirmedWeeklyEntry(): void {
        Access::table('EvolucaoSemanas')->deleteAll([]);
        $s=new Evolucao();
        $s->saveBatch($this->computer,$this->season,'2026-09-07',[['atividade_id'=>$this->activities[0],'percentual'=>10,'revisao'=>0]],bin2hex(random_bytes(16)));
        $this->assertSame('2026-10-20',(new OperacaoRapida())->reference($this->season)[0]);
        $this->assertSame(10.0,$s->state($this->activities[0],'2026-10-20')['percentual']);
        $s->saveBatch($this->computer,$this->season,'2026-10-20',[['atividade_id'=>$this->activities[0],'percentual'=>40,'revisao'=>0]],bin2hex(random_bytes(16)));
        $this->assertCount(2,$s->history($this->activities[0]));
    }
    public function testInvalidReferenceDoesNotCreateWeekOrProgress(): void {
        Access::table('EvolucaoSemanas')->deleteAll([]);
        try {
            (new Evolucao())->saveBatch($this->computer,$this->season,'2026-09-09',[['atividade_id'=>$this->activities[0],'percentual'=>10,'revisao'=>0]],bin2hex(random_bytes(16)));
            $this->fail('Referência fora das semanas deveria ser rejeitada.');
        } catch (\DomainException $e) { $this->assertStringContainsString('Referência fora',$e->getMessage()); }
        $this->assertSame(0,Access::table('EvolucaoSemanas')->find()->count());
        $this->assertSame(0,Access::table('EvolucaoRegistros')->find()->count());
    }
    public function testUnauthorizedProgressRollsBackAutomaticWeek(): void {
        Access::table('EvolucaoSemanas')->deleteAll([]);
        try {
            (new Evolucao())->saveBatch($this->computer,$this->season,'2026-10-19',[['atividade_id'=>$this->activities[1],'percentual'=>10,'revisao'=>0]],bin2hex(random_bytes(16)));
            $this->fail('O setor não autorizado deveria ser rejeitado.');
        } catch (\Cake\Http\Exception\ForbiddenException) {}
        $this->assertSame(0,Access::table('EvolucaoSemanas')->find()->count());
        $this->assertSame(0,Access::table('EvolucaoRegistros')->find()->count());
    }
    public function testQuickProgressPostCreatesWeekWithoutCalendarConfirmation(): void
    {
        Access::table('EvolucaoSemanas')->deleteAll([]);
        $t=Access::table('AreasFabrica');$area=$t->saveOrFail(Access::entity($t,['nome'=>'MOENDA','entressafra_id'=>$this->season]));
        $book=Access::table('Atividades')->get($this->activities[0])->caderno_id;Access::table('Cadernos')->updateAll(['area_curva_id'=>$area->id],['id'=>$book]);
        $this->auth($this->computer);$this->enableCsrfToken();$reference=(new OperacaoRapida())->reference($this->season)[0];
        $this->post('/lancar-porcentagem?area_id='.$area->id,['chave'=>bin2hex(random_bytes(16)),'alteracoes_json'=>json_encode([['atividade_id'=>$this->activities[0],'percentual'=>25,'base'=>0,'revisao'=>0,'referencia'=>$reference]])]);
        $this->assertRedirect();$this->assertSame(25.0,(new Evolucao())->state($this->activities[0],$reference)['percentual']);$this->assertSame(1,Access::table('EvolucaoSemanas')->find()->count());
    }
    public function testAutomaticIdentityWithoutNotebookCanReceiveHoursAndProgress(): void {
        $s=new Activities(new FakeTotvs());$id=$s->identify($this->computer,'nova-os');$a=Access::table('Atividades')->get($id);
        $this->assertNull($a->caderno_id);$this->assertSame($this->season,(int)$a->entressafra_id);$this->assertSame($this->sectors[0],(int)$a->setor_executante_id);
        $this->assertSame($id,$s->identify($this->admin,'nova-os'));
        \App\Test\Support\CompletedOrder::set($id,$this->admin);
        $entry=$this->entries->save($this->computer,['atividade_id'=>$id,'funcionario_chave'=>'pessoa-1','data_servico'=>'22/10/2026','hora_inicial'=>'07:00','hora_final'=>'09:00','descricao'=>'Execução sem caderno']);
        $this->assertGreaterThan(0,$entry);$snapshot=json_decode(Access::table('Apontamentos')->get($entry)->snapshot,true);$this->assertSame('Sem caderno',$snapshot['caderno_nome']);
        $ref=(new OperacaoRapida())->reference($this->season)[0];(new Evolucao())->saveBatch($this->computer,$this->season,$ref,[['atividade_id'=>$id,'percentual'=>100,'revisao'=>0]],bin2hex(random_bytes(16)));
        $this->assertSame(100.0,(new Evolucao())->state($id,$ref)['percentual']);
        $this->assertCount(3,(new Evolucao())->activities($this->season));
        $this->auth($this->admin);foreach(['/atividades','/atividades/view/'.$id,'/apontamentos','/lancar-porcentagem?entressafra_id='.$this->season] as $path){$this->get($path);$this->assertResponseOk();}
    }
    public function testLaterBookClassificationNeverTransfersExistingLinkOrLosesHistory(): void {
        $id=(new Activities(new FakeTotvs()))->identify($this->admin,'nova-os');$book=Access::table('Atividades')->get($this->activities[0])->caderno_id;
        (new Activities(new FakeTotvs()))->classify($this->admin,$id,(int)$book);$this->assertSame((int)$book,(int)Access::table('Atividades')->get($id)->caderno_id);
        $this->expectException(\DomainException::class);(new Activities(new FakeTotvs()))->classify($this->admin,$id,(int)$book);
    }
    public function testAreaWeeksIncludeMondayAndActualPartialEnd(): void {
        $march=\App\Service\Calendarios::weeks('2026-10-19','2027-03-26');$april=\App\Service\Calendarios::weeks('2026-10-19','2027-04-10');
        $this->assertSame('2027-03-26',end($march));$this->assertContains('2027-03-22',$march);$this->assertNotContains('2027-03-29',$march);
        $this->assertSame('2027-04-10',end($april));$this->assertContains('2027-04-05',$april);$this->assertContains('2027-03-29',$april);
    }
    public function testUnknownStartsRemainPendingAndUnknownPhysicalAreaIsNotInferredFromNotebook(): void {
        $area=$this->area('Moenda');$this->schedule($area,'2027-04-10',false);$s=new \App\Service\Calendarios();$r=$s->rows($this->season);
        $this->assertSame([],$s->references($r[0]));$a=(new Evolucao())->activities($this->season)[0];$this->assertNull($s->resolve($a));$this->assertNull($s->planned($a,'2027-04-10'));
    }
    public function testPhysicalDeadlinesAndWeightedPlanRespectEachArea(): void {
        $moenda=$this->area('Moenda');$caldo=$this->area('Tratamento de Caldo');$m=$this->schedule($moenda,'2027-04-10');$c=$this->schedule($caldo,'2027-03-26');
        $s=new \App\Service\Calendarios();$s->classify($this->admin,$this->season,[$this->activities[0]],$moenda);$s->classify($this->admin,$this->season,[$this->activities[1]],$caldo);
        $versions=new Evolucao();
        foreach([$m=>['2027-03-22'=>80,'2027-04-10'=>100],$c=>['2027-03-26'=>100]] as $id=>$values){
            $r=array_values(array_filter((new \App\Service\Calendarios())->rows($this->season),fn($r)=>(int)$r['id']===$id))[0];$content=[];
            foreach((new \App\Service\Calendarios())->references($r) as $d){$content[$d]=0;foreach($values as $ref=>$value)if($ref<=$d)$content[$d]=$value;}
            $versions->version($this->admin,$this->season,'metas','Prazo conferido',$content,true,(int)$id);
        }
        $activities=$versions->activities($this->season);$cs=new \App\Service\Calendarios();$this->assertSame('2027-04-10',$cs->resolve($activities[0])['data_fim']);
        $this->assertSame(80.0,$cs->planned($activities[0],'2027-03-26'));$this->assertSame(100.0,$cs->planned($activities[0],'2027-04-10'));
        $without=$versions->report($this->season,$activities,'2027-03-26');$this->assertNull($without['programado']);$this->assertNull($without['realizado']);
        $versions->version($this->admin,$this->season,'pesos','Pesos de teste',array_fill_keys($this->activities,1),true);
        $report=$versions->report($this->season,$activities,'2027-03-26');$this->assertSame(90.0,$report['programado']);
        $final=$versions->report($this->season,$activities,'2027-04-10');$this->assertSame(100.0,$final['programado']);
        $this->assertSame(0.0,$final['realizado']);
    }
    public function testDeadlineConfigurationAndClassificationRequirePcm(): void {
        $this->auth($this->computer);$this->get('/prazos-entressafra');$this->assertResponseCode(403);
        $this->auth($this->admin);$this->get('/prazos-entressafra?entressafra_id='.$this->season);$this->assertResponseOk();
        $this->expectException(\Cake\Http\Exception\ForbiddenException::class);(new \App\Service\Calendarios())->save($this->computer,$this->season,['data_fim'=>'2026-10-19']);
    }
    public function testCancelledOrUnauthorizedSourceCannotCreateActivity(): void {
        $gateway=$this->createMock(\App\Integration\Totvs\GatewayInterface::class);$row=(new FakeTotvs())->order('os-cancel');$row['situacao']='C';$gateway->method('order')->willReturn($row);
        $this->expectException(\DomainException::class);(new Activities($gateway))->identify($this->computer,'os-cancel');
    }
    public function testSameNotebookAndExecutingTeamCanHaveTwoPhysicalDeadlines(): void {
        $m=$this->area('Moenda');$c=$this->area('Tratamento');$this->schedule($m,'2027-04-10');$this->schedule($c,'2027-03-26');
        $old=Access::table('Atividades')->get($this->activities[0]);$id=(new Activities(new FakeTotvs()))->link($this->admin,(int)$old->caderno_id,'outra-os');
        $s=new \App\Service\Calendarios();$s->classify($this->admin,$this->season,[$old->id],$m);$s->classify($this->admin,$this->season,[$id],$c);
        $first=Access::table('Atividades')->get($old->id);$second=Access::table('Atividades')->get($id);
        $this->assertSame($first->caderno_id,$second->caderno_id);$this->assertSame($first->setor_executante_id,$second->setor_executante_id);
        $this->assertSame('2027-04-10',(new \App\Service\Calendarios())->resolve($first->toArray())['data_fim']);$this->assertSame('2027-03-26',(new \App\Service\Calendarios())->resolve($second->toArray())['data_fim']);
    }
    public function testIdentifyEndpointRequiresCsrfAndReusesSameLocalOrder(): void {
        $this->auth($this->computer);$this->post('/operacao/identificar',['os_chave'=>'nova-os']);$this->assertResponseCode(403);
        $this->enableCsrfToken();$this->post('/operacao/identificar',['os_chave'=>'nova-os','tipo'=>'evolucao']);$this->assertResponseOk();$data=json_decode((string)$this->_response->getBody(),true);$id=$data['order']['id'];
        $this->assertNull(Access::table('Atividades')->get($id)->caderno_id);$this->post('/operacao/identificar',['os_chave'=>'nova-os','tipo'=>'evolucao']);$this->assertResponseOk();$this->assertSame($id,json_decode((string)$this->_response->getBody(),true)['order']['id']);
    }
}

