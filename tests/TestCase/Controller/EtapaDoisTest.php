<?php
declare(strict_types=1);
namespace App\Test\TestCase\Controller;

use App\Service\Access;
use App\Service\Activities;
use App\Service\TimeEntries;
use App\Service\ConflictException;
use App\Service\Users;
use App\Test\Support\FakeTotvs;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class EtapaDoisTest extends TestCase
{
    use IntegrationTestTrait;
    private int $admin;
    private int $pcm;
    private int $responsavel;
    private array $sectors;
    private array $notebooks;
    private array $activities;
    private TimeEntries $entries;

    protected function setUp(): void
    {
        parent::setUp();
        Access::table('Auditorias')->deleteAll(['insumo_id IS NOT'=>null]);
        Access::table('Insumos')->deleteAll([]);
        foreach(['EvolucaoPesos','EvolucaoMetas','EvolucaoRegistros','EvolucaoLotes','EvolucaoVersoes','CalendariosOperacionais','EvolucaoSemanas','VinculosItens','VinculosLotes','Auditorias','Apontamentos','Atividades','UsuariosSetores','Usuarios','Cadernos','Setores','AreasFabrica','Entressafras'] as $alias) { Access::table($alias)->deleteAll([]); }
        $now = gmdate('Y-m-d H:i:s');
        $users = Access::table('Usuarios');
        $hash = password_hash('Teste-Restrito-2026', PASSWORD_DEFAULT);
        $this->admin = (int)$users->saveOrFail(Access::entity($users, ['nome' => 'Admin teste','login' => 'admin-teste','senha_hash' => $hash,'perfil' => 'ADMIN_PCM','ativo' => true,'created' => $now,'modified' => $now]))->id;
        $seasonTable = Access::table('Entressafras');
        $season = $seasonTable->saveOrFail($seasonTable->newEntity(['nome'=>'2026/2027','data_inicio'=>'2026-10-01','data_fim'=>'2027-04-30','status'=>'ativa']));
        $this->sectors = []; $this->notebooks = []; $this->activities = [];
        foreach (['Mecânica', 'Elétrica'] as $index => $name) {
            $table = Access::table('Setores'); $sector = $table->saveOrFail($table->newEntity(['nome'=>$name,'ativo'=>true,'area_totvs'=>'A'.$index,'filial_totvs'=>'01'])); $this->sectors[] = (int)$sector->id;
            $table = Access::table('Cadernos'); $notebook = $table->saveOrFail($table->newEntity(['nome'=>'Caderno '.$name,'entressafra_id'=>$season->id,'setor_id'=>$sector->id,'ativo'=>true])); $this->notebooks[] = (int)$notebook->id;
            $this->activities[] = (new Activities(new FakeTotvs()))->link($this->admin, (int)$notebook->id, 'os-' . $index);
        }
        $service = new Users();
        $this->pcm = $service->save($this->admin, ['nome'=>'PCM teste','login'=>'pcm-teste','senha'=>'Teste-Restrito-2026','perfil'=>'PCM','ativo'=>'1']);
        $this->responsavel = $service->save($this->admin, ['nome'=>'Responsável teste','login'=>'resp-teste','senha'=>'Teste-Restrito-2026','perfil'=>'RESPONSAVEL_SETOR','ativo'=>'1','setores'=>[$this->sectors[0]]]);
        foreach($this->activities as $activityId)\App\Test\Support\CompletedOrder::set($activityId,$this->admin);
        $this->entries = new TimeEntries(new FakeTotvs());
        // HTTP searches use a source double; production always resolves the real gateway.
        $this->mockService(\App\Integration\Totvs\GatewayInterface::class,function() {
            $gateway=$this->createMock(\App\Integration\Totvs\GatewayInterface::class);
            $gateway->method('orders')->willReturnCallback(function($term,$area,$branch) {
                $rows=[];
                foreach(Access::table('Atividades')->find()->all() as $activity) {
                    $row=(new FakeTotvs())->order($activity->os_chave);
                    $row['codigo_area']=Access::table('Setores')->get($activity->setor_executante_id)->area_totvs;
                    $row['numero']=$activity->os_numero;
                    if ($activity->disponivel_totvs===true && $row['codigo_area']===$area && $row['filial']===$branch && (mb_stripos($row['numero'],$term)!==false || mb_stripos($row['servico'],$term)!==false)) { $rows[]=$row; }
                }
                return $rows;
            });
            return $gateway;
        });
        $this->authenticate($this->admin);
    }
    private function authenticate(int $id): void
    {
        $user = Access::actor($id);
        $this->session(['Periodo'=>['id'=>(int)Access::table('Cadernos')->get($this->notebooks[0])->entressafra_id,'usuario_id'=>$id],'Auth'=>['id'=>$id,'stamp'=>hash('sha256',$user->senha_hash),'last'=>time()]]);
    }
    private function input(string $start = '2026-10-21T07:00', string $end = '2026-10-21T11:00', int $activity = 0): array
    { return ['atividade_id'=>$this->activities[$activity],'funcionario_chave'=>'pessoa-1','inicio'=>$start,'fim'=>$end,'descricao'=>'Serviço realizado']; }

    public function testQueriesAndDuplicateLink(): void
    {
        $mock = $this->createMock(\App\Integration\Totvs\GatewayInterface::class);
        $mock->expects($this->once())->method('order')->with('nova-os')->willReturn((new FakeTotvs())->order('nova-os'));
        $id = (new Activities($mock))->link($this->responsavel,$this->notebooks[0],'nova-os');
        $this->assertGreaterThan(0,$id);
        $this->assertCount(1,(new FakeTotvs())->orders('nova'));
        $this->assertCount(1,(new FakeTotvs())->employees('0001'));
        $this->expectException(\DomainException::class);
        (new Activities(new FakeTotvs()))->link($this->admin,$this->notebooks[1],'nova-os');
    }

    public function testServiceCatalogHttpWritesAndHistoricalText(): void
    {
        $this->enableCsrfToken();
        $this->get('/servicos-sugeridos/add');$this->assertResponseOk();
        $this->post('/servicos-sugeridos/add',['descricao'=>'TORQUE ESPECIAL HTTP','ativo'=>'1']);$this->assertRedirect('/servicos-sugeridos');
        $catalog=Access::table('ServicosSugeridos');$suggestion=$catalog->find()->where(['descricao'=>'TORQUE ESPECIAL HTTP'])->firstOrFail();
        $input=$this->input();$input['descricao']=$suggestion->descricao;
        $entry=$this->entries->save($this->responsavel,$input);
        $this->get('/servicos-sugeridos/edit/'.$suggestion->id);$this->assertResponseOk();
        $this->post('/servicos-sugeridos/edit/'.$suggestion->id,['descricao'=>'TORQUE ALTERADO HTTP','ativo'=>'1']);$this->assertRedirect('/servicos-sugeridos');
        $this->post('/servicos-sugeridos/toggle/'.$suggestion->id,['ativo'=>'0']);$this->assertRedirect('/servicos-sugeridos');
        $this->assertFalse($catalog->get($suggestion->id)->ativo);
        $this->assertSame('TORQUE ESPECIAL HTTP',Access::table('Apontamentos')->get($entry)->descricao);
        $this->authenticate($this->pcm);
        $this->post('/servicos-sugeridos/edit/'.$suggestion->id,['descricao'=>'INVASÃO','ativo'=>'1']);$this->assertResponseCode(403);
        $this->authenticate($this->responsavel);
        $this->post('/servicos-sugeridos/toggle/'.$suggestion->id,['ativo'=>'1']);$this->assertResponseCode(403);
        $this->assertSame('TORQUE ALTERADO HTTP',$catalog->get($suggestion->id)->descricao);
    }
    public function testMissingServiceCatalogNeverBlocksSavingFreeText(): void
    {
        $db=Access::table('ServicosSugeridos')->getConnection();
        $db->execute('ALTER TABLE servicos_sugeridos RENAME TO servicos_sugeridos_indisponivel');
        try{
            $this->get('/apontamentos/servicos?q=des');$this->assertResponseCode(503);
            $this->assertSame([],json_decode((string)$this->_response->getBody(),true)['results']);
            $input=$this->input();$input['descricao']='Descrição personalizada sem catálogo instalado';
            $entry=$this->entries->save($this->responsavel,$input);
            $this->assertSame($input['descricao'],Access::table('Apontamentos')->get($entry)->descricao);
        }finally{$db->execute('ALTER TABLE servicos_sugeridos_indisponivel RENAME TO servicos_sugeridos');}
    }
    public function testServiceCatalogSearchAdministrationAndFreeText(): void
    {
        $table=Access::table('ServicosSugeridos');$table->deleteAll([]);
        $service=new \App\Service\ServiceSuggestions();
        $id=$service->save($this->admin,['descricao'=>'LUBRIFICAÇÃO','ativo'=>'1']);
        $this->assertSame('LUBRIFICAÇÃO',$service->search('LuBrI')[0]['descricao']);
        $this->assertCount(1,$service->search("lubrificac\u{0327}a\u{0303}o"));
        $this->assertSame([],$service->search('l'));
        $this->assertSame([],$service->search('lu%'));
        for($n=0;$n<15;$n++)$service->save($this->admin,['descricao'=>'TESTE '.$n]);
        $this->assertCount(10,$service->search('teste'));
        try{$service->save($this->admin,['descricao'=>'  lubrificacao  ']);$this->fail('Duplicata aceita');}catch(\DomainException $e){$this->assertStringContainsString('equivalente',$e->getMessage());}
        foreach([$this->pcm,$this->responsavel] as $actor){
            try{$service->save($actor,['descricao'=>'Proibido']);$this->fail('Perfil indevido');}catch(\Cake\Http\Exception\ForbiddenException){$this->assertTrue(true);}
        }
        $input=$this->input();$input['descricao']="  Descrição livre\n".str_repeat('ROLAMENTO ',90)."  ";
        $entry=$this->entries->save($this->responsavel,$input);
        $this->assertSame($input['descricao'],Access::table('Apontamentos')->get($entry)->descricao);
        $this->assertSame(16,$table->find()->count());
        $service->save($this->admin,['descricao'=>'LUBRIFICAÇÃO GERAL','ativo'=>'1'],$id);
        $service->setActive($this->admin,$id,'0');
        $this->assertSame([],$service->search('lubri'));
        $this->assertSame($input['descricao'],Access::table('Apontamentos')->get($entry)->descricao);
        $this->assertGreaterThan(0,Access::table('Auditorias')->find()->where(['acao'=>'servico_sugestao_situacao'])->count());
        $this->get('/servicos-sugeridos');$this->assertResponseOk();$this->assertResponseContains('LUBRIFICAÇÃO GERAL');
        $this->get('/servicos-sugeridos?q=TE%25');$this->assertResponseOk();$this->assertResponseNotContains('TESTE 0');
        $this->authenticate($this->pcm);$this->get('/servicos-sugeridos');$this->assertResponseCode(403);
        $this->authenticate($this->responsavel);$this->get('/apontamentos/servicos?q=teste');$this->assertResponseOk();
        $this->assertCount(10,json_decode((string)$this->_response->getBody(),true)['results']);
        $this->get('/apontamentos/servicos?q=a');$this->assertResponseCode(422);
        $this->get('/apontamentos/servicos?q%5B%5D=teste');$this->assertResponseCode(422);
        $this->get('/apontamentos/servicos?q='.str_repeat('a',101));$this->assertResponseCode(422);
        Access::table('Usuarios')->updateAll(['computador_operacional'=>true],['id'=>$this->responsavel]);
        $this->authenticate($this->responsavel);$this->get('/apontamentos/servicos?q=teste');$this->assertResponseOk();
        $this->get('/servicos-sugeridos');$this->assertResponseCode(403);
    }
    public function testEmployeeIsConfirmedByServerAndHoursAreCalculated(): void
    {
        $mock = $this->createMock(\App\Integration\Totvs\GatewayInterface::class);
        $mock->expects($this->once())->method('employee')->with('pessoa-1')->willReturn((new FakeTotvs())->employee('pessoa-1'));
        $id = (new TimeEntries($mock))->save($this->responsavel,$this->input()+['minutos'=>99999,'funcionario_nome'=>'Nome adulterado']);
        $row = Access::table('Apontamentos')->get($id);
        $this->assertSame(240,$row->minutos);
        $this->assertSame('Funcionário de teste',$row->funcionario_nome);
        $this->assertSame('2026-10-21 10:00:00',$row->inicio->format('Y-m-d H:i:s'));
        $this->assertSame(1,Access::table('Auditorias')->find()->where(['apontamento_id'=>$id])->count());
    }
    public static function overlapping(): array
    { return [['07:00','11:00'],['09:00','12:00'],['06:00','08:00'],['08:00','10:00'],['06:00','12:00']]; }
    #[DataProvider('overlapping')]
    public function testGlobalOverlapsAcrossSectorsAndOrders(string $from,string $to): void
    {
        $this->entries->save($this->responsavel,$this->input());
        try { $this->entries->save($this->pcm,$this->input('2026-10-21T'.$from,'2026-10-21T'.$to,1)); $this->fail('Conflito não bloqueado.'); }
        catch (ConflictException $e) { $this->assertCount(1,$e->conflicts); $this->assertSame('Mecânica',$e->conflicts[0]['setor']); $this->assertSame('os-0',$e->conflicts[0]['os']); }
        $this->assertSame(1,Access::table('Apontamentos')->find()->count());
    }
    public function testConsecutiveAndMultipleConflicts(): void
    {
        $this->entries->save($this->admin,$this->input());
        $this->entries->save($this->admin,$this->input('2026-10-21T11:00','2026-10-21T15:00',1));
        try { $this->entries->save($this->admin,$this->input('2026-10-21T09:00','2026-10-21T12:00')); $this->fail(); }
        catch (ConflictException $e) { $this->assertCount(2,$e->conflicts); }
    }
    public function testMidnightAndDifferentEmployee(): void
    {
        $id=$this->entries->save($this->admin,$this->input());
        $other=$this->input();$other['funcionario_chave']='pessoa-2';
        $this->assertGreaterThan(0,$this->entries->save($this->admin,$other));
        $this->expectException(\DomainException::class);
        $this->entries->save($this->admin,$this->input('2026-10-21T23:00','2026-10-22T02:00'));
    }    public static function invalidIntervals(): array
    { return [['2026-10-21T07:00','2026-10-21T07:00'],['2026-10-21T08:00','2026-10-21T07:00'],['2026-02-30T07:00','2026-03-01T08:00'],['invalid','2026-10-21T08:00']]; }
    #[DataProvider('invalidIntervals')]
    public function testInvalidIntervals(string $from,string $to): void
    { $this->expectException(\DomainException::class); $this->entries->save($this->admin,$this->input($from,$to)); }
    public function testEditingSelfExclusionAndAudit(): void
    {
        $id=$this->entries->save($this->responsavel,$this->input());
        $data=$this->input()+['motivo'=>'Correção autorizada']; $data['descricao']='Serviço corrigido';
        $this->entries->save($this->pcm,$data,$id);
        $audit=Access::table('Auditorias')->find()->where(['apontamento_id'=>$id,'acao'=>'correcao'])->firstOrFail();
        $this->assertStringContainsString('Serviço realizado',json_decode($audit->antes,true)['descricao']);
        $this->assertSame('Serviço corrigido',json_decode($audit->depois,true)['descricao']);
        $this->entries->save($this->admin,$this->input('2026-10-21T12:00','2026-10-21T14:00',1));
        $data['fim']='2026-10-21T13:00';
        $this->expectException(ConflictException::class); $this->entries->save($this->admin,$data,$id);
    }
    public function testLogicalCancellationAndControlHistory(): void
    {
        $id=$this->entries->save($this->admin,$this->input());
        foreach(['conferido','lancado','pendente','cancelar'] as $action) { $this->entries->change($this->pcm,$id,$action,'Conferência de teste'); }
        $row=Access::table('Apontamentos')->get($id); $this->assertTrue($row->cancelado);
        $this->assertSame(5,Access::table('Auditorias')->find()->where(['apontamento_id'=>$id])->count());
        $this->entries->save($this->responsavel,$this->input());
        $this->assertSame(2,Access::table('Apontamentos')->find()->count());
    }
    public function testSectorAndRolePermissions(): void
    {
        try { $this->entries->save($this->responsavel,$this->input(activity:1)); $this->fail(); } catch(\Cake\Http\Exception\ForbiddenException) { $this->assertTrue(true); }
        $id=$this->entries->save($this->responsavel,$this->input());
        try { $this->entries->save($this->responsavel,$this->input()+['motivo'=>'Teste'],$id); $this->fail(); } catch(\Cake\Http\Exception\ForbiddenException) { $this->assertTrue(true); }
        $this->authenticate($this->responsavel);
        foreach(['/usuarios','/consulta-pcm','/setores/add','/apontamentos/edit/'.$id,'/atividades/view/'.$this->activities[1]] as $url) { $this->get($url); $this->assertResponseCode(403); }
        $this->get('/atividades'); $this->assertResponseOk(); $this->assertResponseNotContains('Caderno Elétrica');
    }
    private function shareNotebook(): void
    {
        $table=Access::table('Cadernos'); $book=$table->get($this->notebooks[0],contain:['Setores']);
        $book=$table->patchEntity($book,['setores'=>['_ids'=>$this->sectors]]);
        $table->saveOrFail($book);
    }
    public function testNotebookAndSectorManyToManyPreserveLegacyAndAvoidDoubleCount(): void
    {
        $before=Access::table('Atividades')->get($this->activities[0])->toArray();
        $this->shareNotebook(); $table=Access::table('Cadernos');
        $book=$table->get($this->notebooks[0],contain:['Setores']);
        $this->assertCount(2,$book->setores); $this->assertSame($this->sectors[0],(int)$book->setor_id);
        $other=$table->saveOrFail($table->newEntity(['nome'=>'Outro caderno compartilhado','entressafra_id'=>$book->entressafra_id,'setores'=>['_ids'=>$this->sectors],'ativo'=>true,'area_fisica'=>'Moenda']));
        $this->assertCount(2,Access::notebookSectors((int)$other->id));
        $this->assertCount(3,Access::table('Setores')->get($this->sectors[1],contain:['Cadernos'])->cadernos);
        $this->assertSame(3,$table->find()->where(['id IN'=>Access::notebookIds($this->sectors)])->count());
        $this->assertEquals($before,Access::table('Atividades')->get($this->activities[0])->toArray());
        $this->get('/cadernos/view/'.$book->id); $this->assertResponseOk(); $this->assertResponseContains('Mecânica'); $this->assertResponseContains('Elétrica');
        $this->get('/cadernos/edit/'.$book->id); $this->assertResponseOk(); $this->assertResponseContains('name="area_curva_id"');$this->assertResponseNotContains('name="setores[_ids][]"');
        $areas=Access::table('AreasFabrica');$area=$areas->saveOrFail(Access::entity($areas,['nome'=>'Moenda apresentação','entressafra_id'=>$book->entressafra_id]));
        $this->enableCsrfToken(); $this->post('/cadernos/add',['nome'=>'Caderno pela interface','area_curva_id'=>$area->id,'setores'=>['_ids'=>$this->sectors],'area_fisica'=>'Moenda']);
        $created=$table->find()->where(['nome'=>'Caderno pela interface'])->firstOrFail();
        $this->assertRedirect(['controller'=>'Cadernos','action'=>'index']);
        $this->assertCount(0,Access::notebookSectors((int)$created->id)); $this->assertNull($created->area_fisica);$this->assertSame((int)$area->id,(int)$created->area_curva_id);
        $book=$table->patchEntity($book,['setores'=>['_ids'=>[$this->sectors[1]]]]);
        $this->assertFalse($table->save($book)); $this->assertNotEmpty($book->getError('setores'));
    }
    public function testDifferentExecutingSectorsInOneNotebookDoNotBroadenPermissions(): void
    {
        $this->shareNotebook();
        $id=(new Activities(new FakeTotvs('A1')))->link($this->admin,$this->notebooks[0],'new-1');
        $activity=Access::table('Atividades')->get($id);
        $this->assertSame($this->sectors[1],(int)$activity->setor_executante_id); $this->assertSame('A1',$activity->area_executante_totvs);
        $this->authenticate($this->responsavel);
        $this->get('/cadernos/view/'.$this->notebooks[0]); $this->assertResponseOk();
        $this->assertResponseNotContains('Elétrica');
        $this->get('/atividades/view/'.$id); $this->assertResponseCode(403);
        $this->get('/atividades'); $this->assertResponseOk(); $this->assertResponseNotContains('new-1');
        $this->get('/apontamentos/ordens?q=new-1&caderno_id='.$this->notebooks[0]); $this->assertResponseOk();
        $this->assertSame([],json_decode((string)$this->_response->getBody(),true)['results']);
        $input=$this->input(); $input['atividade_id']=$id;
        try { $this->entries->save($this->responsavel,$input); $this->fail(); } catch (\Cake\Http\Exception\ForbiddenException) { $this->assertSame(0,Access::table('Apontamentos')->find()->count()); }
        $this->expectException(\Cake\Http\Exception\ForbiddenException::class);
        (new Activities(new FakeTotvs('A1')))->link($this->responsavel,$this->notebooks[0],'new-2');
    }
    public function testNotebookSearchQueriesOnlyAuthorizedAssociatedAreas(): void
    {
        $this->shareNotebook();
        $gateway=$this->createMock(\App\Integration\Totvs\GatewayInterface::class);
        $gateway->expects($this->exactly(2))->method('orders')->willReturnCallback(function($term,$area,$branch) {
            $this->assertContains($area,['A0','A1']); $this->assertSame('01',$branch);
            return [(new FakeTotvs($area))->order($area==='A0'?'new-0':'new-1')];
        });
        $rows=(new Activities($gateway))->searchNotebook($this->admin,$this->notebooks[0],'ENTRESSAFRA');
        $this->assertCount(2,$rows); $this->assertEqualsCanonicalizing(['A0','A1'],array_column($rows,'codigo_area'));
        $restricted=$this->createMock(\App\Integration\Totvs\GatewayInterface::class);
        $restricted->expects($this->once())->method('orders')->with('ENTRESSAFRA','A0','01')->willReturn([(new FakeTotvs('A0'))->order('new-0')]);
        $service=new Activities($restricted);
        $this->assertCount(1,$service->searchNotebook($this->responsavel,$this->notebooks[0],'ENTRESSAFRA'));
        $this->expectException(\DomainException::class); $service->searchNotebook($this->responsavel,$this->notebooks[0],'ENTRESSAFRA',$this->sectors[1]);
    }
    public function testSharedNotebookKeepsGlobalTimeConflictsAndHistoricalSectorSnapshots(): void
    {
        $this->shareNotebook(); $second=(new Activities(new FakeTotvs('A1')))->link($this->admin,$this->notebooks[0],'new-1');
        \App\Test\Support\CompletedOrder::set($second,$this->admin);
        $first=$this->entries->save($this->admin,$this->input()); $before=Access::table('Apontamentos')->get($first)->toArray();
        $input=$this->input('2026-10-21T09:00','2026-10-21T12:00'); $input['atividade_id']=$second;
        try { $this->entries->save($this->pcm,$input); $this->fail(); } catch (ConflictException) { $this->assertTrue(true); }
        $input['inicio']='2026-10-21T11:00'; $input['fim']='2026-10-21T15:00'; $id=$this->entries->save($this->pcm,$input);
        $snapshot=json_decode(Access::table('Apontamentos')->get($id)->snapshot,true);
        $this->assertSame($this->sectors[1],$snapshot['setor_id']); $this->assertSame('A1',$snapshot['area_executante_totvs']);
        $this->assertEquals($before,Access::table('Apontamentos')->get($first)->toArray());
        $this->assertSame(2,Access::table('Apontamentos')->find()->count());
    }
    public function testSynchronizationPreservesOriginalExecutingAreaAndNeverTransfersActivity(): void
    {
        $this->shareNotebook(); $id=$this->activities[0]; $original=Access::table('Atividades')->get($id);
        $row=(new FakeTotvs('A1'))->order('os-0');
        $gateway=$this->createMock(\App\Integration\Totvs\GatewayInterface::class); $gateway->method('order')->willReturn($row);
        (new Activities($gateway))->synchronize($this->admin,$id);
        $updated=Access::table('Atividades')->get($id); $snapshot=json_decode($updated->snapshot_totvs,true);
        $this->assertSame($original->caderno_id,$updated->caderno_id); $this->assertSame($original->setor_executante_id,$updated->setor_executante_id);
        $this->assertSame('A0',$updated->area_executante_totvs); $this->assertFalse($updated->disponivel_totvs);
        $this->assertSame('A0',$snapshot['codigo_area']); $this->assertSame('A1',$snapshot['ultima_sincronizacao']['codigo_area']);
    }
    public function testAllScreensAndCorrections(): void
    {
        $id=$this->entries->save($this->admin,$this->input());
        foreach(['/atividades','/atividades/add','/atividades/view/'.$this->activities[0],'/apontamentos','/apontamentos/add','/apontamentos/view/'.$id,'/apontamentos/edit/'.$id,'/consulta-pcm','/usuarios','/usuarios/add','/usuarios/edit/'.$this->responsavel] as $url) { $this->get($url); $this->assertResponseOk(); }
        $this->enableCsrfToken();
        $this->post('/apontamentos/edit/'.$id,$this->input()+['motivo'=>'Ajuste pela interface']); $this->assertResponseCode(302);
        $this->post('/apontamentos/change/'.$id,['acao'=>'lancado','motivo'=>'Conferido manualmente','confirmar'=>'1']); $this->assertResponseCode(302);
        $this->get('/apontamentos/view/'.$id); $this->assertResponseContains('Conferido manualmente');
    }
    public function testOperationalAndUserMenusRespectAllThreeRolesAndBatchRoute(): void
    {
        foreach ([$this->admin=>'ADMIN_PCM',$this->pcm=>'PCM',$this->responsavel=>'RESPONSAVEL_SETOR'] as $id=>$role) {
            $this->authenticate($id); $this->get('/atividades'); $this->assertResponseOk();
            $document=new \DOMDocument(); @$document->loadHTML('<?xml encoding="utf-8" ?>'.(string)$this->_response->getBody());
            $xpath=new \DOMXPath($document);
            $paths=[]; foreach ($xpath->query('//nav[@aria-label="Navegação principal"]//a') as $link) { $paths[]=$link->getAttribute('href'); }
            $allowed=$role!=='RESPONSAVEL_SETOR';
            $this->assertSame($allowed?['/fila-pcm','/apontamentos/add','/insumos/add','/lancar-porcentagem','/consulta-pcm','/controle-pcm']:['/apontamentos/add','/insumos/add','/lancar-porcentagem','/','/atividades','/apontamentos'],$paths);
            $paths=[]; foreach ($xpath->query('//details[@id="pcm-user-menu"]//a') as $link) { $paths[]=$link->getAttribute('href'); }
            $expected=$allowed?['/entressafras','/setores','/cadernos']:[];
            if ($role==='ADMIN_PCM') { $expected[]='/os-sem-caderno';$expected[]='/usuarios';$expected[]='/servicos-sugeridos'; }
            if ($allowed) { $expected[]='/atividades'; }
            $this->assertSame($expected,$paths);
            $this->assertSame(1,$xpath->query('//details[@id="pcm-user-menu"]//form[@action="/logout" and @method="post"]//button[@type="submit"]')->length);
            $this->assertSame(0,$xpath->query('//main//a[@href="/vinculos-lote" and contains(@class,"btn-primary")]')->length);
            $this->assertSame(1,$xpath->query('//main//a[@href="/atividades/add"]')->length);
            $this->assertSame(1,$xpath->query($allowed?'//nav[@aria-label="Cadastros administrativos"]//a[@href="/atividades" and @aria-current="page"]':'//nav[@aria-label="Navegação principal"]//a[@href="/atividades" and @aria-current="page"]')->length);
            $this->assertSame(0,$xpath->query('//header//a[@href="/evolucao"]')->length);
            $this->get('/vinculos-lote'); $this->assertResponseCode($allowed?200:403);
            if ($allowed) {
                $this->assertResponseContains('Preparar prévia'); $this->assertResponseContains('id="bulk-services"');
                foreach ($expected as $path) { $this->get($path); $this->assertResponseOk(); }
            }
            if ($role==='PCM') { $this->get('/usuarios'); $this->assertResponseCode(403); }
        }
    }
    public function testUserMenuLogoutAndCurrentConsultationLink(): void
    {
        $this->get('/consulta-pcm'); $this->assertResponseOk();
        $document=new \DOMDocument(); @$document->loadHTML((string)$this->_response->getBody()); $xpath=new \DOMXPath($document);
        $this->assertSame(1,$xpath->query('//nav//a[@href="/consulta-pcm" and @aria-current="page"]')->length);
        $this->assertSame(0,$xpath->query('//nav//a[@href="/apontamentos" and @aria-current="page"]')->length);
        foreach ([$this->admin,$this->pcm,$this->responsavel] as $id) {
            $this->authenticate($id); $this->enableCsrfToken(); $this->post('/logout'); $this->assertRedirect('/login');
            $this->assertSession(null,'Auth.id');
            $this->session(['Auth'=>[]]); // Do not re-inject the fixture's pre-logout authentication.
            $this->get('/vinculos-lote'); $this->assertRedirect('/login');
        }
    }
    public function testLoginAndProtectedRoutes(): void
    {
        $this->session(['Auth'=>[]]); $this->get('/apontamentos'); $this->assertRedirect('/login');
        $this->enableCsrfToken(); $this->post('/login',['login'=>'pcm-teste','senha'=>'errada']); $this->assertResponseOk(); $this->assertResponseContains('Login ou senha inválidos');
        $this->post('/login',['login'=>'pcm-teste','senha'=>'Teste-Restrito-2026']); $this->assertResponseCode(302);
        $this->assertSession($this->pcm, 'Auth.id');
        $this->authenticate($this->pcm);
        $this->get('/consulta-pcm'); $this->assertResponseOk();
        $this->post('/logout'); $this->assertRedirect('/login');
    }
    public function testReadOnlyAllowlistAndUnavailableGateway(): void
    {
        $driver=new \App\Database\Driver\ProtheusReadOnly([]);
        try { $driver->prepare('DELETE FROM qualquer_tabela'); $this->fail(); } catch(\LogicException) { $this->assertTrue(true); }
        try { $driver->exec('SELECT 1'); $this->fail(); } catch(\LogicException) { $this->assertTrue(true); }
        $this->expectException(\DomainException::class); (new \App\Integration\Totvs\Gateway())->employees('x');
    }
    public function testConflictFormPreservesFieldsAndExistingEntry(): void
    {
        $id=$this->entries->save($this->admin,$this->input());
        $this->entries->save($this->admin,$this->input('2026-10-21T12:00','2026-10-21T14:00',1));
        $this->enableCsrfToken();
        $data=$this->input('2026-10-21T09:00','2026-10-21T13:00')+['motivo'=>'Correção a revisar']; $data['descricao']='Descrição preservada após conflito';
        $this->post('/apontamentos/edit/'.$id,$data);
        $this->assertResponseOk(); $this->assertResponseContains('Horários conflitantes'); $this->assertResponseContains('Descrição preservada após conflito');
        $this->assertSame('Serviço realizado',Access::table('Apontamentos')->get($id)->descricao);
    }
    public function testSameOrderDuplicateAndRequiredReason(): void
    {
        $id=$this->entries->save($this->admin,$this->input());
        try { $this->entries->save($this->admin,$this->input()); $this->fail(); } catch(ConflictException) { $this->assertTrue(true); }
        $this->expectException(\DomainException::class); $this->entries->save($this->pcm,$this->input(),$id);
    }
    public function testResponsibleCannotCancelOrLinkOtherSector(): void
    {
        $id=$this->entries->save($this->responsavel,$this->input());
        try { $this->entries->change($this->responsavel,$id,'cancelar','Teste'); $this->fail(); } catch(\Cake\Http\Exception\ForbiddenException) { $this->assertTrue(true); }
        $this->expectException(\Cake\Http\Exception\ForbiddenException::class);
        (new Activities(new FakeTotvs()))->link($this->responsavel,$this->notebooks[1],'outra-os');
    }
    public function testPasswordChangeAndDeactivationInvalidateSession(): void
    {
        $this->authenticate($this->responsavel);
        $user=Access::table('Usuarios')->get($this->responsavel); $user->ativo=false; Access::table('Usuarios')->saveOrFail($user);
        $this->get('/apontamentos'); $this->assertRedirect('/login');
        $this->assertTrue(password_verify('Teste-Restrito-2026',$user->senha_hash));
        $this->assertNotSame('Teste-Restrito-2026',$user->senha_hash);
        $this->assertArrayNotHasKey('senha_hash', $user->toArray());
    }
    public function testSixCharacterUserPasswordAndLoginLockout(): void
    {
        $password = substr(bin2hex(random_bytes(8)), 0, 6);
        $data = ['nome'=>'Usuário de teste','login'=>'senha-curta-teste','senha'=>$password,'perfil'=>'PCM','ativo'=>'1'];
        $id = (new Users())->save($this->admin, $data);
        $row = Access::table('Usuarios')->get($id);
        $this->assertTrue(password_verify($password, $row->senha_hash));
        $this->assertNotSame($password, $row->senha_hash);
        $data['senha'] = '';
        (new Users())->save($this->admin, $data, $id);
        $this->assertSame($row->senha_hash, Access::table('Usuarios')->get($id)->senha_hash);
        $password = substr(bin2hex(random_bytes(8)), 0, 6);
        $data['senha'] = $password;
        (new Users())->save($this->admin, $data, $id);
        $this->assertTrue(password_verify($password, Access::table('Usuarios')->get($id)->senha_hash));
        $this->session(['Auth'=>[]]); $this->enableCsrfToken();
        $this->post('/login', ['login'=>$data['login'],'senha'=>$password]);
        $this->assertResponseCode(302); $this->assertSession($id, 'Auth.id');
        $this->session(['Auth'=>[]]);
        for ($attempt=0; $attempt<5; $attempt++) {
            $this->post('/login', ['login'=>$data['login'],'senha'=>bin2hex(random_bytes(16))]);
            $this->assertResponseOk();
        }
        $this->assertNotNull(Access::table('Usuarios')->get($id)->bloqueado_ate);
        $this->post('/login', ['login'=>$data['login'],'senha'=>$password]);
        $this->assertResponseOk(); $this->assertResponseContains('temporariamente bloqueada');
        $data['senha'] = substr($password,0,5);
        $this->expectException(\DomainException::class);
        (new Users())->save($this->admin, $data, $id);
    }
    public function testCreateAdminAcceptsSixCharactersAndRejectsFive(): void
    {
        $admin=Access::table('Usuarios')->get($this->admin); $admin->ativo=false; Access::table('Usuarios')->saveOrFail($admin);
        $keys=['PCM_ADMIN_NAME','PCM_ADMIN_LOGIN','PCM_ADMIN_PASSWORD']; $original=[];
        foreach($keys as $key) { $original[$key]=getenv($key); }
        $password=substr(bin2hex(random_bytes(8)),0,6);
        try {
            putenv('PCM_ADMIN_NAME=Administrador de teste'); putenv('PCM_ADMIN_LOGIN=novo-admin-teste'); putenv('PCM_ADMIN_PASSWORD='.substr($password,0,5));
            $io=$this->createMock(\Cake\Console\ConsoleIo::class);
            $io->expects($this->once())->method('err');
            $command=new \App\Command\CreateAdminCommand(); $args=new \Cake\Console\Arguments([],[],[]);
            $this->assertSame(1,$command->execute($args,$io));
            $this->assertFalse(Access::table('Usuarios')->exists(['login'=>'novo-admin-teste']));
            putenv('PCM_ADMIN_PASSWORD='.$password);
            $io=$this->createMock(\Cake\Console\ConsoleIo::class);
            $io->expects($this->never())->method('err');
            $io->expects($this->once())->method('success')->with('Administrador criado. Nenhuma senha foi exibida.');
            $this->assertSame(0,$command->execute($args,$io));
            $row=Access::table('Usuarios')->find()->where(['login'=>'novo-admin-teste'])->firstOrFail();
            $this->assertTrue(password_verify($password,$row->senha_hash));
        } finally {
            foreach($original as $key=>$value) { putenv($value===false ? $key : $key.'='.$value); }
        }
    }
    public function testAutocompleteRouteRequiresLoginAndValidSearch(): void
    {
        $this->get('/apontamentos/funcionarios?q=x'); $this->assertResponseCode(422);
        $this->assertContentType('application/json');
        $this->assertHeader('Cache-Control','no-store, private');
        $this->get('/apontamentos/funcionarios?q[]=nome'); $this->assertResponseCode(422);
        $this->session(['Auth'=>[]]); $this->get('/apontamentos/funcionarios?q=Nome'); $this->assertRedirect('/login');
    }
    public function testEmployeeStatusesCannotBeForgedByFrontend(): void
    {
        foreach(['F','D','A','X'] as $status) {
            $lookup=new \App\Integration\Totvs\Employees(fn($sql)=>$sql===\App\Integration\Totvs\Queries::EMPLOYEE_DUPLICATES?[]:[['RA_NOME'=>'Nome teste','RA_MAT'=>'000123','elegiveis'=>1,'RA_CODFUNC'=>'00123','RA_SITFOLH'=>$status,'RA_FILIAL'=>'01','R_E_C_N_O_'=>123]]);
            $people=$lookup->search('Nome');
            if ($status==='F') { $this->assertSame('FÉRIAS',$people[0]['situacao']); $this->assertFalse($people[0]['ativo']); }
            else { $this->assertSame([],$people); }
            $gateway=$this->createMock(\App\Integration\Totvs\GatewayInterface::class);
            $gateway->method('employee')->willReturnCallback(fn($key)=>$lookup->find($key));
            $input=$this->input(); $input['funcionario_chave']='000123'; $input['ativo']=true;
            try { (new TimeEntries($gateway))->save($this->admin,$input); $this->fail('Situação não normal aceita.'); }
            catch(\DomainException) { $this->assertSame(0,Access::table('Apontamentos')->find()->count()); }
        }
    }
    public function testConfirmedEmployeeMapperIntegratesWithGlobalConflict(): void
    {
        $lookup=new \App\Integration\Totvs\Employees(fn($sql)=>$sql===\App\Integration\Totvs\Queries::EMPLOYEE_DUPLICATES?[]:[['RA_NOME'=>'Nome teste','RA_MAT'=>'000123','elegiveis'=>1,'RA_CODFUNC'=>'00123','RA_SITFOLH'=>'','RA_FILIAL'=>'01','R_E_C_N_O_'=>123]]);
        $person=$lookup->search('Nome')[0];
        $gateway=$this->createMock(\App\Integration\Totvs\GatewayInterface::class);
        $gateway->method('employee')->willReturnCallback(fn($key)=>$lookup->find($key));
        $service=new TimeEntries($gateway); $input=$this->input(); $input['funcionario_chave']=$person['chave'];
        $id=$service->save($this->responsavel,$input);
        $this->assertSame('000123',Access::table('Apontamentos')->get($id)->funcionario_matricula);
        $snapshot=json_decode(Access::table('Apontamentos')->get($id)->snapshot,true);
        $this->assertSame('00123',$snapshot['funcionario']['funcao']);
        $input['atividade_id']=$this->activities[1];
        $this->expectException(ConflictException::class); $service->save($this->pcm,$input);
    }
    public function testCompactEmployeeFormAndCorrectionKeepServerConfirmedSnapshot(): void
    {
        $this->get('/apontamentos/add'); $this->assertResponseOk();
        $this->assertResponseContains('id="row-0-funcionario-resumo"');
        $this->assertResponseContains('id="row-0-funcionario-chave"');
        foreach (['funcionario_exibicao_','Nome cadastral','Código da função','id="funcionario-nome"','id="funcionario-matricula"','id="funcionario-funcao"','id="funcionario-situacao"'] as $removed) { $this->assertResponseNotContains($removed); }
        $lookup=new \App\Integration\Totvs\Employees(fn()=>[['RA_NOME'=>'Pessoa confirmada','RA_MAT'=>'000123','RA_CODFUNC'=>'00123','RA_SITFOLH'=>'','elegiveis'=>1]]);
        $gateway=$this->createMock(\App\Integration\Totvs\GatewayInterface::class);
        $gateway->method('employee')->willReturnCallback(fn($key)=>$lookup->find($key));
        $input=$this->input(); $input['funcionario_chave']='000123';
        $id=(new TimeEntries($gateway))->save($this->admin,$input);
        $before=Access::table('Apontamentos')->get($id);
        $snapshot=json_decode($before->snapshot,true,512,JSON_THROW_ON_ERROR);
        $this->assertSame('00123',$snapshot['funcionario']['funcao']);
        $this->assertSame('NORMAL',$snapshot['funcionario']['situacao']);
        $this->assertSame('000123',$before->funcionario_matricula);
        $this->get('/apontamentos/edit/'.$id); $this->assertResponseOk();
        $this->assertResponseContains('Pessoa confirmada'); $this->assertResponseContains('Matrícula 000123');
        $this->assertResponseContains('Identificação preservada'); $this->assertResponseNotContains('funcionario_exibicao_');
        $this->enableCsrfToken();
        $this->post('/apontamentos/edit/'.$id,$input+['motivo'=>'Conferência do serviço','funcionario_nome'=>'Nome adulterado']);
        $this->assertRedirect(['controller'=>'Apontamentos','action'=>'view',$id]);
        $after=Access::table('Apontamentos')->get($id);
        $this->assertSame($before->snapshot,$after->snapshot);
        $this->assertSame('Pessoa confirmada',$after->funcionario_nome);
    }
    public function testInvalidPostKeepsFormValuesWithoutConfirmingUntrustedEmployee(): void
    {
        $input=$this->input(); $input['atividade_id']='999999999';
        $input['funcionario_chave']='identificacao-invalida'; $input['funcionario_rotulo']='Nome somente digitado';
        $this->enableCsrfToken(); $this->post('/apontamentos/add',$input);
        $this->assertResponseOk(); $body=(string)$this->_response->getBody();
        $this->assertResponseContains('Serviço realizado');
        $this->assertResponseContains('21/10/2026'); $this->assertResponseContains('value="07:00"'); $this->assertResponseContains('value="11:00"');
        $this->assertResponseContains('Nome somente digitado');
        $this->assertMatchesRegularExpression('/id="funcionario-resumo"[^>]*\shidden/',$body);
        $this->assertResponseNotContains('value="identificacao-invalida"');
        $this->assertSame(0,Access::table('Apontamentos')->find()->count());
    }
    public function testOrderAutocompleteByNumberDescriptionAndSector(): void
    {
        $this->authenticate($this->responsavel);
        foreach(['os-0','Serviço'] as $term) {
            $this->get('/apontamentos/ordens?q='.urlencode($term));
            $this->assertResponseOk(); $this->assertContentType('application/json');
            $data=json_decode((string)$this->_response->getBody(),true);
            $this->assertCount(1,$data['results']);
            $this->assertSame((string)$this->activities[0],$data['results'][0]['id']);
            $this->assertSame('Mecânica',$data['results'][0]['setor']);
            $this->assertArrayHasKey('bem',$data['results'][0]); $this->assertArrayHasKey('situacao',$data['results'][0]);
        }
        $this->get('/apontamentos/ordens?q=os&setor_id='.$this->sectors[1]);
        $this->assertResponseOk();
        $this->assertSame([],json_decode((string)$this->_response->getBody(),true)['results']);
        $this->get('/apontamentos/ordens?q=os&caderno_id='.$this->notebooks[0]);
        $this->assertResponseOk();
        $this->assertCount(1,json_decode((string)$this->_response->getBody(),true)['results']);
    }
    public function testOrderAutocompleteEmptyInvalidAndInjection(): void
    {
        $this->get('/apontamentos/ordens?q='.urlencode("' OR 1=1 --"));
        $this->assertResponseOk();
        $data=json_decode((string)$this->_response->getBody(),true);
        $this->assertSame([],$data['results']); $this->assertStringContainsString('vínculo ao caderno',$data['message']);
        $this->get('/apontamentos/ordens?q=os&setor_id=abc'); $this->assertResponseCode(422);
        $this->get('/apontamentos/ordens?q=x'); $this->assertResponseCode(422);
        $this->session(['Auth'=>[]]); $this->get('/apontamentos/ordens?q=os'); $this->assertRedirect('/login');
    }
    public function testOrderFilterMismatchAndInventedIdentifiersCannotSave(): void
    {
        $gateway=$this->createMock(\App\Integration\Totvs\GatewayInterface::class);
        $gateway->expects($this->never())->method('employee');
        $service=new TimeEntries($gateway);
        foreach(['texto digitado','999999',''] as $invalid) {
            $data=$this->input(); $data['atividade_id']=$invalid;
            try { $service->save($this->admin,$data); $this->fail('Identificador inválido aceito.'); }
            catch(\DomainException $e) { $this->assertStringContainsString('O.S. cadastrada',$e->getMessage()); }
        }
        $data=$this->input()+['caderno_id'=>$this->notebooks[1]];
        try { $service->save($this->admin,$data); $this->fail('Filtros divergentes aceitos.'); }
        catch(\DomainException $e) { $this->assertStringContainsString('não corresponde aos filtros',$e->getMessage()); }
        $this->assertSame(0,Access::table('Apontamentos')->find()->count());
    }
    public function testOrderLookupLimitAndInactiveNotebook(): void
    {
        $table=Access::table('Atividades'); $base=$table->get($this->activities[0])->toArray(); unset($base['id']);
        for($i=0;$i<22;$i++) { $copy=$base; $copy['os_chave']='extra-'.$i; $copy['os_numero']='extra-'.$i; $table->saveOrFail(Access::entity($table,$copy)); }
        $this->get('/apontamentos/ordens?q=extra'); $this->assertResponseOk();
        $data=json_decode((string)$this->_response->getBody(),true);
        $this->assertCount(20,$data['results']); $this->assertTrue($data['has_more']);
        $notebook=Access::table('Cadernos')->get($this->notebooks[0]); $notebook->ativo=false; Access::table('Cadernos')->saveOrFail($notebook);
        $this->get('/apontamentos/ordens?q=extra'); $this->assertResponseOk();
        $data=json_decode((string)$this->_response->getBody(),true);
        $this->assertCount(20,$data['results']);
        foreach ($data['results'] as $row) { $this->assertNull($row['id']); $this->assertFalse($row['selecionavel']); }
    }
    public function testDirectOrderWithoutLocalActivityIsVisibleButCannotBeSelected(): void
    {
        $gateway=$this->createMock(\App\Integration\Totvs\GatewayInterface::class);
        $row=(new FakeTotvs())->order('005523');
        $gateway->method('orders')->willReturn([$row]);
        $this->mockService(\App\Integration\Totvs\GatewayInterface::class,fn()=>$gateway);
        $this->authenticate($this->responsavel);
        $this->get('/apontamentos/ordens?q=005523'); $this->assertResponseOk();
        $data=json_decode((string)$this->_response->getBody(),true);
        $this->assertCount(1,$data['results']);
        $this->assertSame('005523',$data['results'][0]['numero']);
        $this->assertNull($data['results'][0]['id']); $this->assertFalse($data['results'][0]['selecionavel']);
        $this->assertStringContainsString('PCM',$data['results'][0]['aviso']);
        $this->assertSame(2,Access::table('Atividades')->find()->count());
    }
    public function testDirectOrderSearchNeverUsesUnmappedOrUnauthorizedAreas(): void
    {
        $gateway=$this->createMock(\App\Integration\Totvs\GatewayInterface::class);
        $gateway->expects($this->never())->method('orders');
        $this->mockService(\App\Integration\Totvs\GatewayInterface::class,fn()=>$gateway);
        $this->authenticate($this->responsavel);
        $this->get('/apontamentos/ordens?q=005523&setor_id='.$this->sectors[1]); $this->assertResponseOk();
        $this->assertSame([],json_decode((string)$this->_response->getBody(),true)['results']);
        $table=Access::table('Setores'); $sector=$table->get($this->sectors[0]);
        $sector->area_totvs=null; $sector->filial_totvs=null; $table->saveOrFail($sector);
        $this->get('/apontamentos/ordens?q=005523'); $this->assertResponseOk();
        $data=json_decode((string)$this->_response->getBody(),true);
        $this->assertSame([],$data['results']); $this->assertStringContainsString('identificação das equipes autorizadas',$data['message']);
    }
    public function testDirectOrderRejectsForeignAreaBranchAndClosedSource(): void
    {
        $gateway=$this->createMock(\App\Integration\Totvs\GatewayInterface::class);
        $normal=(new FakeTotvs())->order('005523');
        $otherArea=$normal; $otherArea['codigo_area']='A1';
        $otherBranch=$normal; $otherBranch['filial']='02';
        $closed=$normal; $closed['termino']='S';
        $gateway->method('orders')->willReturn([$otherArea,$otherBranch,$closed]);
        $this->mockService(\App\Integration\Totvs\GatewayInterface::class,fn()=>$gateway);
        $this->authenticate($this->responsavel);
        $this->get('/apontamentos/ordens?q=005523'); $this->assertResponseOk();
        $this->assertSame([],json_decode((string)$this->_response->getBody(),true)['results']);
    }
    public function testBothLookupRoutesReportConnectionFailureWithoutSecrets(): void
    {
        $gateway=$this->createMock(\App\Integration\Totvs\GatewayInterface::class);
        $gateway->method('orders')->willThrowException(new \DomainException('Falha protegida.'));
        $gateway->method('employees')->willThrowException(new \DomainException(\App\Integration\Totvs\Employees::UNAVAILABLE));
        $this->mockService(\App\Integration\Totvs\GatewayInterface::class,fn()=>$gateway);
        foreach (['ordens','funcionarios'] as $route) {
            $this->get('/apontamentos/'.$route.'?q=Maria'); $this->assertResponseCode(503);
            $data=json_decode((string)$this->_response->getBody(),true);
            $this->assertArrayHasKey('error',$data); $this->assertArrayNotHasKey('results',$data);
        }
    }
    public function testEmployeeApiReturnsDirectSourceDataWithoutRegistration(): void
    {
        $gateway=$this->createMock(\App\Integration\Totvs\GatewayInterface::class);
        $lookup=new \App\Integration\Totvs\Employees(fn()=>[
            ['RA_NOME'=>'Nome teste','RA_MAT'=>'000123','RA_CODFUNC'=>'00001','RA_SITFOLH'=>'','elegiveis'=>1],
            ['RA_NOME'=>'Nome teste','RA_MAT'=>'000124','RA_CODFUNC'=>'00002','RA_SITFOLH'=>'F','elegiveis'=>0],
        ]);
        $gateway->expects($this->once())->method('employees')->with('Maria')->willReturn($lookup->search('Maria'));
        $this->mockService(\App\Integration\Totvs\GatewayInterface::class,fn()=>$gateway);
        $this->get('/apontamentos/funcionarios?q=Maria'); $this->assertResponseOk();
        $data=json_decode((string)$this->_response->getBody(),true);
        $this->assertCount(2,$data['results']);
        $this->assertSame('000123',$data['results'][0]['matricula']);
        $this->assertSame('00001',$data['results'][0]['funcao']);
        $this->assertTrue($data['results'][0]['ativo']); $this->assertFalse($data['results'][1]['ativo']);
        $this->assertSame('FÉRIAS',$data['results'][1]['situacao']);
    }
    public function testPreviousIdentityIsPreservedAndBlocksMixedNewKeys(): void
    {
        $oldId=$this->entries->save($this->admin,$this->input());
        $old=Access::table('Apontamentos')->get($oldId)->toArray();
        $lookup=new \App\Integration\Totvs\Employees(fn($sql)=>$sql===\App\Integration\Totvs\Queries::EMPLOYEE_DUPLICATES?[]:[['RA_NOME'=>'Nome teste','RA_MAT'=>'000123','elegiveis'=>1,'RA_CODFUNC'=>'00123','RA_SITFOLH'=>'']]);
        $gateway=$this->createMock(\App\Integration\Totvs\GatewayInterface::class);
        $gateway->method('employee')->willReturnCallback(fn($key)=>$lookup->find($key));
        $data=$this->input('2026-10-22T07:00','2026-10-22T11:00'); $data['funcionario_chave']='000123';
        try { (new TimeEntries($gateway))->save($this->admin,$data); $this->fail('Mistura de identificação histórica aceita.'); }
        catch(\DomainException $e) { $this->assertStringContainsString('identificação anterior',$e->getMessage()); }
        $this->assertEquals($old,Access::table('Apontamentos')->get($oldId)->toArray());
    }
    public function testIdentificationPendingListIsRestrictedToPcm(): void
    {
        $this->authenticate($this->responsavel);
        $this->get('/funcionarios/pendencias'); $this->assertResponseCode(403);
        $this->session(['Auth'=>[]]);
        $this->get('/funcionarios/pendencias'); $this->assertRedirect('/login');
    }
    public function testUniqueEmployeeSavesDespiteOtherDuplicatesAndKeepsGlobalLock(): void
    {
        $normal=['RA_NOME'=>'Nome teste','RA_MAT'=>'000123','RA_CODFUNC'=>'00123','RA_SITFOLH'=>'','elegiveis'=>1];
        $duplicate=$normal; $duplicate['RA_MAT']='000124'; $duplicate['elegiveis']=2;
        $lookup=new \App\Integration\Totvs\Employees(function($sql,$params) use($normal,$duplicate) {
            if($sql===\App\Integration\Totvs\Queries::EMPLOYEE_SEARCH) { return [$normal,$duplicate,$duplicate]; }
            return $params['matricula']==='000123'?[$normal]:[$duplicate,$duplicate];
        });
        $people=$lookup->search('Nome'); $this->assertTrue($people[0]['ativo']); $this->assertFalse($people[1]['ativo']);
        $gateway=$this->createMock(\App\Integration\Totvs\GatewayInterface::class);
        $gateway->method('employee')->willReturnCallback(fn($number)=>$lookup->find($number));
        $service=new TimeEntries($gateway);
        $data=$this->input(); $data['funcionario_chave']='000123';
        $service->save($this->responsavel,$data);
        $other=$this->input('2026-10-21T09:00','2026-10-21T12:00',1); $other['funcionario_chave']='000123';
        try { $service->save($this->pcm,$other); $this->fail('Conflito entre setores não bloqueado.'); }
        catch(ConflictException) { $this->assertTrue(true); }
        $other['inicio']='2026-10-21T11:00'; $other['fim']='2026-10-21T15:00';
        $service->save($this->pcm,$other);
        $other['funcionario_chave']='000124';
        try { $service->save($this->pcm,$other); $this->fail('Matrícula duplicada aceita.'); }
        catch(\DomainException $error) { $this->assertSame(\App\Integration\Totvs\Employees::AMBIGUOUS,$error->getMessage()); }
        $this->assertSame(2,Access::table('Apontamentos')->find()->count());
    }
    public function testClosedOrderSyncPreservesNotebookHoursAndOriginalSnapshot(): void
    {
        $id=$this->activities[0];
        $entry=$this->entries->save($this->responsavel,$this->input());
        $before=Access::table('Apontamentos')->get($entry)->toArray();
        $old=Access::table('Atividades')->get($id); $snapshot=json_decode($old->snapshot_totvs,true);
        $row=(new FakeTotvs())->order($old->os_chave); $row['termino']='S'; $row['disponivel']=false;
        $gateway=$this->createMock(\App\Integration\Totvs\GatewayInterface::class); $gateway->method('order')->willReturn($row);
        (new Activities($gateway))->synchronize($this->pcm,$id);
        $current=Access::table('Atividades')->get($id);
        $this->assertSame($old->caderno_id,$current->caderno_id); $this->assertFalse($current->disponivel_totvs);
        $currentSnapshot=json_decode($current->snapshot_totvs,true);
        $this->assertSame($snapshot['termino'],$currentSnapshot['termino']);
        $this->assertSame('S',$currentSnapshot['ultima_sincronizacao']['termino']);
        $this->assertEquals($before,Access::table('Apontamentos')->get($entry)->toArray());
        $this->get('/apontamentos/ordens?q=os-0'); $this->assertResponseOk();
        $this->assertSame([],json_decode((string)$this->_response->getBody(),true)['results']);
        $this->get('/atividades/view/'.$id); $this->assertResponseOk(); $this->assertResponseContains('Serviço realizado');
        $this->expectException(\DomainException::class); $this->entries->save($this->responsavel,$this->input('2026-10-22T07:00','2026-10-22T11:00'));
    }
    public function testLinkRequiresAvailableOrderAndExplicitAreaMapping(): void
    {
        $row=(new FakeTotvs())->order('nova-os'); $row['codigo_area']='OUTRA';
        $gateway=$this->createMock(\App\Integration\Totvs\GatewayInterface::class); $gateway->method('order')->willReturn($row);
        try { (new Activities($gateway))->link($this->admin,$this->notebooks[0],'nova-os'); $this->fail('Área sem correspondência aceita.'); }
        catch(\DomainException $error) { $this->assertStringContainsString('TJ_CODAREA',$error->getMessage()); }
        $row['codigo_area']='A0'; $row['situacao']='P';
        $gateway=$this->createMock(\App\Integration\Totvs\GatewayInterface::class); $gateway->method('order')->willReturn($row);
        $this->expectException(\DomainException::class); (new Activities($gateway))->link($this->admin,$this->notebooks[0],'nova-os');
    }
    public function testPcmSearchFiltersByMappedAreaAndBranch(): void
    {
        $gateway=$this->createMock(\App\Integration\Totvs\GatewayInterface::class);
        $normal=(new FakeTotvs())->order('os-0'); $other=$normal; $other['filial']='02'; $pending=$normal; $pending['situacao']='P';
        $gateway->expects($this->once())->method('orders')->with('os','A0','01')->willReturn([$normal,$other,$pending]);
        $this->assertCount(1,(new Activities($gateway))->search($this->responsavel,$this->sectors[0],'os'));
        $this->expectException(\Cake\Http\Exception\ForbiddenException::class);
        (new Activities($gateway))->search($this->responsavel,$this->sectors[1],'os');
    }
    public function testMaskedDateFormCreatesAndEditsFullStoredDatetimes(): void
    {
        $this->mockService(\App\Integration\Totvs\GatewayInterface::class,fn()=>new FakeTotvs());$this->enableCsrfToken();
        $this->get('/apontamentos/add');$this->assertResponseOk();$this->assertResponseNotContains('datetime-local');$this->assertResponseContains('name="apontamentos[0][data_servico]"');$this->assertResponseContains('name="apontamentos[0][hora_inicio_manha]"');$this->assertResponseContains('name="apontamentos[0][hora_fim_manha]"');
        $data=['atividade_id'=>$this->activities[0],'funcionario_chave'=>'pessoa-1','data_servico'=>'22/10/2026','hora_inicial'=>'07:30','hora_final'=>'11:30','termina_dia_seguinte'=>'0','descricao'=>'Serviço com máscara'];
        $this->post('/apontamentos/add',$data);$this->assertRedirect();$row=Access::table('Apontamentos')->find()->firstOrFail();$this->assertSame(240,$row->minutos);$this->assertSame('2026-10-22 10:30:00',$row->inicio->format('Y-m-d H:i:s'));
        $this->get('/apontamentos/edit/'.$row->id);$this->assertResponseOk();$this->assertResponseContains('value="22/10/2026"');$this->assertResponseContains('value="07:30"');$this->assertResponseNotContains('datetime-local');
        $data['hora_final']='12:30';$data['motivo']='Correção de horário pela interface';$this->post('/apontamentos/edit/'.$row->id,$data);$this->assertRedirect();$updated=Access::table('Apontamentos')->get($row->id);$this->assertSame(300,$updated->minutos);$this->assertSame('2026-10-22 15:30:00',$updated->fim->format('Y-m-d H:i:s'));
    }
    public function testInvalidMaskedFieldsKeepAllValuesAndNeverModifyStoredEntry(): void
    {
        $id=$this->entries->save($this->admin,$this->input());$this->enableCsrfToken();
        $this->post('/apontamentos/edit/'.$id,['data_servico'=>'31/02/2026','hora_inicial'=>'25:00','hora_final'=>'02:00','termina_dia_seguinte'=>'1','descricao'=>'Texto preservado','motivo'=>'Correção a conferir']);
        $this->assertResponseOk();foreach(['31/02/2026','25:00','02:00','Texto preservado','Correção a conferir'] as $value){$this->assertResponseContains($value);}
        $this->assertSame(240,Access::table('Apontamentos')->get($id)->minutos);$this->assertSame('Serviço realizado',Access::table('Apontamentos')->get($id)->descricao);
    }
    public function testMaskedMidnightIsRejected(): void
    {
        $this->expectException(\DomainException::class);
        $this->entries->save($this->admin,['atividade_id'=>$this->activities[0],'funcionario_chave'=>'pessoa-1','data_servico'=>'21/10/2026','hora_inicial'=>'23:00','hora_final'=>'02:00','termina_dia_seguinte'=>'1','descricao'=>'Virada']);
    }
    public function testBatchSavesDifferentEmployeesAndAdjacentIntervalsWithAudits(): void
    {
        $a=$this->input();$b=$this->input('2026-10-21T11:00','2026-10-21T12:00');$c=$a;$c['funcionario_chave']='pessoa-2';$c['atividade_id']=$this->activities[1];
        $ids=$this->entries->saveBatch($this->admin,['atividade_id'=>$this->activities[0],'apontamentos'=>[$a,$b,$c]]);
        $this->assertCount(3,$ids);
        $this->assertSame(3,Access::table('Apontamentos')->find()->count());
        $this->assertSame(3,Access::table('Apontamentos')->find()->where(['atividade_id'=>$this->activities[0]])->count());
        $this->assertSame(3,Access::table('Auditorias')->find()->where(['acao'=>'criacao'])->count());
        foreach($ids as $id){$this->get('/apontamentos/view/'.$id);$this->assertResponseOk();}
        $this->get('/apontamentos');$this->assertResponseOk();
        $this->get('/consulta-pcm');$this->assertResponseOk();
    }
    public function testBatchOverlapsAndInvalidRowsSaveNothing(): void
    {
        $a=$this->input();$b=$this->input('2026-10-21T09:00','2026-10-21T12:00');
        try{$this->entries->saveBatch($this->admin,['atividade_id'=>$this->activities[0],'apontamentos'=>[$a,$b]]);$this->fail();}
        catch(\App\Service\BatchEntryException $e){$this->assertCount(2,$e->errors);}
        $this->assertSame(0,Access::table('Apontamentos')->find()->count());
        $b=$this->input('2026-10-21T12:00','2026-10-21T11:00');
        try{$this->entries->saveBatch($this->admin,['atividade_id'=>$this->activities[0],'apontamentos'=>[$a,$b]]);$this->fail();}
        catch(\App\Service\BatchEntryException $e){$this->assertArrayHasKey(1,$e->errors);}
        $this->assertSame(0,Access::table('Apontamentos')->find()->count());
    }
    public function testBatchExistingConflictPreservesAllPostedFields(): void
    {
        $this->mockService(\App\Integration\Totvs\GatewayInterface::class,fn()=>new FakeTotvs());
        $this->entries->save($this->admin,$this->input());
        $a=['funcionario_chave'=>'pessoa-1','funcionario_rotulo'=>'Funcionário preservado','data_servico'=>'21/10/2026','hora_inicial'=>'08:00','hora_final'=>'09:00','descricao'=>'Descrição preservada'];
        $b=$a;$b['funcionario_chave']='pessoa-2';$b['descricao']='Outra descrição preservada';
        $c=$b;$c['funcionario_chave']='';$c['funcionario_rotulo']='Nome ainda digitado';
        $this->enableCsrfToken();$this->post('/apontamentos/add',['atividade_id'=>$this->activities[0],'apontamentos'=>[$a,$b,$c]]);
        $this->assertResponseOk();foreach(['Funcionário preservado','Descrição preservada','Outra descrição preservada','Nome ainda digitado','08:00','09:00','Conflito de horário'] as $text){$this->assertResponseContains($text);}
        $this->assertSame(1,Access::table('Apontamentos')->find()->count());
    }
    public function testBatchRollsBackEntriesAndAuditsWhenRevalidationFails(): void
    {
        $gateway=$this->createMock(\App\Integration\Totvs\GatewayInterface::class);$calls=0;
        $gateway->method('order')->willReturn((new FakeTotvs())->order('os-0'));
        $gateway->method('employee')->willReturnCallback(function($key)use(&$calls){$person=(new FakeTotvs())->employee($key);if(++$calls===4)$person['ativo']=false;return $person;});
        $a=$this->input();$b=$this->input('2026-10-21T11:00','2026-10-21T12:00');
        try{(new TimeEntries($gateway))->saveBatch($this->admin,['atividade_id'=>$this->activities[0],'apontamentos'=>[$a,$b]]);$this->fail();}
        catch(\App\Service\BatchEntryException $e){$this->assertArrayHasKey(1,$e->errors);}
        $this->assertSame(0,Access::table('Apontamentos')->find()->count());
        $this->assertSame(0,Access::table('Auditorias')->find()->where(['acao'=>'criacao'])->count());
    }
    public function testBatchFormAndSingleSubmission(): void
    {
        $this->mockService(\App\Integration\Totvs\GatewayInterface::class,fn()=>new FakeTotvs());
        $this->get('/apontamentos/add');$this->assertResponseOk();
        $this->assertResponseContains('Salvar apontamentos');
        $this->assertResponseNotContains('Termina no dia seguinte');
        $dom=new \DOMDocument();@$dom->loadHTML((string)$this->_response->getBody());$xpath=new \DOMXPath($dom);
        $this->assertSame(1,$xpath->query('//*[@id="entry-lines"]/article')->length);
        if(getenv('BATCH_PREVIEW')){file_put_contents(TMP.'batch-preview.html',(string)$this->_response->getBody());}
        $this->enableCsrfToken();
        $this->post('/apontamentos/add',['atividade_id'=>$this->activities[0],'apontamentos'=>[['funcionario_chave'=>'pessoa-1','data_servico'=>'21/10/2026','hora_inicial'=>'07:00','hora_final'=>'11:00','descricao'=>'Lote único']]]);
        $this->assertRedirect();$this->assertSame(1,Access::table('Apontamentos')->find()->count());
        $this->assertSession('1 apontamentos registrados com sucesso.','Flash.flash.0.message');
    }
    public function testBatchRejectsInvalidEmployeeAndTruncatedSubmission(): void
    {
        $gateway=$this->createMock(\App\Integration\Totvs\GatewayInterface::class);
        $gateway->method('order')->willReturn((new FakeTotvs())->order('os-0'));
        $gateway->method('employee')->willReturnCallback(function($key){$p=(new FakeTotvs())->employee($key);$p['ativo']=false;return $p;});
        try{(new TimeEntries($gateway))->saveBatch($this->admin,['atividade_id'=>$this->activities[0],'apontamentos'=>[$this->input()]]);$this->fail();}
        catch(\App\Service\BatchEntryException $e){$this->assertArrayHasKey(0,$e->errors);}
        $this->assertSame(0,Access::table('Apontamentos')->find()->count());
        $this->expectException(\DomainException::class);
        $this->entries->saveBatch($this->admin,['atividade_id'=>$this->activities[0],'quantidade_linhas'=>'2','apontamentos'=>[$this->input()]]);
    }
    protected function tearDown(): void
    {
        Access::table('Auditorias')->deleteAll(['insumo_id IS NOT'=>null]);
        Access::table('Insumos')->deleteAll([]);
        parent::tearDown();
    }
    public function testHoursAndSuppliesAllowEveryPercentageWithoutChangingEvolution(): void
    {
        foreach([0,10,50,99,100] as $index=>$percentage){
            \App\Test\Support\CompletedOrder::set($this->activities[0],$this->admin,$percentage);
            $before=(new \App\Service\Evolucao())->history($this->activities[0]);
            $this->assertSame((float)$percentage,\App\Service\OrderProgress::status($this->activities[0])['percentual_atual']);
            $day='2026-10-'.(21+$index);
            $this->assertGreaterThan(0,$this->entries->save($this->responsavel,$this->input($day.'T07:00',$day.'T09:00')));
            $ids=$this->entries->saveBatch($this->admin,['atividade_id'=>$this->activities[0],'apontamentos'=>[$this->input($day.'T09:00',$day.'T10:00'),$this->input($day.'T10:00',$day.'T11:00')]]);
            $this->assertCount(2,$ids);
            $this->assertCount(1,(new \App\Service\Insumos(new FakeTotvs()))->saveBatch($this->admin,['atividade_id'=>$this->activities[0],'insumos'=>[$this->supply()]]));
            $this->assertSame($before,(new \App\Service\Evolucao())->history($this->activities[0]));
        }
        $this->assertSame(15,Access::table('Apontamentos')->find()->count());
        $this->assertSame(5,Access::table('Insumos')->find()->count());
    }
    public function testPercentageIsInformationalAndPartialOrderCanBePosted(): void
    {
        $this->mockService(\App\Integration\Totvs\GatewayInterface::class,fn()=>new FakeTotvs());
        \App\Test\Support\CompletedOrder::set($this->activities[0],$this->admin,85);
        $service=new \App\Service\OperacaoRapida();$order=$service->result($service->activity(Access::actor($this->admin),$this->activities[0]));
        $this->assertSame(85.0,$order['percentual_atual']);$this->assertArrayNotHasKey('liberada_apontamento',$order);
        $this->get('/apontamentos/add?atividade_id='.$this->activities[0]);$this->assertResponseOk();
        $this->assertResponseContains('Percentual atual da O.S.: 85%');$this->assertResponseNotContains('100% de evolução');$this->assertResponseNotContains('Ainda não liberada');
        $this->enableCsrfToken();$data=$this->input();
        $this->post('/apontamentos/add',['atividade_id'=>$this->activities[0],'apontamentos'=>[$data]]);$this->assertRedirect();
        $this->assertSame(1,Access::table('Apontamentos')->find()->count());
        $this->assertSame(85.0,\App\Service\OrderProgress::status($this->activities[0])['percentual_atual']);
    }    public function testOnlyAdminChangesHoursAndAuditKeepsBeforeAndAfter(): void
    {
        $id=$this->entries->save($this->admin,$this->input());$data=$this->input()+['motivo'=>'Ajuste de horário'];$data['fim']='2026-10-21T12:00';
        try{$this->entries->save($this->pcm,$data,$id);$this->fail();}catch(\Cake\Http\Exception\ForbiddenException){$this->assertSame(240,Access::table('Apontamentos')->get($id)->minutos);}
        $this->authenticate($this->pcm);$this->get('/apontamentos/view/'.$id);$this->assertResponseNotContains('Editar hora');
        $this->enableCsrfToken();$this->post('/apontamentos/edit/'.$id,$data);$this->assertResponseCode(403);
        $this->authenticate($this->admin);$this->get('/apontamentos/view/'.$id);$this->assertResponseContains('Editar hora');
        // Completion may change later; correcting an existing record remains possible.
        \App\Test\Support\CompletedOrder::set($this->activities[0],$this->admin,85);
        $this->entries->save($this->admin,$data,$id);$this->assertSame(300,Access::table('Apontamentos')->get($id)->minutos);
        $audit=Access::table('Auditorias')->find()->where(['apontamento_id'=>$id,'acao'=>'correcao'])->firstOrFail();
        $this->assertSame($this->admin,(int)$audit->usuario_id);$this->assertSame(240,json_decode($audit->antes,true)['minutos']);$this->assertSame(300,json_decode($audit->depois,true)['minutos']);$this->assertNotNull($audit->created);
    }
    private function supply(string $quantity='2,5'): array
    {
        return ['funcionario_chave'=>'pessoa-1','funcionario_rotulo'=>'Pessoa selecionada','data'=>'26/10/2026','material'=>'Rolamento','quantidade'=>$quantity];
    }
    public function testSuppliesBatchPreservesEmployeeAndQuantityWithAudit(): void
    {
        \App\Test\Support\CompletedOrder::set($this->activities[0],$this->admin,0);
        $a=$this->supply();$b=$this->supply('3');$b['funcionario_chave']='pessoa-2';$b['material']='Parafuso';
        $ids=(new \App\Service\Insumos(new FakeTotvs()))->saveBatch($this->admin,['atividade_id'=>$this->activities[0],'insumos'=>[$a,$b]]);
        $this->assertCount(2,$ids);$row=Access::table('Insumos')->get($ids[0]);$this->assertSame(2.5,(float)$row->quantidade);$this->assertSame('2026-10-26',$row->data->format('Y-m-d'));$this->assertSame($this->activities[0],(int)$row->atividade_id);
        $this->assertSame(2,Access::table('Auditorias')->find()->where(['acao'=>'insumo_criacao'])->count());
        $this->get('/insumos');$this->assertResponseOk();$this->assertResponseContains('Rolamento');$this->assertResponseContains('Parafuso');
    }
    public function testSuppliesInvalidBatchSavesNothingAndKeepsFormValues(): void
    {
        $this->mockService(\App\Integration\Totvs\GatewayInterface::class,fn()=>new FakeTotvs());
        foreach(['0','-1','1.12345','abc'] as $quantity){
            try{(new \App\Service\Insumos(new FakeTotvs()))->saveBatch($this->admin,['atividade_id'=>$this->activities[0],'insumos'=>[$this->supply(),$this->supply($quantity)]]);$this->fail();}
            catch(\App\Service\BatchEntryException $e){$this->assertArrayHasKey(1,$e->errors);}
            $this->assertSame(0,Access::table('Insumos')->find()->count());
        }
        $this->enableCsrfToken();$this->post('/insumos/add',['atividade_id'=>$this->activities[0],'insumos'=>[$this->supply(),$this->supply('0')]]);
        $this->assertResponseOk();$this->assertResponseContains('Rolamento');$this->assertResponseContains('Pessoa selecionada');$this->assertResponseContains('Quantidade deve ser maior que zero');
        $table=Access::table('Insumos');$calls=0;
        $listener=function()use(&$calls):void{if(++$calls===2)throw new \RuntimeException('Falha simulada na segunda linha');};
        $table->getEventManager()->on('Model.beforeSave',$listener);
        try{(new \App\Service\Insumos(new FakeTotvs()))->saveBatch($this->admin,['atividade_id'=>$this->activities[0],'insumos'=>[$this->supply(),$this->supply()]]);$this->fail();}
        catch(\RuntimeException $e){$this->assertSame('Falha simulada na segunda linha',$e->getMessage());}
        finally{$table->getEventManager()->off('Model.beforeSave',$listener);}
        $this->assertSame(0,$table->find()->count());$this->assertSame(0,Access::table('Auditorias')->find()->where(['acao'=>'insumo_criacao'])->count());
    }
    public function testSuppliesPermissionsDatesAndEmployeeAreValidated(): void
    {
        $service=new \App\Service\Insumos(new FakeTotvs());
        try{$service->saveBatch($this->responsavel,['atividade_id'=>$this->activities[1],'insumos'=>[$this->supply()]]);$this->fail();}catch(\Cake\Http\Exception\ForbiddenException){$this->assertSame(0,Access::table('Insumos')->find()->count());}
        $row=$this->supply();$row['data']='31/02/2026';
        try{$service->saveBatch($this->admin,['atividade_id'=>$this->activities[0],'insumos'=>[$row]]);$this->fail();}catch(\App\Service\BatchEntryException $e){$this->assertArrayHasKey(0,$e->errors);}
        $gateway=$this->createMock(\App\Integration\Totvs\GatewayInterface::class);$gateway->method('order')->willReturn((new FakeTotvs())->order('os-0'));$person=(new FakeTotvs())->employee('pessoa-1');$person['ativo']=false;$gateway->method('employee')->willReturn($person);
        $this->expectException(\App\Service\BatchEntryException::class);(new \App\Service\Insumos($gateway))->saveBatch($this->admin,['atividade_id'=>$this->activities[0],'insumos'=>[$this->supply()]]);
    }
    public function testSuppliesHttpFormAndSingleSave(): void
    {
        $this->mockService(\App\Integration\Totvs\GatewayInterface::class,fn()=>new FakeTotvs());
        $this->get('/insumos/add');$this->assertResponseOk();$this->assertResponseContains('Salvar insumos');$this->assertResponseContains('name="insumos[0][funcionario_chave]"');
        if(getenv('BATCH_PREVIEW'))file_put_contents(TMP.'supplies-preview.html',(string)$this->_response->getBody());
        $this->enableCsrfToken();$this->post('/insumos/add',['atividade_id'=>$this->activities[0],'quantidade_linhas'=>'1','insumos'=>[$this->supply()]]);$this->assertRedirect('/insumos');$this->assertSame(1,Access::table('Insumos')->find()->count());
    }
    private function splitInput(array $hours=['07:00','11:00','12:00','17:00']): array
    {
        return ['atividade_id'=>$this->activities[0],'funcionario_chave'=>'pessoa-1','data_servico'=>'21/10/2026','hora_inicio_manha'=>$hours[0],'hora_fim_manha'=>$hours[1],'hora_inicio_tarde'=>$hours[2],'hora_fim_tarde'=>$hours[3],'descricao'=>'Dois períodos'];
    }
    public function testSplitPeriodsSaveAuditPcmAndAdminCorrection(): void
    {
        $id=$this->entries->save($this->responsavel,$this->splitInput());
        $row=Access::table('Apontamentos')->get($id);
        $this->assertSame(540,$row->minutos);
        $this->assertSame(1,Access::table('Apontamentos')->find()->count());
        $audit=Access::table('Auditorias')->find()->where(['apontamento_id'=>$id])->firstOrFail();
        $this->assertStringContainsString('inicio_tarde',$audit->depois);
        // The lunch gap remains available to other entries for this employee.
        $gap=$this->entries->save($this->responsavel,$this->input('2026-10-21T11:00','2026-10-21T12:00'));
        $this->assertGreaterThan(0,$gap);
        $this->get('/apontamentos/view/'.$id);$this->assertResponseOk();$this->assertResponseContains('Manhã:');$this->assertResponseContains('Tarde:');$this->assertResponseContains('9,00');
        $this->get('/fila-pcm/os/'.$this->activities[0]);$this->assertResponseOk();$this->assertResponseContains('Manhã:');$this->assertResponseContains('Tarde:');$this->assertResponseContains('9,00h');
        $changed=$this->splitInput(['07:00','10:00','12:00','16:00'])+['motivo'=>'Correção administrativa'];
        $this->entries->save($this->admin,$changed,$id);
        $this->assertSame(420,Access::table('Apontamentos')->get($id)->minutos);
        $this->expectException(\Cake\Http\Exception\ForbiddenException::class);
        $this->entries->save($this->pcm,$this->splitInput()+['motivo'=>'Sem permissão'],$id);
    }
    public function testSplitPeriodsConflictAndBatchAtomicity(): void
    {
        $this->entries->save($this->responsavel,$this->input('2026-10-21T13:00','2026-10-21T14:00'));
        try { $this->entries->save($this->responsavel,$this->splitInput());$this->fail('Conflito esperado'); }
        catch (ConflictException $e) { $this->assertNotEmpty($e->conflicts); }
        $rows=[$this->splitInput(['07:00','11:00','','']),$this->splitInput(['','','13:00','15:00'])];
        try {$this->entries->saveBatch($this->responsavel,['atividade_id'=>$this->activities[0],'apontamentos'=>$rows]);$this->fail('Lote deveria falhar');}
        catch (\App\Service\BatchEntryException $e) {$this->assertSame(1,Access::table('Apontamentos')->find()->count());}
    }
    public function testSplitPeriodsBatchAllowsGapAndDifferentEmployees(): void
    {
        $rows=[$this->splitInput(),$this->splitInput(['11:00','12:00','','']),array_replace($this->splitInput(),['funcionario_chave'=>'pessoa-2'])];
        $ids=$this->entries->saveBatch($this->responsavel,['atividade_id'=>$this->activities[0],'apontamentos'=>$rows]);
        $this->assertCount(3,$ids);
        $this->assertSame(1140,(int)Access::table('Apontamentos')->find()->select(['total'=>Access::table('Apontamentos')->find()->func()->sum('minutos')])->first()->total);
    }
    public function testSplitPeriodsSinglePeriodPersistenceAndLegacyPermissions(): void
    {
        foreach ([[['07:00','11:00','',''],240],[['','','12:00','14:00'],120]] as [$hours,$minutes]) {
            $id=$this->entries->save($this->responsavel,$this->splitInput($hours));
            $this->assertSame($minutes,Access::table('Apontamentos')->get($id)->minutos);
            $this->get('/apontamentos/edit/'.$id);$this->assertResponseOk();
            $this->entries->save($this->pcm,$this->splitInput($hours)+['motivo'=>'Revisão da descrição'],$id);
        }
        $legacy=$this->entries->save($this->responsavel,$this->input('2026-10-22T07:00','2026-10-22T11:00'));
        $input=$this->splitInput(['07:00','11:00','','']);$input['data_servico']='22/10/2026';$input['motivo']='Descrição do legado';
        $this->entries->save($this->pcm,$input,$legacy);
        $this->assertSame(240,Access::table('Apontamentos')->get($legacy)->minutos);
    }
}
