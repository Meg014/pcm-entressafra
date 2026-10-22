<?php
declare(strict_types=1);
namespace App\Test\TestCase\Integration\Totvs;
use App\Integration\Totvs\Queries;
use PHPUnit\Framework\TestCase;
class QueriesTest extends TestCase
{
    public function testScopeIsExclusively010(): void
    {
        foreach (Queries::SOURCE_TABLES as $table) {
            $this->assertStringEndsWith('010', $table);
            $this->assertTrue(Queries::supportsTable('dbo', $table));
            $this->assertStringContainsString("'" . $table . "'", Queries::TABLES);
            $this->assertStringContainsString("'" . $table . "'", Queries::COLUMNS);
        }
        foreach (['STJ020', 'SRA020', 'SRA030', 'SRA060', 'SRA010; DROP TABLE usuarios'] as $table) {
            $this->assertFalse(Queries::supportsTable('dbo', $table));
        }
        $this->assertFalse(Queries::supportsTable('outro_schema', 'SRA010'));
    }
    public function testQueriesRemainExactSelectOnly(): void
    {
        foreach ([Queries::TABLES, Queries::COLUMNS, Queries::PROFILE, Queries::PERMISSIONS] as $sql) {
            $this->assertStringStartsWith('SELECT ', $sql);
            $this->assertTrue(Queries::allows($sql));
            $this->assertFalse(Queries::allows($sql . '; DELETE FROM dbo.SRA010'));
        }
    }
    public function testEmployeeDiagnosticsUseOnlyExplicitAuthorizedColumns(): void
    {
        foreach ([Queries::EMPLOYEE_SAMPLE, Queries::EMPLOYEE_BY_NAME, Queries::EMPLOYEE_BY_NUMBER] as $sql) {
            $this->assertTrue(Queries::allows($sql));
            $this->assertStringContainsString('dbo.SRA010', $sql);
            $this->assertStringNotContainsString('RA_NOMECMP', $sql);
            $this->assertStringNotContainsString('SELECT *', $sql);
            $this->assertFalse(Queries::allows($sql . '; DELETE FROM dbo.SRA010'));
        }
        $this->assertStringContainsString(':nome', Queries::EMPLOYEE_BY_NAME);
        $this->assertStringContainsString(':matricula', Queries::EMPLOYEE_BY_NUMBER);
        $this->expectException(\InvalidArgumentException::class);
        Queries::probeColumn('RA_NOME; DELETE FROM dbo.SRA010');
    }
}
