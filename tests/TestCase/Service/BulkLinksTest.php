<?php
declare(strict_types=1);
namespace App\Test\TestCase\Service;

use App\Integration\Totvs\BulkOrderQueries;
use App\Integration\Totvs\BulkOrders;
use App\Integration\Totvs\GatewayInterface;
use App\Integration\Totvs\Orders;
use App\Service\Access;
use App\Service\Activities;
use App\Service\BulkLinks;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class BulkLinksTest extends TestCase
{
    use IntegrationTestTrait;
    private int $user;
    private array $input;
    private array $rows=[];
    private BulkLinks $bulk;
    private GatewayInterface $gateway;
    protected function setUp(): void
    {
        parent::setUp();
        foreach(['EvolucaoPesos','EvolucaoMetas','EvolucaoRegistros','EvolucaoLotes','EvolucaoVersoes','CalendariosOperacionais','EvolucaoSemanas','VinculosItens','VinculosLotes','Auditorias','Apontamentos','Atividades','UsuariosSetores','Usuarios','Cadernos','Setores','AreasFabrica','Entressafras'] as $alias) { Access::table($alias)->deleteAll([]); }
        $now=gmdate('Y-m-d H:i:s'); $table=Access::table('Usuarios');
        $this->user=(int)$table->saveOrFail(Access::entity($table,['nome'=>'PCM teste','login'=>'bulk-test','senha_hash'=>password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT),'perfil'=>'PCM','ativo'=>true,'created'=>$now,'modified'=>$now]))->id;
        $table=Access::table('Entressafras'); $season=$table->saveOrFail($table->newEntity(['nome'=>'2026/2027','data_inicio'=>'2026-01-01','data_fim'=>'2027-12-31','status'=>'ativa']));
        $table=Access::table('Setores'); $sector=$table->saveOrFail($table->newEntity(['nome'=>'Mecânica','ativo'=>true,'area_totvs'=>'MECANI','filial_totvs'=>'01']));
        $table=Access::table('Cadernos'); $notebook=$table->saveOrFail($table->newEntity(['nome'=>'Caderno teste','setor_id'=>$sector->id,'entressafra_id'=>$season->id,'ativo'=>true]));
        $this->input=['entressafra_id'=>(string)$season->id,'setor_id'=>(string)$sector->id,'caderno_id'=>(string)$notebook->id,'servicos'=>['2425ME','ESMECA']];
        $this->rows=[];
        for ($i=1;$i<=75;$i++) { $row=$this->row($i); $this->rows[$row['chave']]=$row; }
        $source=new BulkOrders(function($sql,$params) {
            if ($sql===BulkOrderQueries::services()) { return [['codigo'=>'2425ME','nome'=>'ENTRESSAFRA antigo','quantidade'=>38],['codigo'=>'ESMECA','nome'=>'ENTRESSAFRA atual','quantidade'=>37]]; }
            $this->assertSame(BulkOrderQueries::page(),$sql);
            return array_slice(array_values(array_filter($this->rows,fn($r)=>$r['numero']>$params['cursor'])),0,201);
        });
        $this->gateway=$this->createMock(GatewayInterface::class);
        $this->gateway->method('order')->willReturnCallback(function($key) {
            if (!isset($this->rows[$key])) { throw new \DomainException('O.S. não encontrada.'); }
            return $this->rows[$key];
        });
        $this->bulk=new BulkLinks($source,$this->gateway);
        $this->mockService(BulkLinks::class,fn()=>$this->bulk);
        $user=Access::actor($this->user);
        $this->session(['Periodo'=>['id'=>(int)$season->id,'usuario_id'=>$this->user],'Auth'=>['id'=>$this->user,'stamp'=>hash('sha256',$user->senha_hash),'last'=>time()]]);
    }
    private function row(int $i): array
    {
        $number=str_pad((string)$i,6,'0',STR_PAD_LEFT);
        return ['chave'=>Orders::key('01',$number),'numero'=>$number,'filial'=>'01','servico'=>'ENTRESSAFRA teste','bem'=>'BEM'.($i%2),'codigo_servico'=>$i%2?'2425ME':'ESMECA','codigo_area'=>'MECANI','centro_custo'=>'4101006','situacao'=>'L','termino'=>'N','data_origem'=>'20260101','correspondencias_servico'=>1,'identidades'=>1,'disponivel'=>true];
    }
    private function preview(): string
    { $token=$this->bulk->create($this->user,$this->input); do { $state=$this->bulk->prepare($this->user,$token); } while ($state['estado']==='preparando'); return $token; }
    private function all(string $token): void
    {
        $state=$this->bulk->summary($this->user,$token);
        $this->bulk->select($this->user,$token,['todas'=>true,'selecionado'=>true,'quantidade_confirmada'=>$state['elegiveis_filtradas']]);
    }
    public function testPreviewOnlyAndAllPagesSelectionWithOldAndCurrentServices(): void
    {
        $token=$this->preview(); $state=$this->bulk->summary($this->user,$token);
        $this->assertSame(75,$state['contagens']['encontradas']); $this->assertCount(10,$state['rows']); $this->assertSame(8,$state['paginas']);
        $this->assertSame(0,Access::table('Atividades')->find()->count());
        $this->all($token); $state=$this->bulk->summary($this->user,$token,'',2);
        $this->assertSame(75,$state['contagens']['selecionadas']); $this->assertCount(10,$state['rows']);
        $this->assertTrue($state['rows'][0]['selecionado']);
        $this->assertEqualsCanonicalizing(['2425ME','ESMECA'],array_unique(array_column(array_column($state['rows'],'os'),'codigo_servico')));
    }
    public function testSourcePaginationAndSelectionNeverStopAtFirstPage(): void
    {
        for ($i=76;$i<=240;$i++) { $row=$this->row($i); $this->rows[$row['chave']]=$row; }
        $token=$this->bulk->create($this->user,$this->input);
        $state=$this->bulk->prepare($this->user,$token);
        $this->assertSame('preparando',$state['estado']); $this->assertSame(200,$state['contagens']['encontradas']);
        $state=$this->bulk->prepare($this->user,$token);
        $this->assertSame('pronto',$state['estado']); $this->assertSame(240,$state['contagens']['encontradas']);
        $this->all($token); $this->assertSame(240,$this->bulk->summary($this->user,$token)['contagens']['selecionadas']);
    }
    public function testSelectedSubsetIsRevalidatedAuditedAndRepeatedProcessIsIdempotent(): void
    {
        $token=$this->preview(); $state=$this->bulk->summary($this->user,$token);
        $ids=array_column(array_slice($state['rows'],0,2),'id');
        $this->bulk->select($this->user,$token,['ids'=>$ids,'selecionado'=>true]);
        $this->bulk->start($this->user,$token,2);
        $key=$state['rows'][1]['os']['chave']; $this->rows[$key]['termino']='S';
        $state=$this->bulk->process($this->user,$token);
        $this->assertSame('concluido',$state['estado']); $this->assertSame(1,$state['contagens']['vinculadas']); $this->assertSame(1,$state['contagens']['falhas']);
        $this->assertSame(1,Access::table('Atividades')->find()->count());
        foreach (['vinculo_os','item_vinculo_lote','inicio_vinculo_lote','fim_vinculo_lote'] as $action) { $this->assertSame(1,Access::table('Auditorias')->find()->where(['acao'=>$action,'usuario_id'=>$this->user])->count()); }
        $this->bulk->process($this->user,$token); $this->assertSame(1,Access::table('Atividades')->find()->count());
    }
    public function testExistingLinkAndSourceAmbiguityAreBlockedWithoutTransfer(): void
    {
        $first=reset($this->rows); $activity=(new Activities($this->gateway))->link($this->user,(int)$this->input['caderno_id'],$first['chave']);
        $keys=array_keys($this->rows); $this->rows[$keys[1]]['identidades']=2;
        $token=$this->preview(); $state=$this->bulk->summary($this->user,$token);
        $this->assertSame(1,$state['contagens']['ja_vinculadas']); $this->assertSame(1,$state['contagens']['bloqueadas']);
        $this->assertStringContainsString('Caderno teste',$state['rows'][0]['caderno_atual']);
        $this->all($token); $this->assertSame(73,$this->bulk->summary($this->user,$token)['contagens']['selecionadas']);
        $this->assertSame((int)$this->input['caderno_id'],(int)Access::table('Atividades')->get($activity)->caderno_id);
        $this->expectException(\DomainException::class); $this->bulk->select($this->user,$token,['ids'=>[$state['rows'][0]['id']],'selecionado'=>true]);
    }
    public function testWrongAreaCancelledTerminatedAndOtherServiceAreBlocked(): void
    {
        $keys=array_keys($this->rows);
        foreach ([['codigo_area','ELETRI'],['situacao','C'],['situacao','P'],['termino','S'],['codigo_servico','OUTRO']] as $i=>[$field,$value]) { $this->rows[$keys[$i]][$field]=$value; }
        $token=$this->preview(); $this->assertSame(5,$this->bulk->summary($this->user,$token)['contagens']['bloqueadas']);
        $this->all($token); $this->assertSame(70,$this->bulk->summary($this->user,$token)['contagens']['selecionadas']);
    }
    public function testRollbackOfActivityWhenItemAuditFails(): void
    {
        $token=$this->preview(); $state=$this->bulk->summary($this->user,$token);
        $this->bulk->select($this->user,$token,['ids'=>[$state['rows'][0]['id']],'selecionado'=>true]); $this->bulk->start($this->user,$token,1);
        $table=Access::table('Auditorias');
        $listener=function($event,$entity) { if ($entity->acao==='item_vinculo_lote') { throw new \RuntimeException('Falha simulada.'); } };
        $table->getEventManager()->on('Model.beforeSave',$listener);
        try { $result=$this->bulk->process($this->user,$token); }
        finally { $table->getEventManager()->off('Model.beforeSave',$listener); }
        $this->assertSame(1,$result['contagens']['falhas']); $this->assertSame(0,Access::table('Atividades')->find()->count());
        $this->assertSame(0,$table->find()->where(['acao IN'=>['vinculo_os','item_vinculo_lote']])->count());
    }
    public function testConcurrentLinkBetweenPreviewAndSaveBecomesFailure(): void
    {
        $token=$this->preview(); $state=$this->bulk->summary($this->user,$token);
        $this->bulk->select($this->user,$token,['ids'=>[$state['rows'][0]['id']],'selecionado'=>true]); $this->bulk->start($this->user,$token,1);
        (new Activities($this->gateway))->link($this->user,(int)$this->input['caderno_id'],$state['rows'][0]['os']['chave']);
        $state=$this->bulk->process($this->user,$token);
        $this->assertSame(1,$state['contagens']['falhas']); $this->assertSame(1,Access::table('Atividades')->find()->count());
        $this->assertStringContainsString('vinculada',$state['rows'][0]['motivo']);
    }
    public function testExplicitQuantitiesAndServicesCannotBeForged(): void
    {
        $input=$this->input; $input['servicos']=['INVENT'];
        try { $this->bulk->create($this->user,$input); $this->fail(); } catch (\DomainException) { $this->assertSame(0,Access::table('VinculosLotes')->find()->count()); }
        $token=$this->preview();
        try { $this->bulk->select($this->user,$token,['todas'=>true,'selecionado'=>true,'quantidade_confirmada'=>50]); $this->fail(); } catch (\DomainException) { $this->assertSame(0,$this->bulk->summary($this->user,$token)['contagens']['selecionadas']); }
        $this->all($token);
        $this->expectException(\DomainException::class); $this->bulk->start($this->user,$token,1);
    }
    public function testPreviewSearchSelectionAndLiteralWildcards(): void
    {
        $token=$this->preview(); $state=$this->bulk->summary($this->user,$token,'000001');
        $this->assertSame(1,$state['filtradas']);
        $this->bulk->select($this->user,$token,['todas'=>true,'selecionado'=>true,'quantidade_confirmada'=>1,'busca'=>'000001']);
        $this->assertSame(1,$this->bulk->summary($this->user,$token)['contagens']['selecionadas']);
        $this->assertSame(0,$this->bulk->summary($this->user,$token,'%')['filtradas']);
    }
    public function testHttpAccessCsrfAndReadOnlyRequests(): void
    {
        $this->get('/vinculos-lote'); $this->assertResponseOk(); $this->assertResponseContains('Preparar prévia');
        $this->post('/vinculos-lote/api',['acao'=>'criar']+$this->input); $this->assertResponseCode(403);
        $this->get('/vinculos-lote/api?acao=processar'); $this->assertResponseCode(422);
        $table=Access::table('Usuarios'); $user=$table->get($this->user); $user->perfil='RESPONSAVEL_SETOR'; $table->saveOrFail($user);
        $this->get('/vinculos-lote'); $this->assertResponseCode(403);
        $this->get('/vinculos-lote/api?acao=servicos'); $this->assertResponseCode(403);
        $this->session(['Auth'=>[]]); $this->get('/vinculos-lote'); $this->assertRedirect('/login');
    }
    public function testBackendRevalidatesAdditionalFiltersAndDestinationAtSave(): void
    {
        $this->input['cc']='4101'; $this->input['bem']='BEM';
        $token=$this->preview(); $state=$this->bulk->summary($this->user,$token);
        $this->bulk->select($this->user,$token,['ids'=>[$state['rows'][0]['id']],'selecionado'=>true]);
        $this->bulk->start($this->user,$token,1);
        $this->rows[$state['rows'][0]['os']['chave']]['centro_custo']='OUTRO';
        $state=$this->bulk->process($this->user,$token);
        $this->assertSame(1,$state['contagens']['falhas']); $this->assertSame(0,Access::table('Atividades')->find()->count());
        $input=$this->input; $input['setor_id']='999999';
        $this->expectException(\Cake\Datasource\Exception\RecordNotFoundException::class); $this->bulk->create($this->user,$input);
    }
    public function testAnotherPcmCannotUseOwnerTokenOrInventItemSelection(): void
    {
        $token=$this->preview(); $state=$this->bulk->summary($this->user,$token);
        $other=$this->preview();
        try { $this->bulk->select($this->user,$other,['ids'=>[$state['rows'][0]['id']],'selecionado'=>true]); $this->fail(); }
        catch (\DomainException) { $this->assertSame(0,$this->bulk->summary($this->user,$other)['contagens']['selecionadas']); }
        $table=Access::table('Usuarios'); $original=$table->get($this->user); $row=$original->toArray(); unset($row['id']); $row['login']='other-pcm'; $row['senha_hash']=$original->senha_hash;
        $otherUser=$table->saveOrFail(Access::entity($table,$row));
        $this->expectException(\DomainException::class); $this->bulk->summary((int)$otherUser->id,$token);
    }
    public function testLegacyIdentityDoesNotAllowDuplicateNumberAndBranch(): void
    {
        $first=reset($this->rows); $id=(new Activities($this->gateway))->link($this->user,(int)$this->input['caderno_id'],$first['chave']);
        $table=Access::table('Atividades'); $legacy=$table->get($id); $legacy->os_chave='legacy-identity'; $table->saveOrFail($legacy);
        $token=$this->preview(); $this->assertSame(1,$this->bulk->summary($this->user,$token)['contagens']['ja_vinculadas']);
        try { (new Activities($this->gateway))->link($this->user,(int)$this->input['caderno_id'],$first['chave']); $this->fail(); }
        catch (\DomainException) { $this->assertSame(1,$table->find()->count()); }
        $this->assertSame('legacy-identity',$table->get($id)->os_chave);
    }
    public function testBulkLinkUsesAnyAssociatedExecutingSectorWithoutChangingLegacyOwner(): void
    {
        $table=Access::table('Setores'); $sector=$table->saveOrFail($table->newEntity(['nome'=>'Elétrica','ativo'=>true,'area_totvs'=>'ELETRI','filial_totvs'=>'01']));
        $bookTable=Access::table('Cadernos'); $book=$bookTable->get($this->input['caderno_id'],contain:['Setores']); $legacy=$book->setor_id;
        $book=$bookTable->patchEntity($book,['setores'=>['_ids'=>[$legacy,$sector->id]]]); $bookTable->saveOrFail($book);
        $this->input['setor_id']=(string)$sector->id;
        foreach ($this->rows as &$row) { $row['codigo_area']='ELETRI'; } unset($row);
        $token=$this->preview(); $state=$this->bulk->summary($this->user,$token);
        $this->assertSame(75,$state['contagens']['elegiveis']); $this->assertSame('Elétrica',$state['setor_nome']);
        $this->bulk->select($this->user,$token,['ids'=>[$state['rows'][0]['id']],'selecionado'=>true]); $this->bulk->start($this->user,$token,1);
        $state=$this->bulk->process($this->user,$token); $this->assertSame(1,$state['contagens']['vinculadas']);
        $activity=Access::table('Atividades')->find()->firstOrFail();
        $this->assertSame((int)$sector->id,(int)$activity->setor_executante_id); $this->assertSame('ELETRI',$activity->area_executante_totvs);
        $this->assertSame($legacy,$bookTable->get($book->id)->setor_id);
    }
}
