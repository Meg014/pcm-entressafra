<?php
declare(strict_types=1);
namespace App\Test\TestCase\Service;

use App\Integration\Totvs\{GatewayInterface,Orders};
use App\Service\{Access,Activities,Curva,CurvaSemanal,Evolucao,EvolucaoSemanal,FilaPcm,Insumos,OperacaoRapida,PeriodoContexto,Planejamento,TimeEntries};
use App\Test\Support\FakeTotvs;
use Cake\TestSuite\{TestCase,IntegrationTestTrait};

class CorretivasTest extends TestCase
{
    use IntegrationTestTrait;
    private int $admin;
    private int $season;
    private int $area;
    private GatewayInterface $gateway;
    private array $source;

    protected function setUp(): void
    {
        parent::setUp();PeriodoContexto::set(null);
        foreach(['Auditorias','Insumos','EvolucaoPesos','EvolucaoMetas','EvolucaoRegistros','EvolucaoLotes','EvolucaoVersoes','CalendariosOperacionais','EvolucaoSemanas','VinculosItens','VinculosLotes','Apontamentos','Atividades','PendenciasOrdens','PendenciasConsultas','UsuariosSetores','Usuarios','Cadernos','Setores','AreasFabrica','Entressafras'] as $alias)Access::table($alias)->deleteAll([]);
        $now=gmdate('Y-m-d H:i:s');$t=Access::table('Usuarios');
        $this->admin=(int)$t->saveOrFail(Access::entity($t,['nome'=>'PCM teste','login'=>'corretivas','senha_hash'=>password_hash('Teste-2026!',PASSWORD_DEFAULT),'perfil'=>'ADMIN_PCM','ativo'=>true,'created'=>$now,'modified'=>$now]))->id;
        $t=Access::table('Entressafras');$this->season=(int)$t->saveOrFail($t->newEntity(['nome'=>'Corretivas teste','data_inicio'=>'2026-09-01','data_fim'=>'2027-04-30','status'=>'ativa']))->id;
        $t=Access::table('Setores');$t->saveOrFail($t->newEntity(['nome'=>'Mecânica','ativo'=>true,'area_totvs'=>'A0','filial_totvs'=>'01']));
        $t=Access::table('AreasFabrica');$this->area=(int)$t->saveOrFail(Access::entity($t,['nome'=>'MOENDA','entressafra_id'=>$this->season]))->id;
        $this->source=['chave'=>Orders::key('01','007125'),'numero'=>'007125','filial'=>'01','servico'=>'corretiva não programada entressafra','codigo_servico'=>'CNPEMC','codigo_area'=>'A0','bem'=>'BOMBA 1','descricao_totvs'=>'Manutenção da bomba','situacao'=>'L','termino'=>'N','data_origem'=>'20261001','disponivel'=>true,'correspondencias_servico'=>1];
        $this->gateway=$this->createMock(GatewayInterface::class);
        $this->gateway->method('order')->willReturnCallback(fn($key)=>array_replace($this->source,['chave'=>$key]));
        $this->gateway->method('orders')->willReturnCallback(fn($term,$area,$branch)=>$area===''||$area===$this->source['codigo_area']?[$this->source]:[]);
        $this->gateway->method('employee')->willReturnCallback(fn($key)=>(new FakeTotvs())->employee($key));
        PeriodoContexto::set($this->season);
        $u=Access::actor($this->admin);$this->session(['Auth'=>['id'=>$u->id,'stamp'=>hash('sha256',$u->senha_hash),'last'=>time()],'Periodo'=>['id'=>$this->season,'usuario_id'=>$u->id]]);
        $this->mockService(GatewayInterface::class,fn()=>$this->gateway);
    }

    protected function tearDown(): void {PeriodoContexto::set(null);parent::tearDown();}
    private function identify(): int {return (new Activities($this->gateway))->identify($this->admin,$this->source['chave']);}
    private function hours(int $id): int
    {
        return (new TimeEntries($this->gateway))->save($this->admin,['atividade_id'=>$id,'funcionario_chave'=>'pessoa-1','inicio'=>'2026-10-21T07:00','fim'=>'2026-10-21T09:00','descricao'=>'Serviço corretivo']);
    }
    private function supplies(int $id): array
    {
        return (new Insumos($this->gateway))->saveBatch($this->admin,['atividade_id'=>$id,'insumos'=>[['funcionario_chave'=>'pessoa-1','data'=>'21/10/2026','material'=>'Rolamento','quantidade'=>'2,5']]]);
    }

    public function testRecognitionUsesOfficialNameOrExactCodeOnly(): void
    {
        $this->assertTrue(Planejamento::corretiva(['servico'=>'  Corretiva NÃO programada ENTRESSAFRA  ']));
        foreach(Planejamento::CODIGOS as $code)$this->assertTrue(Planejamento::corretiva(['codigo_servico'=>$code]));
        $this->assertFalse(Planejamento::corretiva(['codigo_servico'=>'CNPXYZ','servico'=>'ENTRESSAFRA','descricao_totvs'=>'CORRETIVA NAO PROGRAMADA ENTRESSAFRA']));
        $this->assertFalse(Planejamento::corretiva(['servico'=>'ENTRESSAFRA PROGRAMADA']));
        $this->assertTrue(Orders::available(array_replace($this->source,['servico'=>'Outro nome oficial'])));
    }

    public function testLookupAndRegistrationWithoutNotebookAreIdempotentAcrossUsers(): void
    {
        $result=(new OperacaoRapida($this->gateway))->search(Access::actor($this->admin),'007125',false);
        $this->assertSame('007125',$result['results'][0]['numero']);
        $this->assertSame(Planejamento::CORRETIVA,$result['results'][0]['tipo_planejamento']);
        $id=$this->identify();$a=Access::table('Atividades')->get($id);
        $this->assertNull($a->caderno_id);$this->assertSame(Planejamento::CORRETIVA,$a->tipo_planejamento);
        $this->assertSame('007125',$a->os_numero);$this->assertSame('CNPEMC',json_decode($a->snapshot_totvs,true)['codigo_servico']);
        $t=Access::table('Usuarios');$other=$t->saveOrFail(Access::entity($t,['nome'=>'Outro PCM','login'=>'outro-pcm','senha_hash'=>'hash','perfil'=>'PCM','ativo'=>true,'created'=>gmdate('Y-m-d H:i:s'),'modified'=>gmdate('Y-m-d H:i:s')]));
        $this->assertSame($id,(new Activities($this->gateway))->identify((int)$other->id,$this->source['chave']));
        $this->assertSame(1,Access::table('Atividades')->find()->count());
    }

    public function testHoursSuppliesQueueAndDirectSearchIgnoringPercentageFilter(): void
    {
        $id=$this->identify();$this->assertGreaterThan(0,$this->hours($id));$this->assertCount(1,$this->supplies($id));
        $queue=new FilaPcm();$u=Access::actor($this->admin);$rows=$queue->queue($u);
        $this->assertCount(1,$rows);$this->assertNull($rows[0]['percentual_atual']);
        $this->assertSame(120,(int)$rows[0]['minutos']);$this->assertSame(1,(int)$rows[0]['insumos_pendentes']);
        $this->assertSame([],$queue->queue($u,null,['evolucao'=>'zero']));
        $this->assertCount(1,$queue->queuePage($u,1,['os_numero'=>'007125','evolucao'=>'concluidas'])['rows']);
        $this->get('/fila-pcm?os_numero=007125&evolucao=concluidas');$this->assertResponseOk();$this->assertResponseContains('Corretiva não programada');$this->assertResponseContains('—');
        $this->assertGreaterThan(0,Access::table('Auditorias')->find()->where(['acao'=>'insumo_criacao'])->count());
    }

    public function testUnknownSectorRemainsAvailableToPcmForEntries(): void
    {
        $this->source['codigo_area']='NOMAP';$id=$this->identify();
        $this->assertNull(Access::table('Atividades')->get($id)->setor_executante_id);
        $this->assertCount(1,(new OperacaoRapida($this->gateway))->search(Access::actor($this->admin),'007125',false)['results']);
        $this->assertGreaterThan(0,$this->hours($id));$this->assertCount(1,$this->supplies($id));
    }

    public function testOneHundredCorrectivesDoNotChangePlannedCurveOrWeeklyRoster(): void
    {
        $this->source['servico']='ENTRESSAFRA PROGRAMADA';$this->source['codigo_servico']='PLAN';$this->source['numero']='000001';$this->source['chave']=Orders::key('01','000001');
        $planned=$this->identify();$t=Access::table('Atividades');$t->updateAll(['area_curva_id'=>$this->area],['id'=>$planned]);
        $e=new Evolucao();$ref=$e->calendar($this->season)[0]['referencia'];$e->saveBatch($this->admin,$this->season,$ref,[['atividade_id'=>$planned,'percentual'=>60,'revisao'=>0]],bin2hex(random_bytes(16)));
        $before=(new Curva())->report($this->season);$weeklyBefore=(new CurvaSemanal())->report($this->season,$this->area);
        $this->source['servico']='CORRETIVA NAO PROGRAMADA ENTRESSAFRA';$this->source['codigo_servico']='CNPEMC';
        for($i=1;$i<=100;$i++){$this->source['numero']=sprintf('%06d',7000+$i);$this->source['chave']=Orders::key('01',$this->source['numero']);$id=$this->identify();$t->updateAll(['area_curva_id'=>$this->area],['id'=>$id]);}
        $after=(new Curva())->report($this->season);
        $this->assertSame($before['metrics'],$after['metrics']);$this->assertSame($before['points'],$after['points']);
        $this->assertSame($weeklyBefore,(new CurvaSemanal())->report($this->season,$this->area));
        $this->assertSame(101,$t->find()->count());$this->assertSame(Planejamento::PROGRAMADA,$t->get($planned)->tipo_planejamento);
        $this->assertCount(1,$e->activities($this->season));
        $grade=(new EvolucaoSemanal())->page(Access::actor($this->admin),$this->season,$this->area,'',1);$this->assertSame(1,$grade['total']);
    }

    public function testCorrectiveIsHiddenFromPercentageLookupAndRejectsPercentageWrites(): void
    {
        $id=$this->identify();$this->assertSame([],(new OperacaoRapida($this->gateway))->search(Access::actor($this->admin),'007125',true)['results']);
        $ref=(new Evolucao())->calendar($this->season)[0]['referencia'];
        $this->expectException(\DomainException::class);
        (new Evolucao())->saveBatch($this->admin,$this->season,$ref,[['atividade_id'=>$id,'percentual'=>50,'revisao'=>0]],bin2hex(random_bytes(16)));
    }

    public function testTimeConflictsStillPreventOverlappingCorrectiveEntries(): void
    {
        $id=$this->identify();$this->hours($id);
        $this->expectException(\App\Service\ConflictException::class);$this->hours($id);
    }

    public function testCorrectiveCannotBeLinkedToPlannedNotebook(): void
    {
        $id=$this->identify();$sector=Access::table('Atividades')->get($id)->setor_executante_id;
        $t=Access::table('Cadernos');$book=$t->saveOrFail($t->newEntity(['nome'=>'Planejado','entressafra_id'=>$this->season,'setor_id'=>$sector,'area_curva_id'=>$this->area,'ativo'=>true]));
        $this->expectException(\DomainException::class);
        (new Activities($this->gateway))->classify($this->admin,$id,(int)$book->id);
    }

    public function testUnmappedCorrectiveDoesNotExpandResponsibleSectorPermissions(): void
    {
        $this->source['codigo_area']='NOMAP';$id=$this->identify();$t=Access::table('Usuarios');
        $user=$t->saveOrFail(Access::entity($t,['nome'=>'Responsável','login'=>'restrito','senha_hash'=>'hash','perfil'=>'RESPONSAVEL_SETOR','ativo'=>true,'created'=>gmdate('Y-m-d H:i:s'),'modified'=>gmdate('Y-m-d H:i:s')]));
        $this->expectException(\Cake\Http\Exception\ForbiddenException::class);
        (new OperacaoRapida())->activity($user,$id);
    }

    public function testDatabaseIdentityConstraintPreventsDuplicateOrder(): void
    {
        $id=$this->identify();$t=Access::table('Atividades');$data=$t->get($id)->toArray();unset($data['id']);
        $this->expectException(\Cake\Database\Exception\QueryException::class);
        $t->getConnection()->insert('atividades',$data);
    }
}
