<?php
declare(strict_types=1);
namespace App\Test\TestCase\Service;

use App\Integration\Totvs\{ListOrders,ListOrderQueries,Orders,Queries,GatewayInterface};
use App\Service\{Access,Users,ListLinks,ClassificacaoCurva,Curva,Evolucao,TimeEntries,ConflictException};
use Cake\TestSuite\{TestCase,IntegrationTestTrait};

class CurvaReorganizacaoTest extends TestCase
{
    use IntegrationTestTrait;
    private int $admin;private int $computer;private int $season;private array $sectors=[];private array $books=[];private array $areas=[];private array $sourceRows=[];private array $calls=[];
    protected function setUp(): void
    {
        parent::setUp();
        foreach(['EvolucaoPesos','EvolucaoMetas','EvolucaoRegistros','EvolucaoLotes','EvolucaoVersoes','CalendariosOperacionais','EvolucaoSemanas','VinculosItens','VinculosLotes','Auditorias','Apontamentos','Atividades','UsuariosSetores','Usuarios','Cadernos','Setores','AreasFabrica','Entressafras'] as $alias)Access::table($alias)->deleteAll([]);
        $stamp=gmdate('Y-m-d H:i:s');$t=Access::table('Usuarios');$this->admin=(int)$t->saveOrFail(Access::entity($t,['nome'=>'Admin curva','login'=>'curva-admin','senha_hash'=>password_hash('teste123',PASSWORD_DEFAULT),'perfil'=>'ADMIN_PCM','ativo'=>true,'created'=>$stamp,'modified'=>$stamp]))->id;
        $t=Access::table('Entressafras');$this->season=(int)$t->saveOrFail($t->newEntity(['nome'=>'Curva teste','data_inicio'=>'2026-10-05','data_fim'=>'2027-04-10','status'=>'ativa']))->id;
        foreach(['MECANI','ELETRI'] as $i=>$code){$t=Access::table('Setores');$this->sectors[]=(int)$t->saveOrFail($t->newEntity(['nome'=>$i?'Elétrica':'Mecânica','ativo'=>true,'area_totvs'=>$code,'filial_totvs'=>'01']))->id;
            $t=Access::table('Cadernos');$this->books[]=(int)$t->saveOrFail($t->newEntity(['nome'=>$i?'CALDEIRARIA - MOENDA':'MOENDA','entressafra_id'=>$this->season,'setor_id'=>$this->sectors[$i],'ativo'=>true]))->id;}
        foreach(['Moenda','Destilaria'] as $name){$t=Access::table('AreasFabrica');$this->areas[]=(int)$t->saveOrFail(Access::entity($t,['nome'=>$name]))->id;}
        $this->computer=(new Users())->save($this->admin,['nome'=>'Computador Mecânica','login'=>'curva-computer','senha'=>'teste123','perfil'=>'RESPONSAVEL_SETOR','computador_operacional'=>'1','ativo'=>'1','setores'=>[$this->sectors[0]]]);
        $this->auth($this->admin);
    }
    private function auth(int $id): void {$u=Access::actor($id);$this->session(['Periodo'=>['id'=>$this->season,'usuario_id'=>$id],'Auth'=>['id'=>$id,'stamp'=>hash('sha256',$u->senha_hash),'last'=>time()]]);}
    private function row(int $number,string $area='MECANI'): array
    {
        return ['numero'=>sprintf('%06d',$number),'filial'=>'01','codigo_servico'=>'2425ME','codigo_area'=>$area,'centro_custo'=>'','data_origem'=>'20261001','situacao'=>'L','termino'=>'N','bem'=>'BEM '.$number,'servico'=>'ENTRESSAFRA realista teste','correspondencias_servico'=>1,'identidades'=>1];
    }
    private function local(int $number,?int $book=null,string $area='MECANI',?int $season=null): int
    {
        if($book){$links=Access::table('CadernosSetores');$sector=$this->sectors[$area==='MECANI'?0:1];if(!$links->exists(['caderno_id'=>$book,'setor_id'=>$sector]))$links->saveOrFail(Access::entity($links,['caderno_id'=>$book,'setor_id'=>$sector]));}
        $row=Orders::mapRow($this->row($number,$area));$t=Access::table('Atividades');$stamp=gmdate('Y-m-d H:i:s');
        return (int)$t->saveOrFail(Access::entity($t,['entressafra_id'=>$season??$this->season,'caderno_id'=>$book,'setor_executante_id'=>$this->sectors[$area==='MECANI'?0:1],'area_executante_totvs'=>$area,'identificacao_setor'=>'area_totvs','os_numero'=>$row['numero'],'os_filial'=>'01','os_chave'=>$row['chave'],'servico'=>$row['servico'],'bem'=>$row['bem'],'situacao_totvs'=>'L','disponivel_totvs'=>true,'snapshot_totvs'=>json_encode($row),'criado_por'=>$this->admin,'created'=>$stamp,'modified'=>$stamp]))->id;
    }
    private function source(): ListOrders
    {
        return new ListOrders(function($sql,$params){$this->assertSame(ListOrderQueries::numbers(),$sql);$this->calls[]=$params;$numbers=array_filter($params,fn($key)=>str_starts_with($key,'n'),ARRAY_FILTER_USE_KEY);return array_values(array_filter($this->sourceRows,fn($r)=>in_array($r['numero'],$numbers,true)));});
    }
    private function preview(ListLinks $s,string $text,int $book=0): string
    {
        $token=$s->create($this->admin,$book?:$this->books[0],$text);
        do{$s->prepare($this->admin,$token);}while($s->summary($this->admin,$token)['job']->estado==='preparando');return $token;
    }
    private function finish(ListLinks $s,string $token): void
    {for($i=0;$i<110;$i++){if($s->summary($this->admin,$token)['job']->estado==='concluido')return;$s->process($this->admin,$token);}$this->fail('Processamento não terminou.');}
    private function progress(int $id,float $value,string $date='2026-10-19',int $revision=0,?int $user=null): void
    {(new Evolucao())->saveBatch($user??$this->admin,$this->season,$date,[['atividade_id'=>$id,'percentual'=>$value,'revisao'=>$revision]],bin2hex(random_bytes(16)),true);}
    private function classify(int $book,int $area): void
    {$s=new ClassificacaoCurva();$s->apply($this->admin,$s->preview($this->admin,$this->season,'caderno',[$book],$area,'Conferência administrativa do caderno.'));$this->assertSame($area,(int)Access::table('Cadernos')->getConnection()->execute('SELECT area_curva_id FROM cadernos WHERE id=?',[$book])->fetch('assoc')['area_curva_id']);}
    public function testInputPreservesZerosAndDeduplicatesOnlyInput(): void
    {$r=ListLinks::parse("005587\n005569,005587;005579 005583");$this->assertSame(5,$r['informadas']);$this->assertSame(4,$r['unicas']);$this->assertSame(1,$r['repetidas']);$this->assertSame(['005587','005569','005579','005583'],$r['numbers']);}
    public function testCompletedListReturnsToListingOnlyWithoutPendingResults(): void
    {
        $this->get('/cadernos?q=MOENDA');$this->assertResponseOk();
        $this->assertSession('MOENDA','Cadernos.retorno.query.q');$this->session(['Cadernos'=>$_SESSION['Cadernos']]);
        $this->sourceRows=[$this->row(1)];$service=new ListLinks($this->source());
        $token=$this->preview($service,'000001');$service->confirmEligible($this->admin,$token);
        $url='/cadernos/'.$this->books[0].'/vincular-lista?token='.$token;
        $this->get($url);$this->assertResponseOk();$this->assertResponseContains('data-auto-process="1"');
        $this->finish($service,$token);
        $this->get($url);$this->assertRedirect(['controller'=>'Cadernos','action'=>'index','?'=>['q'=>'MOENDA']]);
        $this->session(['Flash'=>$_SESSION['Flash']]);
        $this->get('/cadernos?q=MOENDA');$this->assertResponseOk();$this->assertResponseContains('1 O.S. vinculada(s)');
        $other=$this->preview($service,'000001 000002');
        // Fixture: the successful part finished but the missing OS still needs review.
        $job=$service->job($this->admin,$other);$job->estado='concluido';Access::table('VinculosLotes')->saveOrFail($job);
        $url='/cadernos/'.$this->books[0].'/vincular-lista?token='.$other;
        $this->get($url);$this->assertResponseOk();$this->assertResponseContains('pendência(s) para revisão');$this->assertResponseContains('000002');$this->assertResponseContains('Voltar à listagem de Cadernos');
        $this->get($url);$this->assertResponseOk();$this->assertSame(1,Access::table('Atividades')->find()->count());
    }
    public function testSingleConfirmationLinksAllEligibleAcrossPagesAndLeavesConflictsUntouched(): void
    {
        for($i=1;$i<=45;$i++)$this->sourceRows[]=$this->row($i);
        $this->sourceRows[3]['termino']='S';
        $other=$this->local(2,$this->books[1]);$same=$this->local(3,$this->books[0]);
        $service=new ListLinks($this->source());$token=$this->preview($service,implode(' ',array_column($this->sourceRows,'numero')).' 000001 000046');
        $url='/cadernos/'.$this->books[0].'/vincular-lista?token='.$token;
        $this->get($url);$this->assertResponseOk();
        $dom=new \DOMDocument();@$dom->loadHTML((string)$this->_response->getBody());$xp=new \DOMXPath($dom);
        $this->assertSame(0,$xp->query('//input[@type="checkbox"]|//input[@name="quantidade"]|//input[@name="justificativa"]|//select')->length);
        $this->assertSame(1,$xp->query('//form[.//input[@name="acao" and @value="confirmar"]]')->length);
        $this->assertResponseContains('Confirmar vínculos');$this->assertResponseContains('/transferir-os');
        $this->assertSame(2,Access::table('Atividades')->find()->count());
        // A stale administrative transfer selection cannot enter initial linking.
        $conflict=Access::table('VinculosItens')->find()->where(['numero'=>'000002'])->firstOrFail();
        $service->select($this->admin,$token,[$conflict->id],false,true);
        $this->enableCsrfToken();$this->post($url,['acao'=>'confirmar']);$this->assertRedirect();
        $summary=$service->summary($this->admin,$token);
        $this->assertSame('processando',$summary['job']->estado);$this->assertSame(42,$summary['counts']['selecionadas']);
        $this->assertFalse((bool)Access::table('VinculosItens')->get($conflict->id)->selecionado);
        $this->post($url,['acao'=>'confirmar']);$this->assertRedirect(); // double click
        $this->finish($service,$token);
        $this->assertSame(44,Access::table('Atividades')->find()->count());
        $this->assertSame($this->books[1],(int)Access::table('Atividades')->get($other)->caderno_id);
        $this->assertSame($this->books[0],(int)Access::table('Atividades')->get($same)->caderno_id);
        $this->assertSame(1,Access::table('Auditorias')->find()->where(['acao'=>'lista_os_confirmada'])->count());
        $this->assertSame(42,$service->summary($this->admin,$token)['counts']['sucesso']);
    }
    public function testSimpleLinkCannotSkipPreviewOrSelectTransfersThroughLegacyPosts(): void
    {
        $service=new ListLinks($this->source());$token=$service->create($this->admin,$this->books[0],'000001');
        $url='/cadernos/'.$this->books[0].'/vincular-lista?token='.$token;$this->enableCsrfToken();
        $this->post($url,['acao'=>'confirmar']);$this->assertResponseOk();$this->assertSame('preparando',$service->job($this->admin,$token)->estado);
        $this->post($url,['acao'=>'selecionar','transferir'=>'1','ids'=>[1]]);$this->assertResponseOk();
        $this->assertSame(0,Access::table('Atividades')->find()->count());
        $this->get('/cadernos/'.$this->books[0].'/transferir-os?token='.$token);$this->assertResponseOk();$this->assertResponseContains('Transferência administrativa');
        $this->auth($this->computer);$this->get('/cadernos/'.$this->books[0].'/transferir-os?token='.$token);$this->assertResponseCode(403);
    }
    public function testRealRenderedPaginationAndContinuationPreserveNotebookTokenAndPeriod(): void
    {
        foreach([1,10,45,105] as $size){
            $this->sourceRows=[];for($i=1;$i<=$size;$i++)$this->sourceRows[]=$this->row($i);
            $service=new ListLinks($this->source());$text=implode("\n",array_column($this->sourceRows,'numero'));$token=$service->create($this->admin,$this->books[0],$text);
            $base='/cadernos/'.$this->books[0].'/vincular-lista';$url=$base.'?token='.$token;
            $this->get($url);$this->assertResponseOk();$this->assertResponseContains('data-auto-process="1"');$this->assertResponseContains('0/'.$size);
            $this->assertSame(0,$service->summary($this->admin,$token)['total']);
            do{$service->prepare($this->admin,$token);}while($service->summary($this->admin,$token)['job']->estado==='preparando');
            $service->prepare($this->admin,$token); // retry after completed POST is idempotent
            $this->assertSame($size,$service->summary($this->admin,$token)['total']);
            $this->assertSame($text,$service->summary($this->admin,$token)['filters']['texto_original']);
            $pages=(int)ceil($size/10);
            for($page=1;$page<=$pages;$page++){
                $this->get($url.'&pagina='.$page);$this->assertResponseOk();$this->assertResponseContains('Prévia completa');$this->assertSession($this->season,'Periodo.id');
                $dom=new \DOMDocument();@$dom->loadHTML((string)$this->_response->getBody());$xp=new \DOMXPath($dom);
                $this->assertSame(min(10,$size-($page-1)*10),$xp->query('//table/tbody/tr')->length);
                foreach($xp->query('//nav[@aria-label="Paginação"]/a') as $link){
                    $parts=parse_url($link->getAttribute('href'));$this->assertSame($base,$parts['path']);parse_str($parts['query'],$query);$this->assertSame($token,$query['token']);$this->assertContains((int)$query['pagina'],[$page-1,$page+1]);
                }
                foreach($xp->query('//form[@method="post"][.//input[@name="acao"]]') as $form){$this->assertSame($base,parse_url($form->getAttribute('action'),PHP_URL_PATH));parse_str((string)parse_url($form->getAttribute('action'),PHP_URL_QUERY),$query);$this->assertSame($token,$query['token']??null);}
            }
            $this->get($url.'&pagina=2');$this->assertResponseOk();$this->assertSame($size,$service->summary($this->admin,$token)['total']);
            $this->assertSame(0,Access::table('Atividades')->find()->count());
        }
    }
    public function testInvalidPreviewTokensAndFormErrorsAreRecoverableWithoutLosingInput(): void
    {
        $base='/cadernos/'.$this->books[0].'/vincular-lista';
        foreach(['invalid',str_repeat('f',48)] as $token){$this->get($base.'?token='.$token);$this->assertResponseOk();$this->assertResponseContains('Prévia');}
        $this->enableCsrfToken();$this->post($base,['acao'=>'criar','numeros'=>"006183\n062200\nINVALIDO"]);$this->assertResponseOk();$this->assertResponseContains('062200');$this->assertResponseContains('INVALIDO');
        $this->assertSame(0,Access::table('VinculosLotes')->find()->count());
    }
    public function testInterleavedConfirmationWorkersDoNotDuplicateLinks(): void
    {
        for($i=1;$i<=10;$i++)$this->sourceRows[]=$this->row($i);
        $first=new ListLinks($this->source());$token=$this->preview($first,implode(' ',array_column($this->sourceRows,'numero')));
        $first->select($this->admin,$token,[],true);$first->start($this->admin,$token,10,'');$first->start($this->admin,$token,10,'');
        $second=new ListLinks(new ListOrders(function()use($first,$token){
            // The first worker commits after the second reads its pending item list.
            $first->process($this->admin,$token);return $this->sourceRows;
        }));
        $second->process($this->admin,$token);$this->finish($first,$token);
        $this->assertSame(10,Access::table('Atividades')->find()->count());
        $this->assertSame(10,Access::table('Auditorias')->find()->where(['acao'=>'vinculo_caderno_lista'])->count());
        $this->assertSame(1,Access::table('Auditorias')->find()->where(['acao'=>'lista_os_confirmada'])->count());
        $this->assertSame(10,$first->summary($this->admin,$token)['counts']['processadas']);
    }
    public function testParameterizedSourceIsAllowlistedAndRejectsInjectedNumbers(): void
    {
        $this->assertTrue(Queries::allows(ListOrderQueries::numbers()));$this->assertFalse(Queries::allows(ListOrderQueries::numbers().'; DELETE FROM STJ010'));
        $this->expectException(\DomainException::class);ListLinks::parse("005300' OR 1=1");
    }
    public function testPreviewHasAllRequiredResultCategoriesAndDoesNotLink(): void
    {
        foreach([1,2,3,5,6] as $number)$this->sourceRows[]=$this->row($number);
        $this->sourceRows[3]['termino']='S';$this->sourceRows[4]['identidades']=2;
        $this->local(2,$this->books[0]);$this->local(3,$this->books[1]);
        $s=new ListLinks($this->source());$token=$this->preview($s,'000001 000002 000003 000004 000005 000006 000001');$r=$s->summary($this->admin,$token);
        foreach(['elegivel','mesmo','outro','nao_encontrada','inelegivel','ambigua'] as $state)$this->assertSame(1,$r['counts'][$state]);
        $this->assertSame(1,$r['filters']['repetidas']);$this->assertSame(2,Access::table('Atividades')->find()->count());
    }
    public function testHundredsOfNumbersUseBlocksAndSelectionSurvivesPages(): void
    {
        for($i=1;$i<=105;$i++)$this->sourceRows[]=$this->row($i);
        $s=new ListLinks($this->source());$token=$this->preview($s,implode("\n",array_column($this->sourceRows,'numero')));$r=$s->summary($this->admin,$token);
        $this->assertCount(2,$this->calls);$this->assertCount(10,$r['rows']);$this->assertSame(11,$r['pages']);
        $s->select($this->admin,$token,[],true);$this->assertSame(105,$s->summary($this->admin,$token,2)['counts']['selecionadas']);
        try{$s->start($this->admin,$token,10,'');$this->fail();}catch(\DomainException){}
        $s->start($this->admin,$token,105,'');$this->finish($s,$token);$this->assertSame(105,Access::table('Atividades')->find()->count());
        $s->process($this->admin,$token);$this->assertSame(105,Access::table('Atividades')->find()->count());
    }
    public function testTransferRequiresExplicitSelectionAndReasonAndPreservesHistory(): void
    {
        $this->sourceRows[]=$this->row(1);$id=$this->local(1,$this->books[1]);$this->progress($id,10);
        $before=(new Evolucao())->history($id);$s=new ListLinks($this->source());$token=$this->preview($s,'000001');
        $s->select($this->admin,$token,[],true);$this->assertSame(0,$s->summary($this->admin,$token)['counts']['selecionadas']??0);
        $item=$s->summary($this->admin,$token)['rows']->first();$s->select($this->admin,$token,[$item->id],false,true);
        try{$s->start($this->admin,$token,1,'');$this->fail();}catch(\DomainException){}
        $s->start($this->admin,$token,1,'Caderno incorreto conferido pelo PCM.');$this->finish($s,$token);
        $a=Access::table('Atividades')->get($id);$this->assertSame($this->books[0],(int)$a->caderno_id);$this->assertSame($this->sectors[0],(int)$a->setor_executante_id);
        $this->assertSame($before,(new Evolucao())->history($id));$this->assertSame(1,Access::table('Atividades')->find()->count());
        $this->assertSame(1,Access::table('Auditorias')->find()->where(['acao'=>'transferencia_caderno_lista'])->count());
    }
    public function testChangedSourceOrNotebookLinkIsNotSilentlyTransferred(): void
    {
        $this->sourceRows[]=$this->row(1);$id=$this->local(1);$s=new ListLinks($this->source());$token=$this->preview($s,'000001');$s->select($this->admin,$token,[],true);$s->start($this->admin,$token,1,'');
        $t=Access::table('CadernosSetores');$t->saveOrFail(Access::entity($t,['caderno_id'=>$this->books[1],'setor_id'=>$this->sectors[0]]));
        Access::table('Atividades')->updateAll(['caderno_id'=>$this->books[1]],['id'=>$id]);$this->finish($s,$token);
        $this->assertSame(1,$s->summary($this->admin,$token)['counts']['erro']);$this->assertSame($this->books[1],(int)Access::table('Atividades')->get($id)->caderno_id);
    }
    public function testCurveUsesNotebookAreaAcrossExecutionTeamsWithoutDoubleCount(): void
    {
        $a=$this->local(1,$this->books[0]);$b=$this->local(2,$this->books[1],'ELETRI');$this->local(3);
        $t=Access::table('CadernosSetores');$t->saveOrFail(Access::entity($t,['caderno_id'=>$this->books[0],'setor_id'=>$this->sectors[1]]));
        $this->classify($this->books[0],$this->areas[0]);$this->classify($this->books[1],$this->areas[0]);
        $this->progress($a,10,'2026-10-12');$this->progress($a,80);$this->progress($b,40);
        $r=(new Curva())->report($this->season,(string)$this->areas[0]);$this->assertSame(2,$r['metrics']['total']);$this->assertSame(60.0,$r['metrics']['simples']);$this->assertNull($r['official']);
        $this->assertSame(3,(new Curva())->report($this->season)['metrics']['total']);$this->assertSame(40.0,(new Curva())->report($this->season)['metrics']['simples']);
        $this->assertSame(1,(new Curva())->report($this->season,'pendentes')['metrics']['total']);
        $weekly=array_column($r['points'],'simples','referencia');$this->assertSame(5.0,$weekly['2026-10-12']);$this->assertSame(60.0,$weekly['2026-10-19']);
        $this->assertSame($this->sectors[0],(int)Access::table('Atividades')->get($a)->setor_executante_id);
        $this->get('/controle-pcm?entressafra_id='.$this->season.'&area_id='.$this->areas[0]);$this->assertResponseOk();$this->assertResponseContains('Moenda');$this->assertResponseContains('60,00%');$this->assertResponseNotContains('Classificação física das O.S.');
    }
    public function testUnclassifiedOrdersAreLaunchableAndOverridesHaveOnePrincipalArea(): void
    {
        $id=$this->local(1,$this->books[0]);$this->progress($id,25,user:$this->computer);$this->classify($this->books[0],$this->areas[0]);
        $s=new ClassificacaoCurva();$s->apply($this->admin,$s->preview($this->admin,$this->season,'atividades',[$id],$this->areas[1],'Exceção individual conferida.'));
        $this->assertSame(0,(new Curva())->report($this->season,(string)$this->areas[0])['metrics']['total']);$this->assertSame(1,(new Curva())->report($this->season,(string)$this->areas[1])['metrics']['total']);
        $this->assertSame(1,(new Curva())->report($this->season)['metrics']['total']);
    }
    public function testAdministrativePreviewRejectsStaleOrTamperedPlan(): void
    {
        $s=new ClassificacaoCurva();$plan=$s->preview($this->admin,$this->season,'caderno',[$this->books[0]],$this->areas[0],'Conferência.');
        $plan['area']=$this->areas[1];$this->expectException(\DomainException::class);$s->apply($this->admin,$plan);
    }
    public function testQuickProgressTenToEightyUsesConfirmedIdAndPreservesSameWeekRevisions(): void
    {
        $id=$this->local(5300);$this->progress($id,10,user:$this->computer);$this->auth($this->computer);$this->enableCsrfToken();
        $this->post('/lancar-porcentagem',['atividade_id'=>$id,'referencia'=>'2026-10-19','revisao'=>1,'percentual'=>80,'chave'=>bin2hex(random_bytes(16))]);$this->assertResponseOk();$this->assertResponseContains('lançamento individual foi encerrado');
        $this->assertSame(10.0,(new Evolucao())->state($id,'2026-10-19')['percentual']);$this->assertCount(1,(new Evolucao())->history($id));
        $this->get('/lancar-porcentagem');$this->assertResponseOk();$this->assertResponseNotContains('id="os-pesquisa"');
    }
    public function testCurveAndHistoryUseTenRecordsPerBackendPage(): void
    {
        for($i=1;$i<=25;$i++)$this->local($i,$this->books[0]);$this->classify($this->books[0],$this->areas[0]);
        $r=(new Curva())->report($this->season,(string)$this->areas[0],page:2);$this->assertCount(10,$r['details']);$this->assertSame('000011',$r['details'][0]['os_numero']);$this->assertSame(25,$r['metrics']['total']);
        $id=(int)$r['details'][0]['id'];for($i=0;$i<12;$i++)$this->progress($id,(float)$i,revision:$i);
        $history=(new Curva())->historyPage($id);$this->assertCount(10,$history['rows']);$this->assertSame(12,$history['count']);$this->assertCount(2,(new Curva())->historyPage($id,2)['rows']);
        $this->get('/controle-pcm?entressafra_id='.$this->season.'&area_id='.$this->areas[0].'&pagina=2');$this->assertResponseOk();$this->assertSame(10,substr_count((string)$this->_response->getBody(),'<tr data-os-row>'));$this->assertResponseContains('area_id=');
    }
    public function testElectricalDemonstrationDoesNotWriteAnyOfficialValues(): void
    {
        $id=$this->local(1,$this->books[1],'ELETRI');$this->progress($id,55);$before=(new Evolucao())->history($id);$audit=Access::table('Auditorias')->find()->count();
        $r=(new Curva())->report($this->season,demo:true);$this->assertSame([30.0,40.0],array_column($r['points'],'simples'));$this->assertSame(40.0,$r['metrics']['simples']);$this->assertNull($r['official']);
        $this->assertSame($before,(new Evolucao())->history($id));$this->assertSame($audit,Access::table('Auditorias')->find()->count());$this->assertSame(55.0,(new Evolucao())->state($id,'2026-10-19')['percentual']);
        $this->get('/controle-pcm?entressafra_id='.$this->season.'&demonstracao=eletrica');$this->assertResponseOk();$this->assertResponseContains('DEMONSTRAÇÃO ISOLADA');$this->assertResponseContains('Encerrar demonstração');
    }
    public function testGlobalRetroactiveConflictIncludesOtherSeasonsAndEveryConflictingEntry(): void
    {
        $a=$this->local(1);$t=Access::table('Entressafras');$other=(int)$t->saveOrFail($t->newEntity(['nome'=>'Outra entressafra','data_inicio'=>'2026-10-01','data_fim'=>'2027-04-10','status'=>'planejamento']))->id;$b=$this->local(2,null,'ELETRI',$other);
        $gateway=$this->createMock(GatewayInterface::class);$gateway->method('employee')->willReturn(['chave'=>'000001','nome'=>'Pessoa','matricula'=>'000001','filial'=>'01','vinculo'=>'validado','ativo'=>true]);$s=new TimeEntries($gateway);
        foreach([$a,$b] as $activityId)\App\Test\Support\CompletedOrder::set($activityId,$this->admin);
        $save=fn($id,$from,$to)=>$s->save($this->admin,['atividade_id'=>$id,'funcionario_chave'=>'000001','inicio'=>$from,'fim'=>$to,'descricao'=>'Execução histórica']);
        $save($a,'2026-10-01T07:00','2026-10-01T09:00');$save($b,'2026-10-01T10:00','2026-10-01T12:00');
        $conflicts=$s->conflicts('000001','2026-10-01 11:00:00','2026-10-01 14:00:00');$this->assertCount(2,$conflicts);
        $save($a,'2026-10-02T07:00','2026-10-02T09:00');
        // Fixture histórica com virada de dia, preservada pelas consultas de conflitos.
        $historic=$save($a,'2026-10-02T23:00','2026-10-02T23:30');
        Access::table('Apontamentos')->updateAll(['fim'=>'2026-10-03 05:00:00','minutos'=>180],['id'=>$historic]);
        $this->expectException(ConflictException::class);$save($b,'2026-10-03T01:00','2026-10-03T03:00');
    }
    public function testComputerCannotOrganizeClassifyTransferOrSimulate(): void
    {
        $this->auth($this->computer);foreach(['/organizacao-curva','/organizacao-curva/previa','/cadernos/'.$this->books[0].'/vincular-lista','/controle-pcm?demonstracao=eletrica'] as $path){$this->get($path);$this->assertResponseCode(403);}
        $this->expectException(\Cake\Http\Exception\ForbiddenException::class);(new ListLinks($this->source()))->create($this->computer,$this->books[0],'000001');
    }
    public function testAdministrativePagesAreAvailableAndPostsRequireCsrf(): void
    {
        foreach(['/organizacao-curva?entressafra_id='.$this->season.'&caderno_id='.$this->books[0],'/cadernos/view/'.$this->books[0],'/cadernos/'.$this->books[0].'/vincular-lista'] as $path){$this->get($path);$this->assertResponseOk();$this->assertResponseContains('Vincular O.S.');}
        $this->post('/organizacao-curva',['tipo'=>'caderno','area_curva_id'=>$this->areas[0]]);$this->assertResponseCode(403);
    }
    public function testPartialWeightPagePreservesOtherValuesAndFrozenVersions(): void
    {
        $a=$this->local(1);$b=$this->local(2);$s=new Evolucao();$version=$s->version($this->admin,$this->season,'pesos','Original',[$a=>2,$b=>3],true);
        $this->enableCsrfToken();$this->post('/evolucao/configuracao?entressafra_id='.$this->season,['acao'=>'versao','tipo'=>'pesos','nome'=>'Correção de uma página','parcial'=>'1','base_versao'=>(string)$version,'valores'=>[$a=>4]]);$this->assertRedirect();
        $versions=$s->versions($this->season,'pesos');$this->assertCount(2,$versions);$latest=json_decode($versions[0]['conteudo'],true);$this->assertEquals(4,$latest[$a]);$this->assertEquals(3,$latest[$b]);$this->assertEquals(2,json_decode($versions[1]['conteudo'],true)[$a]);
        $this->post('/evolucao/configuracao?entressafra_id='.$this->season,['acao'=>'versao','tipo'=>'pesos','nome'=>'Página antiga','parcial'=>'1','base_versao'=>(string)$version,'valores'=>[$b=>9]]);$this->assertResponseOk();$this->assertResponseContains('Outra pessoa alterou os pesos');$this->assertCount(2,$s->versions($this->season,'pesos'));
        try{$s->version($this->admin,$this->season,'pesos','Corrida entre páginas',[$a=>9,$b=>9],baseVersion:$version);$this->fail('A versão deve ser conferida também dentro do bloqueio transacional.');}catch(\DomainException $e){$this->assertStringContainsString('Outra pessoa alterou',$e->getMessage());}
        $this->assertCount(2,$s->versions($this->season,'pesos'));
    }
    public function testSourceOutageCanResumePreviewWithoutDuplicateItems(): void
    {
        $fail=true;$source=new ListOrders(function()use(&$fail){if($fail)throw new \RuntimeException('offline');return [$this->row(1)];});$s=new ListLinks($source);$token=$s->create($this->admin,$this->books[0],'000001');
        try{$s->prepare($this->admin,$token);$this->fail();}catch(\DomainException){}
        $this->assertSame('preparando',$s->summary($this->admin,$token)['job']->estado);$this->assertSame(0,$s->summary($this->admin,$token)['total']);
        $fail=false;$s->prepare($this->admin,$token);$s->prepare($this->admin,$token);$this->assertSame(1,$s->summary($this->admin,$token)['total']);
    }
    public function testClosedSourceAfterPreviewCannotCreateLocalActivity(): void
    {
        $this->sourceRows[]=$this->row(1);$s=new ListLinks($this->source());$token=$this->preview($s,'000001');$s->select($this->admin,$token,[],true);$s->start($this->admin,$token,1,'');$this->sourceRows[0]['termino']='S';$this->finish($s,$token);
        $this->assertSame(1,$s->summary($this->admin,$token)['counts']['erro']);$this->assertSame(0,Access::table('Atividades')->find()->count());
    }
}
