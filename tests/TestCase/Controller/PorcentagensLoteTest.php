<?php
declare(strict_types=1);
namespace App\Test\TestCase\Controller;
use App\Service\{Access,PeriodoContexto,PorcentagensLote,Evolucao,OperacaoRapida,Curva};
use Cake\TestSuite\{TestCase,IntegrationTestTrait};
class PorcentagensLoteTest extends TestCase
{
    use IntegrationTestTrait;
    private int $admin;private int $responsible;private int $season;private array $areas=[],$teams=[],$ids=[];private string $reference;
    protected function setUp(): void
    {
        parent::setUp();PeriodoContexto::set(null);
        foreach(['Insumos','PendenciasOrdens','PendenciasConsultas','EvolucaoPesos','EvolucaoMetas','EvolucaoRegistros','EvolucaoLotes','EvolucaoVersoes','CalendariosOperacionais','EvolucaoSemanas','VinculosItens','VinculosLotes','Auditorias','Apontamentos','Atividades','UsuariosSetores','Usuarios','Cadernos','AreasFabrica','Setores','Entressafras'] as $alias)Access::table($alias)->deleteAll([]);
        $t=Access::table('Usuarios');foreach(['ADMIN_PCM','RESPONSAVEL_SETOR'] as $role){$u=$t->saveOrFail(Access::entity($t,['nome'=>$role,'login'=>$role,'senha_hash'=>password_hash('teste123',PASSWORD_DEFAULT),'perfil'=>$role,'ativo'=>true,'created'=>gmdate('Y-m-d H:i:s'),'modified'=>gmdate('Y-m-d H:i:s')]));if($role==='ADMIN_PCM')$this->admin=(int)$u->id;else $this->responsible=(int)$u->id;}
        $t=Access::table('Entressafras');$this->season=(int)$t->saveOrFail($t->newEntity(['nome'=>'2026/2027','data_inicio'=>'2026-09-01','data_fim'=>'2027-04-10','status'=>'ativa']))->id;
        foreach(['MOENDA','CALDEIRA'] as $name){$t=Access::table('AreasFabrica');$this->areas[]=(int)$t->saveOrFail(Access::entity($t,['nome'=>$name,'entressafra_id'=>$this->season]))->id;}
        foreach(['MECANI','ELETRI','CALDEI'] as $name){$t=Access::table('Setores');$this->teams[]=(int)$t->saveOrFail(Access::entity($t,['nome'=>$name,'area_totvs'=>$name,'filial_totvs'=>'01','ativo'=>true]))->id;}
        $t=Access::table('UsuariosSetores');$t->saveOrFail(Access::entity($t,['usuario_id'=>$this->responsible,'setor_id'=>$this->teams[0]]));
        foreach($this->areas as $area){$t=Access::table('Cadernos');$b=$t->saveOrFail(Access::entity($t,['nome'=>'Caderno '.$area,'entressafra_id'=>$this->season,'area_curva_id'=>$area,'ativo'=>true]));foreach($this->teams as $team){$t=Access::table('CadernosSetores');$t->saveOrFail(Access::entity($t,['caderno_id'=>$b->id,'setor_id'=>$team]));}
            for($i=0;$i<($area===$this->areas[0]?260:1);$i++){$t=Access::table('Atividades');$number=str_pad((string)(6000+count($this->ids)),6,'0',STR_PAD_LEFT);$id=(int)$t->saveOrFail(Access::entity($t,['entressafra_id'=>$this->season,'caderno_id'=>$b->id,'setor_executante_id'=>$this->teams[$i%3],'area_executante_totvs'=>['MECANI','ELETRI','CALDEI'][$i%3],'os_numero'=>$number,'os_filial'=>'01','os_chave'=>\App\Integration\Totvs\Orders::key('01',$number),'servico'=>'ENTRESSAFRA GENERICA','descricao_atividade'=>'Atividade específica '.$number,'bem'=>'Equipamento '.$number,'snapshot_totvs'=>'{}','situacao_totvs'=>'L','disponivel_totvs'=>true,'criado_por'=>$this->admin,'created'=>gmdate('Y-m-d H:i:s'),'modified'=>gmdate('Y-m-d H:i:s')]))->id;$this->ids[]=$id;}
        }
        PeriodoContexto::set($this->season);$this->reference=(new OperacaoRapida())->reference($this->season)[0];$this->login($this->admin);$this->enableCsrfToken('PCM_ENTRESSAFRA_CSRF');
    }
    protected function tearDown(): void {PeriodoContexto::set(null);parent::tearDown();}
    private function login(int $id): void {$u=Access::actor($id);$this->session(['Auth'=>['id'=>$id,'stamp'=>hash('sha256',$u->senha_hash),'last'=>time()],'Periodo'=>['id'=>$this->season,'usuario_id'=>$id]]);}
    private function row(int $index,float $value,float $base=0,int $revision=0): array {return ['atividade_id'=>$this->ids[$index],'percentual'=>$value,'base'=>$base,'revisao'=>$revision,'referencia'=>$this->reference];}
    public function testEquipmentNamesMatchGridAndExcelWithoutChangingCodes(): void
    {
        $table=Access::table('Atividades');
        $codes=['BCE 40 064','EXA 50 001','TOR 40 017'];
        $expected=['BB CARREGAMENTO DE ALCOOL','EXAUSTOR EX-23 101','TORRE DE RESFRIAMENTO DESTILARIA 02'];
        foreach($codes as $i=>$code){
            $a=$table->get($this->ids[$i]);$a->bem=$code;
            $a->snapshot_totvs=$i===2?json_encode(['bem'=>$code,'filial'=>'01','bem_nome'=>$expected[$i]]):'{}';
            $table->saveOrFail($a);
        }
        $equipment=new \App\Service\EquipmentNames(new \App\Integration\Totvs\Equipment(function($sql,$params)use($codes,$expected){
            $this->assertSame('01',$params['filial']);
            $this->assertNotContains($codes[2],array_values($params));
            return [
                ['codigo'=>$codes[0],'filial'=>'01','nome'=>$expected[0]],
                ['codigo'=>$codes[1],'filial'=>'01','nome'=>$expected[1]],
                ['codigo'=>$codes[0],'filial'=>'02','nome'=>'Outra filial'],
            ];
        }));
        $user=Access::actor($this->admin);$now=new \DateTimeImmutable('2026-10-28');
        $grid=(new \App\Service\EvolucaoSemanal($now,null,$equipment))->page($user,$this->season,$this->areas[0],'',1);
        $book=(new \App\Service\ExportarPorcentagens(null,$equipment))->workbook($user,$this->season,$this->areas[0],$now);
        try{
            foreach($codes as $i=>$code){
                $this->assertSame($expected[$i],$grid['rows'][$i]['equipamento']);
                $this->assertSame($grid['rows'][$i]['equipamento'],$book->getActiveSheet()->getCell('C'.($i+6))->getValue());
                $this->assertSame($code,$table->get($this->ids[$i])->bem);
            }
            $this->assertSame($grid['rows'][3]['bem'],$grid['rows'][3]['equipamento']);
            $this->assertSame($grid['rows'][3]['bem'],$book->getActiveSheet()->getCell('C9')->getValue());
        }finally{$book->disconnectWorksheets();}
    }

    public function testRealChecklistDescriptionsInReopenedExcel(): void
    {
        // Exact descriptions read from TOTVS on 2026-11-17, including the full checklist.
        $real=json_decode(file_get_contents(ROOT.'/tests/Fixture/Files/checklist-real-descriptions.json'),true,512,JSON_THROW_ON_ERROR);
        $expected=['006974'=>'FAZER MANUTENÇÃO.','006975'=>'RETIRADA EIXOS DA MESA ALIMENTADORA E MONTAGEM DO EIXO DE ACIONAMNETO DA MESA','007188'=>'FAZER MANUTENÇÃO','007189'=>'FAZER MANUTENÇÃO'];
        $t=Access::table('Atividades');$originals=[];
        foreach(array_keys($real) as $i=>$number){
            $a=$t->get($this->ids[$i]);$a->os_numero=(string)$number;$a->os_chave='01:'.$number;
            $a->descricao_atividade=$i===3?null:$real[$number];$a->snapshot_totvs='{}';
            $t->saveOrFail($a);$originals[$a->id]=[$a->descricao_atividade,$a->snapshot_totvs];
        }
        $source=new \App\Integration\Totvs\ListOrders(static function()use($real):array{
            return [['numero'=>'007189','filial'=>'01','descricao_totvs'=>$real['007189'],'codigo_servico'=>'1','codigo_area'=>'MECANI','centro_custo'=>'CC','data_origem'=>'20260101','situacao'=>'L','termino'=>'N','bem'=>'BEM']];
        });
        $book=(new \App\Service\ExportarPorcentagens($source))->workbook(Access::actor($this->admin),$this->season,$this->areas[0]);
        $path=tempnam(TMP,'real-checklist-');$loaded=null;
        try{
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);
            $loaded=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);$sheet=$loaded->getActiveSheet();$found=[];
            for($r=6;$r<=$sheet->getHighestRow();$r++){
                $number=$sheet->getCell('A'.$r)->getValue();if(!isset($expected[$number]))continue;
                $found[$number]=$sheet->getCell('B'.$r)->getValue();
                $this->assertSame($expected[$number],$found[$number],$number);
                $this->assertSame(-1.0,$sheet->getRowDimension($r)->getRowHeight());
            }
            $this->assertCount(4,$found);
            foreach($originals as $id=>$values){$a=$t->get($id);$this->assertSame($values,[$a->descricao_atividade,$a->snapshot_totvs]);}
        }finally{if($loaded)$loaded->disconnectWorksheets();$book->disconnectWorksheets();unlink($path);}
    }

    public function testChecklistVariationsKeepUsefulText(): void
    {
        $real=json_decode(file_get_contents(ROOT.'/tests/Fixture/Files/checklist-real-descriptions.json'),true);
        $checklist=substr($real['007188'],strpos($real['007188'],"\r\n"));
        $clean=\App\Service\ExportarPorcentagens::cleanDescription(...);
        $this->assertSame('', $clean($checklist));
        $this->assertSame("Trocar ferramentas e limpar proteções.\nConferir segurança após montagem.",$clean('Trocar ferramentas e limpar proteções.'.$checklist."\nConferir segurança após montagem."));
        $variant=mb_strtoupper(str_replace(["\r\n",'serviço','não','aplicável','proteção','botões','sinalização','condições','segurança','estão','conclusão'],[" \n ",'servico','nao','aplicavel','protecao','botoes','sinalizacao','condicoes','seguranca','estao','conclusao'],$checklist));
        $this->assertSame('FAZER MANUTENÇÃO',$clean('FAZER MANUTENÇÃO'.$variant));
        $this->assertSame('', $clean('A conclusão do serviço manteve as condições de segurança? ( ) sim ( ) não'));
        $plain="As ferramentas foram limpas antes do serviço? Confirmar com a equipe.\nLimpeza e segurança são parte da manutenção.";
        $this->assertSame($plain,$clean($plain));
    }
    public function testExcelChecklistAndNotebookMembership(): void
    {
        $t=Access::table('Atividades');
        $checklist="\nferramentas limpas antes do serviço? ( ) sim ( ) não ( ) n/a\nequipamento limpo após o serviço? ( ) sim ( ) não ( ) n/a\nsobras de materiais recolhidas? ( ) sim ( ) não ( ) n/a\nproteções, botões e sinalização conforme nr-12? ( ) sim ( ) não\ncondições de segurança mantidas após o serviço? ( ) sim ( ) não";
        $original='FAZER MANUTENÇÃO.'.$checklist."\n\nObservação útil após o checklist.";
        $a=$t->get($this->ids[0]);$a->descricao_atividade=$original;$t->saveOrFail($a);
        // An activity override cannot pull another notebook into this export.
        $a=$t->get($this->ids[260]);$a->area_curva_id=$this->areas[0];$t->saveOrFail($a);
        // Nor can an override remove an activity belonging to this notebook.
        $a=$t->get($this->ids[1]);$a->area_curva_id=$this->areas[1];$t->saveOrFail($a);
        $a=$t->get($this->ids[2]);$a->caderno_id=null;$a->area_curva_id=$this->areas[0];$t->saveOrFail($a);
        $a=$t->get($this->ids[3]);$a->tipo_planejamento=\App\Service\Planejamento::CORRETIVA;$t->saveOrFail($a);
        $books=Access::table('Cadernos');
        $extra=$books->saveOrFail(Access::entity($books,['nome'=>'Segundo caderno','entressafra_id'=>$this->season,'area_curva_id'=>$this->areas[0],'ativo'=>true]));
        $links=Access::table('CadernosSetores');
        $links->saveOrFail(Access::entity($links,['caderno_id'=>$extra->id,'setor_id'=>$this->teams[1]]));
        $a=$t->get($this->ids[4]);$a->caderno_id=$extra->id;$t->saveOrFail($a);
        $book=(new \App\Service\ExportarPorcentagens())->workbook(Access::actor($this->admin),$this->season,$this->areas[0]);
        $sheet=$book->getActiveSheet();$numbers=[];
        for($r=6;$r<=$sheet->getHighestRow();$r++)$numbers[]=$sheet->getCell('A'.$r)->getValue();
        $this->assertCount(258,$numbers);$this->assertSame($numbers,array_values(array_unique($numbers)));
        $this->assertContains('006001',$numbers);
        $this->assertContains('006004',$numbers);
        foreach(['006002','006003','006260'] as $number)$this->assertNotContains($number,$numbers);
        $this->assertSame("FAZER MANUTENÇÃO.\nObservação útil após o checklist.",$sheet->getCell('B6')->getValue());
        $this->assertSame(-1.0,$sheet->getRowDimension(6)->getRowHeight());
        $this->assertSame($original,$t->get($this->ids[0])->descricao_atividade);
        $this->assertSame('FAZER MANUTENÇÃO.',\App\Service\ExportarPorcentagens::cleanDescription('FAZER MANUTENÇÃO.'.mb_strtoupper($checklist)));
        $plain="Limpar ferramentas e manter condições de segurança.\nConferir equipamento limpo após o serviço.";
        $this->assertSame($plain,\App\Service\ExportarPorcentagens::cleanDescription($plain));
        $book->disconnectWorksheets();
    }
    public function testExcelDescriptionsMatchGridAndPreserveCompleteText(): void
    {
        $user=Access::actor($this->admin);
        $now=new \DateTimeImmutable('2026-10-28',new \DateTimeZone('America/Sao_Paulo'));
        $before=(new \App\Service\ExportarPorcentagens())->workbook($user,$this->season,$this->areas[0],$now);
        $original=$before->getActiveSheet()->toArray();$before->disconnectWorksheets();
        $long=str_repeat('Descrição completa da atividade sem truncamento. ',30)."\nFim da descrição.";
        $expected=['006141'=>'TROCA ANEL DE VEDAÇÃO P/ REGENERADOR','006144'=>'LIMPEZA DO FILTRO 02 E TROCA DOS BICOS SPRAY'];
        $t=Access::table('Atividades');
        foreach(array_slice($this->ids,0,260) as $i=>$id) {
            $a=$t->get($id);$a->descricao_atividade=$i===0?$long:null;
            $a->snapshot_totvs=json_encode($i===1?['descricao_totvs'=>'Descrição do snapshot']:($i===2?['ultima_sincronizacao'=>['descricao_totvs'=>'Descrição da última sincronização']]:[]),JSON_UNESCAPED_UNICODE);
            $t->saveOrFail($a);
        }
        $sizes=[];
        $source=new \App\Integration\Totvs\ListOrders(function($sql,$params)use(&$sizes,$expected): array {
            $numbers=[];foreach($params as $key=>$value)if(str_starts_with($key,'n')&&$value!=='')$numbers[]=$value;
            $sizes[]=count($numbers);$rows=[];
            foreach($numbers as $number) {
                $this->assertNotContains($number,['006000','006001','006002']);
                if($number==='006003')continue;
                $rows[]=['numero'=>$number,'filial'=>'01','codigo_servico'=>'1','codigo_area'=>'A0','centro_custo'=>'CC','data_origem'=>'20260101','situacao'=>'L','termino'=>'N','bem'=>'BEM','servico'=>'ENTRESSAFRA','correspondencias_servico'=>1,'descricao_totvs'=>$expected[$number]??'Descrição consultada '.$number];
            }
            return $rows;
        });
        $book=(new \App\Service\ExportarPorcentagens($source))->workbook($user,$this->season,$this->areas[0],$now);$sheet=$book->getActiveSheet();
        $this->assertSame([100,100,57],$sizes);
        $this->assertSame(265,$sheet->getHighestRow());
        foreach($expected as $number=>$description) {
            $row=(int)$number-6000+6;
            $grid=(new \App\Service\EvolucaoSemanal($now,$source))->page($user,$this->season,$this->areas[0],$number,1);
            $this->assertSame($description,$grid['rows'][0]['descricao']);
            $this->assertSame($grid['rows'][0]['descricao'],$sheet->getCell('B'.$row)->getValue());
        }
        $this->assertSame($long,$sheet->getCell('B6')->getValue());
        $this->assertSame('Descrição do snapshot',$sheet->getCell('B7')->getValue());
        $this->assertSame('Descrição da última sincronização',$sheet->getCell('B8')->getValue());
        $this->assertSame('Descrição não informada',$sheet->getCell('B9')->getValue());
        $this->assertTrue($sheet->getStyle('B6')->getAlignment()->getWrapText());
        $this->assertSame(-1.0,$sheet->getRowDimension(6)->getRowHeight());
        $after=$sheet->toArray();
        foreach($original as $i=>$cells) {unset($cells[1],$after[$i][1]);$this->assertSame($cells,$after[$i]);}
        $path=tempnam(TMP,'description-xlsx-');
        try {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);
            $loaded=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            foreach($expected as $number=>$description)$this->assertSame($description,$loaded->getActiveSheet()->getCell('B'.((int)$number-6000+6))->getValue());
            $this->assertSame($long,$loaded->getActiveSheet()->getCell('B6')->getValue());
            $loaded->disconnectWorksheets();
        }finally{unlink($path);$book->disconnectWorksheets();}
        $unavailable=new \App\Integration\Totvs\ListOrders(static function(){throw new \RuntimeException('Fonte indisponível');});
        $book=(new \App\Service\ExportarPorcentagens($unavailable))->workbook($user,$this->season,$this->areas[0],$now);
        $this->assertSame($long,$book->getActiveSheet()->getCell('B6')->getValue());
        $this->assertSame('Descrição do snapshot',$book->getActiveSheet()->getCell('B7')->getValue());
        $this->assertSame('Descrição não informada',$book->getActiveSheet()->getCell('B147')->getValue());
        $book->disconnectWorksheets();
    }
    public function testExcelExportRealCellsAllRowsAndPermissions(): void
    {
        $now=new \DateTimeImmutable('2026-10-28',new \DateTimeZone('America/Sao_Paulo'));
        $grid=new \App\Service\EvolucaoSemanal($now);$weeks=$grid->weeks($this->season);
        foreach([[0,0,0],[0,2,35],[1,1,20]] as [$index,$week,$value]) {
            $grid->save($this->admin,$this->season,$this->areas[0],[['atividade_id'=>$this->ids[$index],'referencia'=>$weeks[$week]['fim'],'percentual'=>$value,'base'=>null,'base_registro'=>0,'revisao'=>0]],bin2hex(random_bytes(16)));
        }
        $t=Access::table('Atividades');$a=$t->get($this->ids[259]);$a->tipo_planejamento=\App\Service\Planejamento::CORRETIVA;$t->saveOrFail($a);
        $before=[Access::table('Auditorias')->find()->count(),Access::table('EvolucaoRegistros')->find()->count()];
        $export=new \App\Service\ExportarPorcentagens();$book=$export->workbook(Access::actor($this->admin),$this->season,$this->areas[0],$now);
        $sheet=$book->getActiveSheet();
        $this->assertSame(264,$sheet->getHighestRow());
        $this->assertSame('006000',$sheet->getCell('A6')->getValue());
        $this->assertSame('Atividade específica 006000',$sheet->getCell('B6')->getValue());
        $this->assertSame('Equipamento 006000',$sheet->getCell('C6')->getValue());
        $this->assertSame(0.0,(float)$sheet->getCell('E6')->getValue());
        $this->assertNull($sheet->getCell('F6')->getValue());
        $this->assertSame(0.35,$sheet->getCell('G6')->getValue());
        $this->assertSame(0.35,$sheet->getCell('D6')->getValue());
        $this->assertSame(0.2,$sheet->getCell('F7')->getValue());
        $this->assertNull($sheet->getCell('D8')->getValue());
        $this->assertSame('006258',$sheet->getCell('A264')->getValue());
        $this->assertSame('E6',$sheet->getFreezePane());
        $path=tempnam(TMP,'xlsx-test-');
        try {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);
            $loaded=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $this->assertSame('006000',$loaded->getActiveSheet()->getCell('A6')->getValue());
            $this->assertSame(0.0,(float)$loaded->getActiveSheet()->getCell('E6')->getValue());
            $loaded->disconnectWorksheets();
        } finally {unlink($path);$book->disconnectWorksheets();}
        $this->assertSame($before,[Access::table('Auditorias')->find()->count(),Access::table('EvolucaoRegistros')->find()->count()]);
        $this->get('/exportar-porcentagens');$this->assertResponseOk();$this->assertResponseContains('Baixar Excel');
        $this->get('/exportar-porcentagens?entressafra_id='.$this->season.'&area_id='.$this->areas[0].'&baixar=1');$this->assertResponseOk();
        $this->assertHeader('Content-Type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertHeaderContains('Content-Disposition','attachment;');
        $this->assertHeaderContains('Content-Disposition','.xlsx');
        $this->assertStringStartsWith('PK',(string)$this->_response->getBody());
        $this->get('/exportar-porcentagens?entressafra_id=999999&area_id='.$this->areas[0].'&baixar=1');$this->assertResponseCode(400);
        $t=Access::table('Entressafras');$other=$t->saveOrFail($t->newEntity(['nome'=>'Outra','data_inicio'=>'2025-10-01','data_fim'=>'2026-04-01','status'=>'encerrada']));
        $this->get('/exportar-porcentagens?entressafra_id='.$other->id.'&area_id='.$this->areas[0].'&baixar=1');$this->assertResponseCode(403);
        $t=Access::table('Usuarios');$pcm=$t->get($this->responsible);$pcm->perfil='PCM';$t->saveOrFail($pcm);
        $this->login($this->responsible);$this->get('/exportar-porcentagens?entressafra_id='.$this->season.'&area_id='.$this->areas[0].'&baixar=1');$this->assertResponseCode(403);
        $pcm->perfil='RESPONSAVEL_SETOR';$t->saveOrFail($pcm);
        $this->login($this->responsible);$this->get('/exportar-porcentagens');$this->assertResponseCode(403);
        $this->get('/exportar-porcentagens?entressafra_id='.$this->season.'&area_id='.$this->areas[0].'&baixar=1');$this->assertResponseCode(403);
        $this->expectException(\Cake\Http\Exception\ForbiddenException::class);
        $export->workbook(Access::actor($this->responsible),$this->season,$this->areas[0],$now);
    }
    public function testExportButtonUsesExplicitActiveSeasonAndExistingEndpoint(): void
    {
        $this->get('/lancar-porcentagem?area_id='.$this->areas[0].'&pagina=2');
        $this->assertResponseOk();
        $dom=new \DOMDocument();@$dom->loadHTML((string)$this->_response->getBody());$xp=new \DOMXPath($dom);
        $button=$xp->query('//button[@id="porcentagens-exportar"]')->item(0);
        $this->assertNotNull($button);
        $this->assertSame('button',$button->getAttribute('type'));
        $this->assertFalse($button->hasAttribute('onclick'));
        parse_str((string)parse_url($button->getAttribute('data-export-url'),PHP_URL_QUERY),$query);
        $this->assertSame((string)$this->season,$query['entressafra_id']??null,$button->getAttribute('data-export-url'));
        $this->assertSame('1',$query['baixar']);
        $this->assertArrayNotHasKey('pagina',$query);
        $this->assertArrayNotHasKey('os',$query);
        $this->assertResponseContains('exportar-porcentagens.js');
        $this->get('/exportar-porcentagens?entressafra_id='.$this->season.'&area_id='.$this->areas[0].'&baixar=1');
        $this->assertResponseOk();
        $path=tempnam(TMP,'empty-percent-xlsx-');
        try {
            file_put_contents($path,(string)$this->_response->getBody());
            $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);$sheet=$book->getActiveSheet();
            $this->assertSame(265,$sheet->getHighestRow());
            $this->assertSame('006259',$sheet->getCell('A265')->getValue());
            $this->assertNull($sheet->getCell('D6')->getValue());
            $this->assertNull($sheet->getCell('E6')->getValue());
            $book->disconnectWorksheets();
        }finally{unlink($path);}
        $this->login($this->responsible);$this->get('/lancar-porcentagem?area_id='.$this->areas[0]);
        $this->assertResponseOk();$this->assertResponseNotContains('id="porcentagens-exportar"');
    }
    public function testSectorUsesPrincipalAreaAcrossTeamsAndPaginationAndNumberSearch(): void
    {
        $s=new PorcentagensLote();$admin=Access::actor($this->admin);$one=$s->page($admin,$this->season,$this->areas[0],'',1);$six=$s->page($admin,$this->season,$this->areas[0],'',6);
        $this->assertSame(260,$one['total']);$this->assertCount(50,$one['rows']);$this->assertCount(10,$six['rows']);$this->assertSame('006000',$one['rows'][0]['numero']);$this->assertSame('006002',$one['rows'][2]['numero']);
        $this->assertSame(1,$s->page($admin,$this->season,$this->areas[0],'006025',1)['total']);$this->assertSame(1,$s->page($admin,$this->season,$this->areas[0],'006260',1)['total']);
        $this->get('/lancar-porcentagem?area_id='.$this->areas[0]);$this->assertResponseOk();$this->assertResponseContains('Atividade específica 006000');
        $dom=new \DOMDocument();@$dom->loadHTML((string)$this->_response->getBody());$xp=new \DOMXPath($dom);$weeks=count((new \App\Service\EvolucaoSemanal())->weeks($this->season));$this->assertSame(4+$weeks,$xp->query('//main//table/thead/tr/th')->length);$this->assertSame(50*$weeks,$xp->query('//*[@data-percentage]')->length);
        $this->assertSame(1,$xp->query('//button[@id="porcentagens-salvar"]')->length);
        foreach(['setor_id','caderno_id','referencia','observacoes','motivo'] as $field)$this->assertSame(0,$xp->query('//*[@name="'.$field.'"]')->length);
        $this->assertResponseNotContains('Sem atualização na semana');$this->assertResponseNotContains('Lançamento em lote');
    }
    public function testNumberSearchWithoutSectorAndWithDifferentSectorCanSave(): void
    {
        $s=new PorcentagensLote();$admin=Access::actor($this->admin);
        $this->assertSame(0,$s->page($admin,$this->season,0,'',1)['total']);
        foreach([0,$this->areas[0]] as $area){
            $page=$s->page($admin,$this->season,$area,'006260',1);
            $this->assertSame(1,$page['total']);$this->assertSame($this->ids[260],$page['rows'][0]['id']);
            $this->get('/lancar-porcentagem?area_id='.$area.'&os=006260');$this->assertResponseOk();
            $this->assertResponseContains('Atividade específica 006260');$this->assertResponseContains('id="porcentagens-salvar"');
            $html=(string)$this->_response->getBody();$this->assertLessThan(strpos($html,'name="area_id"'),strpos($html,'name="os"'));
        }
        $this->post('/lancar-porcentagem?area_id='.$this->areas[0].'&os=006260',['chave'=>bin2hex(random_bytes(16)),'alteracoes_json'=>json_encode([$this->row(260,30)])]);
        $this->assertRedirect();$this->assertSame(30.0,(new Evolucao())->state($this->ids[260],$this->reference)['percentual']);
    }
    public function testNumberSearchNeverBypassesExecutorPermissions(): void
    {
        $s=new PorcentagensLote();$user=Access::actor($this->responsible);
        $this->assertSame(0,$s->page($user,$this->season,0,'006001',1)['total']);
        $this->assertSame(1,$s->page($user,$this->season,0,'006000',1)['total']);
        try{$s->save($this->responsible,$this->season,0,[$this->row(1,10)],bin2hex(random_bytes(16)));$this->fail('Unauthorized O.S. accepted');}
        catch(\DomainException){$this->assertSame(0,Access::table('EvolucaoRegistros')->find()->count());}
    }
    public function testAtomicSaveMoreThanTwoHundredAndIdempotency(): void
    {
        $s=new PorcentagensLote();$rows=[];for($i=0;$i<250;$i++)$rows[]=$this->row($i,35);$key=bin2hex(random_bytes(16));
        $result=$s->save($this->admin,$this->season,$this->areas[0],$rows,$key);$this->assertSame(250,$result['quantidade']);$this->assertSame($result,$s->save($this->admin,$this->season,$this->areas[0],$rows,$key));
        $this->assertSame(250,Access::table('EvolucaoRegistros')->find()->count());$this->assertSame(0.0,(new Evolucao())->state($this->ids[259],$this->reference)['percentual']);$this->assertSame(250,Access::table('Auditorias')->find()->where(['acao'=>'evolucao_lancamento'])->count());
        $this->assertSame(0,Access::table('Apontamentos')->find()->count());
    }
    public function testAccumulatedCarryForwardOnlyModifiedRowsAndImmediateCurve(): void
    {
        $e=new Evolucao();$e->saveBatch($this->admin,$this->season,'2026-10-12',[['atividade_id'=>$this->ids[0],'percentual'=>20,'revisao'=>0]],bin2hex(random_bytes(16)));
        $s=new PorcentagensLote();$this->assertSame(20.0,$s->page(Access::actor($this->admin),$this->season,$this->areas[0],'006000',1)['rows'][0]['percentual']);
        $result=$s->save($this->admin,$this->season,$this->areas[0],[$this->row(0,35,20),$this->row(1,0)],bin2hex(random_bytes(16)));$this->assertSame(1,$result['quantidade']);
        $state=(new Evolucao())->state($this->ids[0],$this->reference);$this->assertSame(35.0,$state['percentual']);$this->assertSame(15.0,$state['variacao']);$this->assertSame(2,Access::table('EvolucaoRegistros')->find()->count());
        $s->save($this->admin,$this->season,$this->areas[0],[$this->row(0,35,35,1)],bin2hex(random_bytes(16)));$this->assertSame(2,Access::table('EvolucaoRegistros')->find()->count());
        $this->assertEqualsWithDelta(35/260,(new Curva())->report($this->season,(string)$this->areas[0])['metrics']['simples'],0.0001);
    }
    public function testReductionStaleAndInvalidValueRejectEntireBatch(): void
    {
        $s=new PorcentagensLote();$s->save($this->admin,$this->season,$this->areas[0],[$this->row(0,20)],bin2hex(random_bytes(16)));
        foreach([[$this->row(1,15),$this->row(0,10,20,1)],[$this->row(1,15),$this->row(0,40)],[$this->row(1,15),$this->row(2,101)]] as $rows){
            try{$s->save($this->admin,$this->season,$this->areas[0],$rows,bin2hex(random_bytes(16)));$this->fail('Invalid batch accepted');}catch(\DomainException){$this->assertSame(1,Access::table('EvolucaoRegistros')->find()->count());}
        }
        $this->assertSame(20.0,(new Evolucao())->state($this->ids[0],$this->reference)['percentual']);
    }
    public function testPermissionsAndBookMembershipAreRevalidated(): void
    {
        $s=new PorcentagensLote();$r=Access::actor($this->responsible);$page=$s->page($r,$this->season,$this->areas[0],'',1);$this->assertSame(87,$page['total']);
        try{$s->save($this->responsible,$this->season,$this->areas[0],[$this->row(1,10)],bin2hex(random_bytes(16)));$this->fail('Unauthorized executor accepted');}catch(\DomainException){$this->assertSame(0,Access::table('EvolucaoRegistros')->find()->count());}
        $t=Access::table('Atividades');$a=$t->get($this->ids[0]);$a->caderno_id=null;$t->saveOrFail($a);
        $this->expectException(\DomainException::class);$s->save($this->admin,$this->season,$this->areas[0],[$this->row(0,10)],bin2hex(random_bytes(16)));
    }
    public function testHttpSavesBatchAndKeepsSectorAndRejectsOldIndividualPath(): void
    {
        $url='/lancar-porcentagem?area_id='.$this->areas[0].'&os=0060&pagina=2';$this->configRequest(['headers'=>['X-Requested-With'=>'XMLHttpRequest']]);
        $this->post($url,['chave'=>bin2hex(random_bytes(16)),'alteracoes_json'=>json_encode([$this->row(0,30),$this->row(55,50)])]);$this->assertResponseOk();$data=json_decode((string)$this->_response->getBody(),true);$this->assertSame(2,$data['quantidade']);
        $this->get($url);$this->assertResponseOk();$this->assertResponseContains('value="50"');
        $this->post('/lancar-porcentagem',['atividade_id'=>$this->ids[0],'percentual'=>70]);$this->assertResponseOk();$this->assertResponseContains('lançamento individual foi encerrado');$this->assertSame(2,Access::table('EvolucaoRegistros')->find()->count());
    }
}
