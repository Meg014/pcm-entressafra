<?php
declare(strict_types=1);
namespace App\Test\TestCase\Integration\Totvs;
use App\Integration\Totvs\Employees;
use App\Integration\Totvs\Queries;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
class EmployeesTest extends TestCase
{
    public function testSearchNormalizesCaseOnBothSidesWithoutAdditionalColumns(): void
    {
        $this->assertStringContainsString('UPPER(s.RA_NOME) LIKE UPPER(:nome)',Queries::EMPLOYEE_SEARCH);
        foreach (['RA_FILIAL','R_E_C_N_O_','D_E_L_E_T_','RA_NOMECMP'] as $field) {
            $this->assertStringNotContainsString($field,Queries::EMPLOYEE_SEARCH);
        }
    }
    private function lookup(\Closure $reader): Employees
    { return new Employees(fn($sql,$params)=>$sql===Queries::EMPLOYEE_DUPLICATES?[]:$reader($sql,$params)); }
    private function row(string $status = ''): array
    { return ['RA_NOME'=>'Nome de teste  ','RA_MAT'=>'000123','RA_CODFUNC'=>'00123','RA_SITFOLH'=>$status,'elegiveis'=>$status===''?1:0]; }
    public function testSearchByPartialNameOrNumberPreservesIdentity(): void
    {
        foreach (['Nome','001'] as $term) {
            $lookup=$this->lookup(function($sql,$params) use($term) {
                $this->assertSame(Queries::EMPLOYEE_SEARCH,$sql);
                $this->assertSame(['nome'=>'%'.$term.'%','matricula'=>'%'.$term.'%'],$params);
                return [$this->row()];
            });
            $person=$lookup->search($term)[0];
            $this->assertSame('000123',$person['matricula']); $this->assertSame('000123',$person['chave']);
            $this->assertSame('00123',$person['funcao']); $this->assertSame('NORMAL',$person['situacao']);
            $this->assertTrue($person['ativo']); $this->assertSame('RA_NOME',$person['origem_nome']);
            $confirmed=($this->lookup(function($sql,$params) {
                $this->assertSame(Queries::EMPLOYEE_DETAIL,$sql);
                $this->assertSame(['matricula'=>'000123'],$params);
                return [$this->row()];
            }))->find($person['chave']);
            $this->assertSame($person,$confirmed);
        }
    }
    public function testHomonymsHaveDifferentKeysAndResultsAreLimited(): void
    {
        $rows=array_fill(0,30,$this->row());
        foreach($rows as $i=>&$row) { $row['RA_MAT']=str_pad((string)$i,6,'0',STR_PAD_LEFT); $row['R_E_C_N_O_']=$i+1; } unset($row);
        $people=($this->lookup(fn()=> $rows))->search('Nome');
        $this->assertCount(21,$people);
        $this->assertSame($people[0]['nome'],$people[1]['nome']);
        $this->assertNotSame($people[0]['chave'],$people[1]['chave']);
        $this->assertNotSame(Employees::key('000123'),Employees::key('000124'));
    }
    public static function statuses(): array { return [['',true,'NORMAL'],['F',false,'FÉRIAS'],['D',false,'Situação cadastral: D'],['A',false,'Situação cadastral: A'],['X',false,'Situação cadastral: X']]; }
    #[DataProvider('statuses')]
    public function testOnlyNormalIsEligible(string $status,bool $eligible,string $label): void
    {
        $people=($this->lookup(fn()=>[$this->row($status)]))->search('Nome');
        if (!in_array($status,['','F'],true)) { $this->assertSame([],$people); return; }
        $person=$people[0];
        $this->assertSame($eligible,$person['ativo']); $this->assertSame($label,$person['situacao']);
    }
    public function testSqlFiltersSituationsBeforeLimitForBothNameAndNumber(): void
    {
        $this->assertStringContainsString("WHERE LTRIM(RTRIM(s.RA_SITFOLH)) IN ('', 'F') AND (UPPER(s.RA_NOME) LIKE UPPER(:nome) OR UPPER(s.RA_MAT) LIKE UPPER(:matricula))",Queries::EMPLOYEE_SEARCH);
        $this->assertStringContainsString("LTRIM(RTRIM(RA_SITFOLH)) = ''",Queries::EMPLOYEE_DETAIL);
    }
    public function testBackendFiltersUnapprovedSituationsBeforeApplyingLimit(): void
    {
        $rows=array_fill(0,21,$this->row('D'));
        $rows[]=$this->row('A'); $rows[]=$this->row('X');
        $rows[]=$this->row(' '); $rows[count($rows)-1]['elegiveis']=1;
        $rows[]=$this->row('F ');
        foreach (['Nome','000123'] as $term) {
            $people=($this->lookup(fn()=>$rows))->search($term);
            $this->assertCount(2,$people);
            $this->assertSame(['NORMAL','FÉRIAS'],array_column($people,'situacao'));
            $this->assertTrue($people[0]['ativo']); $this->assertFalse($people[1]['ativo']);
        }
    }
    #[DataProvider('statuses')]
    public function testFindRevalidatesSituationEvenIfReaderReturnsDisallowedRow(string $status,bool $eligible,string $label): void
    {
        $lookup=$this->lookup(fn()=>[$this->row($status)]);
        if ($eligible) { $this->assertTrue($lookup->find('000123')['ativo']); return; }
        $this->expectException(\DomainException::class); $lookup->find('000123');
    }
    public function testInjectionIsBoundAndWildcardsEscaped(): void
    {
        $term="%' OR 1=1--[_";
        $lookup=$this->lookup(function($sql,$params) use($term) {
            $this->assertSame(Queries::EMPLOYEE_SEARCH,$sql); $this->assertStringNotContainsString($term,$sql);
            $this->assertSame("%[%]' OR 1=1--[[][_]%",$params['nome']);
            return [];
        });
        $this->assertSame([],$lookup->search($term));
        foreach([Queries::EMPLOYEE_SEARCH,Queries::EMPLOYEE_DETAIL] as $sql) {
            $this->assertTrue(Queries::allows($sql)); $this->assertStringNotContainsString('RA_NOMECMP',$sql);
            foreach(['D_E_L_E_T_','RA_FILIAL','R_E_C_N_O_'] as $forbidden) { $this->assertStringNotContainsString($forbidden,$sql); }
        }
    }
    public function testFreeNameAndInventedNumberCannotSelectAnEmployee(): void
    {
        $lookup=$this->lookup(function(){ $this->fail('Não consultar SQL com chave inválida.'); });
        foreach(['Nome digitado','','sra020:AAAA'] as $key) {
            try { $lookup->find($key); $this->fail('Chave inválida aceita.'); } catch(\DomainException) { $this->assertTrue(true); }
        }
    }
    public function testMissingOrAmbiguousDetailIsRejected(): void
    {
        $key=Employees::key('000123');
        foreach([[],[$this->row(),$this->row()]] as $rows) {
            try { ($this->lookup(fn()=>$rows))->find($key); $this->fail('Identidade não confirmada aceita.'); } catch(\DomainException) { $this->assertTrue(true); }
        }
    }
    public function testReadFailureDoesNotExposeConnectionOrPersonalData(): void
    {
        try { ($this->lookup(fn()=>throw new \RuntimeException('Detalhe interno restrito')))->search('Nome'); $this->fail(); }
        catch(\DomainException $error) { $this->assertSame(Employees::UNAVAILABLE,$error->getMessage()); }
    }
    public function testDuplicateEligibleNumbersBlockSelectionAndSaving(): void
    {
        $duplicate=$this->row(); $duplicate['elegiveis']=2;
        $lookup=new Employees(fn($sql)=>$sql===Queries::EMPLOYEE_SEARCH ? [$duplicate] : [$this->row(),$this->row()]);
        $person=$lookup->search('Nome')[0];
        $this->assertSame('NORMAL',$person['situacao']); $this->assertFalse($person['ativo']);
        $this->assertSame('',$person['chave']); $this->assertSame(Employees::AMBIGUOUS,$person['bloqueio']);
        $this->expectException(\DomainException::class); $this->expectExceptionMessage(Employees::AMBIGUOUS);
        $lookup->find('000123');
    }
    public function testUniquenessIsCheckedAgainAfterSelection(): void
    {
        $lookup=new Employees(fn($sql)=>$sql===Queries::EMPLOYEE_SEARCH ? [$this->row()] : [$this->row(),$this->row()]);
        $person=$lookup->search('Nome')[0]; $this->assertTrue($person['ativo']);
        $this->expectException(\DomainException::class); $lookup->find($person['chave']);
    }
    public function testSimplifiedQueriesNeverRequireRestrictedFields(): void
    {
        foreach([Queries::EMPLOYEE_SEARCH,Queries::EMPLOYEE_DETAIL,Queries::EMPLOYEE_DUPLICATES] as $sql) {
            foreach(['RA_FILIAL','R_E_C_N_O_','D_E_L_E_T_','RA_NOMECMP'] as $field) { $this->assertStringNotContainsString($field,$sql); }
            $this->assertStringContainsString('DADOSP12.dbo.SRA010',$sql);
        }
    }
    public function testUnrelatedDuplicatesDoNotBlockUniqueEmployees(): void
    {
        $unique=$this->row(); $duplicate=$this->row(); $duplicate['RA_MAT']='000124'; $duplicate['elegiveis']=2;
        $vacation=$this->row('F'); $vacation['RA_MAT']='000125';
        $lookup=new Employees(function($sql,$params) use($unique,$duplicate,$vacation) {
            $this->assertNotSame(Queries::EMPLOYEE_DUPLICATES,$sql);
            if($sql===Queries::EMPLOYEE_SEARCH) { return [$unique,$duplicate,$duplicate,$vacation]; }
            $this->assertSame(['matricula'=>'000123'],$params); return [$unique];
        });
        $people=$lookup->search('Nome');
        $this->assertTrue($people[0]['ativo']); $this->assertNull($people[0]['bloqueio']);
        $this->assertFalse($people[1]['ativo']); $this->assertFalse($people[2]['ativo']);
        $this->assertSame(Employees::AMBIGUOUS,$people[1]['bloqueio']);
        $this->assertSame('',$people[1]['chave']); $this->assertFalse($people[3]['ativo']);
        $this->assertSame('000123',$lookup->find('000123')['chave']);
    }
}
