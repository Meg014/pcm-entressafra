<?php
declare(strict_types=1);
namespace App\Test\TestCase\Controller;

use App\Service\{Access,Activities,Insumos,InsumosConsulta,InsumosEdicao,FilaPcm,PeriodoContexto};
use App\Test\Support\FakeTotvs;
use Cake\TestSuite\{TestCase,IntegrationTestTrait};

class InsumosMelhoriasTest extends TestCase
{
    use IntegrationTestTrait;
    private int $admin; private int $pcm; private int $common; private int $season; private int $activity;

    protected function setUp(): void
    {
        parent::setUp(); PeriodoContexto::set(null);
        $db=Access::table('Atividades')->getConnection();
        if ($db->getDriver() instanceof \Cake\Database\Driver\Postgres) {
            $schema=$db->execute('SELECT current_schema()')->fetch()[0];
            if ($schema!==getenv('PCM_CANCEL_API_TEST_SCHEMA') || !preg_match('/^pcm_cancel_api_test_[a-f0-9]{16}$/D',$schema)) throw new \RuntimeException('Schema descartável obrigatório.');
            $names=$db->execute("SELECT tablename FROM pg_tables WHERE schemaname=? AND tablename<>'phinxlog'",[$schema])->fetchAll('assoc');
            $quoted=array_map(static fn($r)=>'"'.$schema.'"."'.$r['tablename'].'"',$names);
            if($quoted)$db->execute('TRUNCATE TABLE '.implode(',',$quoted).' RESTART IDENTITY CASCADE');
        } else {
            $db->execute('PRAGMA foreign_keys=OFF');
            $names=$db->execute("SELECT name FROM sqlite_master WHERE type='table' AND name NOT IN ('phinxlog','sqlite_sequence')")->fetchAll('assoc');
            foreach($names as $row)$db->execute('DELETE FROM "'.$row['name'].'"');
            $db->execute('PRAGMA foreign_keys=ON');
        }
        $now=gmdate('Y-m-d H:i:s');$t=Access::table('Usuarios');
        foreach(['admin'=>'ADMIN_PCM','pcm'=>'PCM','common'=>'RESPONSAVEL_SETOR'] as $key=>$role)$this->$key=(int)$t->saveOrFail(Access::entity($t,['nome'=>$key,'login'=>'insumos-'.$key,'senha_hash'=>password_hash('Teste-2026!',PASSWORD_DEFAULT),'perfil'=>$role,'ativo'=>true,'computador_operacional'=>$key==='common','created'=>$now,'modified'=>$now]))->id;
        $t=Access::table('Entressafras');$this->season=(int)$t->saveOrFail($t->newEntity(['nome'=>'Teste insumos','data_inicio'=>'2026-09-01','data_fim'=>'2027-04-30','status'=>'ativa']))->id;
        $t=Access::table('Setores');$sector=$t->saveOrFail($t->newEntity(['nome'=>'Mecânica','ativo'=>true,'area_totvs'=>'A0','filial_totvs'=>'01']));
        $t=Access::table('Cadernos');$book=$t->saveOrFail($t->newEntity(['nome'=>'Caderno','entressafra_id'=>$this->season,'setor_id'=>$sector->id,'ativo'=>true]));
        $this->activity=(new Activities(new FakeTotvs()))->link($this->admin,(int)$book->id,'os-0');
        $this->mockService(\App\Integration\Totvs\GatewayInterface::class,fn()=>new FakeTotvs());
        PeriodoContexto::set($this->season);$this->login($this->admin);$this->enableCsrfToken();
    }

    protected function tearDown(): void {PeriodoContexto::set(null);parent::tearDown();}
    private function login(int $actor): void
    {
        $user=Access::actor($actor);$this->session(['Auth'=>['id'=>$actor,'stamp'=>hash('sha256',$user->senha_hash),'last'=>time()],'Periodo'=>['id'=>$this->season,'usuario_id'=>$actor]]);
    }
    private function line(array $overrides=[]): array {return $overrides+['funcionario_chave'=>'pessoa-1','data'=>'21/10/2026','material'=>'Válvula de pressão','quantidade'=>'2,5','unidade'=>'und'];}
    private function save(array $rows=[]): array {return (new Insumos(new FakeTotvs()))->saveBatch($this->admin,['atividade_id'=>$this->activity,'insumos'=>$rows?:[$this->line()]]);}
    private function changes(int $id,array $overrides=[]): array
    {
        $r=Access::table('Insumos')->get($id);
        return $overrides+['material'=>$r->material,'quantidade'=>(string)$r->quantidade,'unidade'=>'kg','motivo'=>'Complementação da unidade de medida','revisao_pcm'=>(string)$r->revisao_pcm];
    }
    private function filtered(array $filters): array {return InsumosConsulta::query(Access::actor($this->admin),InsumosConsulta::filters($filters))->all()->extract('id')->toList();}

    public function testVacationEmployeeCanBeSelectedSavedAndEdited(): void
    {
        $source = new \App\Integration\Totvs\Employees(fn() => [[
            'RA_MAT'=>'000123', 'RA_NOME'=>'Pessoa em férias', 'RA_CODFUNC'=>'01', 'RA_SITFOLH'=>'F', 'elegiveis'=>1,
        ]]);
        $person = $source->search('000123')[0];
        $gateway = $this->createMock(\App\Integration\Totvs\GatewayInterface::class);
        $gateway->method('order')->willReturnCallback([new FakeTotvs(), 'order']);
        $gateway->method('employee')->willReturnCallback([$source, 'find']);
        [$id] = (new Insumos($gateway))->saveBatch($this->admin, [
            'atividade_id'=>$this->activity, 'insumos'=>[$this->line(['funcionario_chave'=>$person['chave']])],
        ]);
        $before = Access::table('Insumos')->get($id);
        $this->assertSame('F', json_decode($before->snapshot, true)['funcionario']['situacao_codigo']);
        (new InsumosEdicao())->edit($this->admin, $id, $this->changes($id));
        $after = Access::table('Insumos')->get($id);
        $this->assertSame('kg', $after->unidade);
        $this->assertSame('000123', $after->funcionario_matricula);
        $this->assertSame($before->snapshot, $after->snapshot);
    }

    public function testDateOrderMaterialCombinedAndSeasonFilters(): void
    {
        [$a,$b,$c]=$this->save([$this->line(),$this->line(['data'=>'22/10/2026','material'=>'Óleo hidráulico']),$this->line(['data'=>'23/10/2026'])]);
        Access::table('Atividades')->updateAll(['os_numero'=>'005300'],['id'=>$this->activity]);
        $this->assertSame([$b],$this->filtered(['data_inicial'=>'2026-10-22','data_final'=>'2026-10-22']));
        $this->assertSame([$c,$b,$a],$this->filtered(['os'=>'005300']));
        $this->assertSame([],$this->filtered(['os'=>'5300']));
        $this->assertSame([$b],$this->filtered(['material'=>'LEO']));
        $this->assertSame([$c,$a],$this->filtered(['material'=>'Válvula']));
        $this->assertSame([$a],$this->filtered(['data_inicial'=>'2026-10-21','data_final'=>'2026-10-22','os'=>'005300','material'=>'PRESSÃO']));
        if(Access::table('Insumos')->getConnection()->getDriver() instanceof \Cake\Database\Driver\Postgres)$this->assertSame([$c,$a],$this->filtered(['material'=>'valvula']));
        $this->assertSame([],$this->filtered(['material'=>'%']));
        $t=Access::table('Entressafras');$other=$t->saveOrFail($t->newEntity(['nome'=>'Outra','data_inicio'=>'2025-01-01','data_fim'=>'2025-12-31','status'=>'ativa']));
        PeriodoContexto::set((int)$other->id);$this->assertSame([],$this->filtered([]));
    }

    public function testPaginationKeepsFiltersAndSearchDoesNotWrite(): void
    {
        $this->save(array_fill(0,51,$this->line()));Access::table('Atividades')->updateAll(['os_numero'=>'005300'],['id'=>$this->activity]);
        $db=Access::table('Insumos')->getConnection();$before=$db->execute('SELECT * FROM insumos ORDER BY id')->fetchAll('assoc');
        $this->get('/insumos?data_inicial=2026-10-01&data_final=2026-10-31&os=005300&material=press');$this->assertResponseOk();
        $dom=new \DOMDocument();@$dom->loadHTML((string)$this->_response->getBody());$xp=new \DOMXPath($dom);
        $links=$xp->query('//ul[contains(@class,"pagination")]//a[contains(@href,"page=2")]');$this->assertGreaterThan(0,$links->length);
        $url=$links->item(0)->getAttribute('href');
        foreach(['data_inicial=2026-10-01','data_final=2026-10-31','os=005300','material=press'] as $part)$this->assertStringContainsString($part,$url);
        $this->get($url);$this->assertResponseOk();$this->assertCount(1,$this->viewVariable('rows'));
        $this->assertSame($before,$db->execute('SELECT * FROM insumos ORDER BY id')->fetchAll('assoc'));
    }

    public function testRequiredUnitsAndAtomicMultipleRows(): void
    {
        foreach([null,'','litro','KG'] as $unit){
            try{$this->save([$this->line(),$this->line(['unidade'=>$unit])]);$this->fail('Unidade inválida aceita');}catch(\App\Service\BatchEntryException $e){$this->assertArrayHasKey(1,$e->errors);}
            $this->assertSame(0,Access::table('Insumos')->find()->count());
        }
        $ids=$this->save(array_map(fn($unit)=>$this->line(['unidade'=>$unit]),['m','kg','und','PSI']));$this->assertCount(4,$ids);
        $this->assertSame(['m','kg','und','PSI'],Access::table('Insumos')->find()->orderBy(['id'=>'ASC'])->all()->extract('unidade')->toList());
        $this->get('/insumos/add');$this->assertResponseOk();$this->assertResponseContains('name="insumos[0][unidade]"');$this->assertResponseContains('PSI');
        if(getenv('INSUMOS_PREVIEW')==='1')file_put_contents(TMP.'insumos-unidade-preview.html',(string)$this->_response->getBody());
    }

    public function testLegacyDisplayAdministrativeUnitEditAndAudit(): void
    {
        [$id,$other]=$this->save([$this->line(),$this->line()]);$t=Access::table('Insumos');$t->updateAll(['unidade'=>null],['id'=>$id]);$before=$t->get($id)->toArray();$untouched=$t->get($other)->toArray();
        $this->get('/insumos');$this->assertResponseOk();$this->assertResponseContains('Não informada');$this->assertResponseContains('Editar');
        if(getenv('INSUMOS_PREVIEW')==='1')file_put_contents(TMP.'insumos-edicao-preview.html',(string)$this->_response->getBody());
        $this->post('/insumos/edit/'.$id,$this->changes($id));$this->assertResponseCode(302);
        $row=$t->get($id);$this->assertSame('kg',$row->unidade);$this->assertSame(1,(int)$row->revisao_pcm);
        foreach(['atividade_id','funcionario_nome','funcionario_matricula','data','created','snapshot','material','quantidade'] as $key)$this->assertEquals($before[$key],$row->$key);
        $this->assertEquals($untouched,$t->get($other)->toArray());
        $audit=Access::table('Auditorias')->find()->where(['acao'=>'insumo_edicao','insumo_id'=>$id])->firstOrFail();
        $this->assertNull(json_decode($audit->antes,true)['unidade']);$this->assertSame('kg',json_decode($audit->depois,true)['unidade']);$this->assertSame($this->admin,(int)$audit->usuario_id);$this->assertNotNull($audit->created);
        $this->get('/fila-pcm/os/'.$this->activity);$this->assertResponseOk();$this->assertResponseContains('kg');$this->assertResponseContains('Edição administrativa de insumo');
    }

    public function testMaterialQuantityReasonConcurrentRevisionAndIdentity(): void
    {
        $id=$this->save()[0];$service=new InsumosEdicao();$changes=$this->changes($id,['material'=>'Correia','quantidade'=>'10,125','motivo'=>'Correção após conferência física','atividade_id'=>999,'data'=>'2020-01-01','funcionario_nome'=>'Outra pessoa']);
        $changes['revisao_pcm']=(string)Access::table('Insumos')->get($id)->revisao_pcm;
        $empty=$changes;$empty['motivo']='';
        $before=Access::table('Insumos')->get($id)->toArray();
        try{$service->edit($this->admin,$id,$empty);$this->fail('Sem justificativa');}catch(\DomainException){$this->assertEquals($before,Access::table('Insumos')->get($id)->toArray());}
        $service->edit($this->admin,$id,$changes);$r=Access::table('Insumos')->get($id);$this->assertSame('Correia',$r->material);$this->assertEquals(10.125,(float)$r->quantidade);$this->assertSame($this->activity,(int)$r->atividade_id);$this->assertSame('2026-10-21',$r->data->format('Y-m-d'));
        $audit=Access::table('Auditorias')->find()->where(['acao'=>'insumo_edicao','insumo_id'=>$id])->orderBy(['id'=>'DESC'])->firstOrFail();$this->assertSame('Correia',json_decode($audit->depois,true)['material']);
        $this->assertSame($before['material'],json_decode($audit->antes,true)['material']);
        $this->assertEquals(2.5,(float)json_decode($audit->antes,true)['quantidade']);
        $this->assertEquals(10.125,(float)json_decode($audit->depois,true)['quantidade']);
        $changes['material']='Sobrescrita';
        try{$service->edit($this->admin,$id,$changes);$this->fail('Revisão obsoleta');}catch(\DomainException $e){$this->assertStringContainsString('outra pessoa',$e->getMessage());}
    }

    public function testCommonUsersCannotEditOnBackendOrUi(): void
    {
        $id=$this->save()[0];$before=Access::table('Insumos')->get($id)->toArray();
        foreach([$this->pcm,$this->common] as $actor){
            try{(new InsumosEdicao())->edit($actor,$id,$this->changes($id));$this->fail('Sem permissão');}catch(\Cake\Http\Exception\ForbiddenException){$this->assertTrue(true);}
            $this->login($actor);$this->get('/insumos');$this->assertResponseOk();$this->assertResponseNotContains('data-edit-supply');
            $this->post('/insumos/edit/'.$id,$this->changes($id));$this->assertResponseCode(403);
        }
        $this->assertEquals($before,Access::table('Insumos')->get($id)->toArray());
    }

    public function testConfirmedSupplyRequiresAwarenessAndReturnsToPcmQueue(): void
    {
        $id=$this->save()[0];(new FilaPcm())->confirmSupplies($this->pcm,$this->activity,[['id'=>$id,'revisao'=>0]],'lancado','',true);
        $before=Access::table('Insumos')->get($id)->toArray();$changes=$this->changes($id);
        try{(new InsumosEdicao())->edit($this->admin,$id,$changes);$this->fail('Sem ciência');}catch(\DomainException){$this->assertEquals($before,Access::table('Insumos')->get($id)->toArray());}
        (new InsumosEdicao())->edit($this->admin,$id,$changes+['ciencia_totvs'=>'1']);$r=Access::table('Insumos')->get($id);
        $this->assertSame('pendente',$r->controle_pcm);$this->assertNull($r->confirmado_por);$this->assertSame(2,(int)$r->revisao_pcm);
        $audit=Access::table('Auditorias')->find()->where(['insumo_id'=>$id,'acao'=>'insumo_reabertura_totvs'])->firstOrFail();$old=json_decode($audit->antes,true);$this->assertSame('lancado',$old['controle_pcm']);$this->assertNotNull($old['confirmado_em']);$this->assertSame($this->pcm,(int)$old['confirmado_por']);
        $this->assertSame(1,(new FilaPcm())->queueCount(Access::actor($this->admin)));
        try{(new FilaPcm())->confirmSupplies($this->pcm,$this->activity,[['id'=>$id,'revisao'=>1]],'lancado','',true);$this->fail('Confirmação obsoleta');}catch(\DomainException){$this->assertTrue(true);}
    }

    public function testCancelledAndClosedSeasonCannotBeEditedAndAuditFailureRollsBack(): void
    {
        $id=$this->save()[0];$t=Access::table('Insumos');$changes=$this->changes($id);$t->updateAll(['cancelado'=>true],['id'=>$id]);
        try{(new InsumosEdicao())->edit($this->admin,$id,$changes);$this->fail();}catch(\DomainException){$this->assertNull($t->get($id)->modified);}
        $t->updateAll(['cancelado'=>false],['id'=>$id]);$before=$t->get($id)->toArray();
        $callback=static function(){throw new \RuntimeException('Falha de auditoria simulada');};$audits=Access::table('Auditorias');$audits->getEventManager()->on('Model.beforeSave',$callback);
        try{(new InsumosEdicao())->edit($this->admin,$id,$changes);$this->fail();}catch(\RuntimeException){$this->assertEquals($before,$t->get($id)->toArray());}finally{$audits->getEventManager()->off('Model.beforeSave',$callback);}
        Access::table('Entressafras')->updateAll(['status'=>'encerrada'],['id'=>$this->season]);
        $this->expectException(\Cake\Http\Exception\ForbiddenException::class);(new InsumosEdicao())->edit($this->admin,$id,$changes);
    }
}
