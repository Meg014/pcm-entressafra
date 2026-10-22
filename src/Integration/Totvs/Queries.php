<?php
declare(strict_types=1);
namespace App\Integration\Totvs;
final class Queries
{
    public const ORDER_NOTES = "SELECT TOP (2) RTRIM(TJ_ORDEM) AS numero, RTRIM(TJ_FILIAL) AS filial, CONVERT(varchar(max), TJ_OBSERVA) AS observacoes FROM DADOSP12.dbo.STJ010 WHERE D_E_L_E_T_ <> '*' AND TJ_ORDEM = :numero AND TJ_FILIAL = :filial";
    // Escopo confirmado pelo usuário: exclusivamente a família 010.
    public const SOURCE_TABLES = ['STJ010', 'ST4010', 'ST9010', 'SRA010', 'ST1010', 'CTT010'];
    public const TABLES = "SELECT TOP (500) TABLE_SCHEMA, TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = :kind AND TABLE_SCHEMA = 'dbo' AND TABLE_NAME IN ('STJ010','ST4010','ST9010','SRA010','ST1010','CTT010') ORDER BY TABLE_SCHEMA, TABLE_NAME";
    public const COLUMNS = "SELECT TOP (500) COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND TABLE_SCHEMA = 'dbo' AND TABLE_NAME IN ('STJ010','ST4010','ST9010','SRA010','ST1010','CTT010') ORDER BY ORDINAL_POSITION";
    public static function supportsTable(string $schema, string $table): bool
    { return $schema === 'dbo' && in_array($table, self::SOURCE_TABLES, true); }
    public const ORDER_COLUMNS = ['STJ010'=>['TJ_ORDEM','TJ_FILIAL','TJ_SERVICO','TJ_CODAREA','TJ_CCUSTO','TJ_DTORIGI','TJ_SITUACA','TJ_TERMINO','TJ_CODBEM','D_E_L_E_T_'], 'ST4010'=>['T4_SERVICO','T4_NOME','T4_FILIAL','D_E_L_E_T_']];
    public static function probeOrderColumn(string $table,string $column): string
    {
        if(!in_array($column,self::ORDER_COLUMNS[$table]??[],true)) { throw new \InvalidArgumentException('Campo de O.S. fora do escopo.'); }
        return 'SELECT TOP (1) ['.$column.'] FROM DADOSP12.dbo.['.$table.']';
    }
    public const PROFILE = <<<'SQL'
SELECT 'orders' AS source, COUNT(*) AS sampled, SUM(CASE WHEN RTRIM(TJ_ORDEM) = '' THEN 1 ELSE 0 END) AS incomplete
FROM (SELECT TOP (200) TJ_ORDEM FROM dbo.STJ010 WHERE D_E_L_E_T_ <> '*' ORDER BY R_E_C_N_O_ DESC) j
UNION ALL
SELECT 'employees', COUNT(*), SUM(CASE WHEN RTRIM(RA_MAT) = '' OR RTRIM(RA_NOME) = '' THEN 1 ELSE 0 END)
FROM (SELECT TOP (200) RA_MAT, RA_NOME FROM DADOSP12.dbo.SRA010) r
UNION ALL
SELECT 'services', COUNT(*), SUM(CASE WHEN RTRIM(T4_NOME) = '' THEN 1 ELSE 0 END)
FROM (SELECT TOP (200) T4_NOME FROM dbo.ST4010 WHERE D_E_L_E_T_ <> '*' ORDER BY R_E_C_N_O_ DESC) s
UNION ALL
SELECT 'assets', COUNT(*), SUM(CASE WHEN RTRIM(T9_NOME) = '' THEN 1 ELSE 0 END)
FROM (SELECT TOP (200) T9_NOME FROM dbo.ST9010 WHERE D_E_L_E_T_ <> '*' ORDER BY R_E_C_N_O_ DESC) b
UNION ALL
SELECT 'cost_centers', COUNT(*), SUM(CASE WHEN RTRIM(CTT_DESC01) = '' THEN 1 ELSE 0 END)
FROM (SELECT TOP (200) CTT_DESC01 FROM dbo.CTT010 WHERE D_E_L_E_T_ <> '*' ORDER BY R_E_C_N_O_ DESC) c
SQL;
    public const PERMISSIONS = "SELECT HAS_PERMS_BY_NAME('dbo.STJ010', 'OBJECT', 'SELECT') AS orders_read, HAS_PERMS_BY_NAME('dbo.SRA010', 'OBJECT', 'SELECT') AS employees_read, HAS_PERMS_BY_NAME('dbo.STJ010', 'OBJECT', 'INSERT') AS orders_insert, HAS_PERMS_BY_NAME('dbo.STJ010', 'OBJECT', 'UPDATE') AS orders_update, HAS_PERMS_BY_NAME('dbo.STJ010', 'OBJECT', 'DELETE') AS orders_delete, HAS_PERMS_BY_NAME('dbo.SRA010', 'OBJECT', 'INSERT') AS employees_insert, HAS_PERMS_BY_NAME('dbo.SRA010', 'OBJECT', 'UPDATE') AS employees_update, HAS_PERMS_BY_NAME('dbo.SRA010', 'OBJECT', 'DELETE') AS employees_delete";
    public const EMPLOYEE_COLUMNS = ['RA_NOME','RA_MAT','RA_CODFUNC','RA_SITFOLH'];
    public const EMPLOYEE_SEARCH = "SELECT TOP (21) s.RA_NOME, s.RA_MAT, s.RA_CODFUNC, s.RA_SITFOLH, (SELECT COUNT_BIG(*) FROM DADOSP12.dbo.SRA010 e WHERE RTRIM(e.RA_MAT) = RTRIM(s.RA_MAT) AND LTRIM(RTRIM(e.RA_SITFOLH)) = '') AS elegiveis FROM DADOSP12.dbo.SRA010 s WHERE LTRIM(RTRIM(s.RA_SITFOLH)) IN ('', 'F') AND (UPPER(s.RA_NOME) LIKE UPPER(:nome) OR UPPER(s.RA_MAT) LIKE UPPER(:matricula)) ORDER BY CASE WHEN LTRIM(RTRIM(s.RA_SITFOLH)) = '' THEN 0 ELSE 1 END, s.RA_NOME, s.RA_MAT, s.RA_CODFUNC, s.RA_SITFOLH";
    public const EMPLOYEE_DETAIL = "SELECT TOP (2) RA_NOME, RA_MAT, RA_CODFUNC, RA_SITFOLH FROM DADOSP12.dbo.SRA010 WHERE RTRIM(RA_MAT) = :matricula AND LTRIM(RTRIM(RA_SITFOLH)) = ''";
    public const EMPLOYEE_STATUS = 'SELECT RA_SITFOLH AS situation, COUNT(*) AS sampled FROM (SELECT TOP (200) RA_SITFOLH FROM dbo.SRA010) s GROUP BY RA_SITFOLH';
    public const EMPLOYEE_DUPLICATES = "SELECT TOP (51) RTRIM(RA_MAT) AS matricula, COUNT_BIG(*) AS quantidade FROM DADOSP12.dbo.SRA010 WHERE LTRIM(RTRIM(RA_SITFOLH)) = '' GROUP BY RTRIM(RA_MAT) HAVING COUNT_BIG(*) > 1 OR RTRIM(RA_MAT) = '' ORDER BY RTRIM(RA_MAT)";
    public const EMPLOYEE_UNIQUE_SAMPLE = "SELECT TOP (1) s.RA_MAT, s.RA_NOME, s.RA_CODFUNC, s.RA_SITFOLH FROM DADOSP12.dbo.SRA010 s WHERE LTRIM(RTRIM(s.RA_SITFOLH)) = '' AND RTRIM(s.RA_MAT) <> '' AND (SELECT COUNT_BIG(*) FROM DADOSP12.dbo.SRA010 e WHERE RTRIM(e.RA_MAT) = RTRIM(s.RA_MAT) AND LTRIM(RTRIM(e.RA_SITFOLH)) = '') = 1 ORDER BY s.RA_MAT";
    // Consultas restritas aos quatro campos expressamente autorizados pelo usuário.
    public const EMPLOYEE_SAMPLE = 'SELECT TOP (1) RA_NOME, RA_MAT, RA_CODFUNC, RA_SITFOLH FROM dbo.SRA010';
    public const EMPLOYEE_BY_NAME = 'SELECT TOP (5) RA_NOME, RA_MAT, RA_CODFUNC, RA_SITFOLH FROM dbo.SRA010 WHERE RA_NOME LIKE :nome';
    public const EMPLOYEE_BY_NUMBER = 'SELECT TOP (5) RA_NOME, RA_MAT, RA_CODFUNC, RA_SITFOLH FROM dbo.SRA010 WHERE RA_MAT = :matricula';
    public static function probeColumn(string $column): string
    {
        if (!in_array($column, self::EMPLOYEE_COLUMNS, true)) { throw new \InvalidArgumentException('Coluna fora do escopo permitido.'); }
        return 'SELECT TOP (1) [' . $column . '] FROM dbo.SRA010';
    }
    public static function allows(string $sql): bool
    {
        if($sql===self::ORDER_NOTES) { return true; }
        if($sql===ListOrderQueries::numbers()) { return true; }
        if(BulkOrderQueries::allows($sql)) { return true; }
        if(OrderQueries::allows($sql)) { return true; }
        if (in_array($sql, [self::TABLES, self::COLUMNS, self::PROFILE, self::PERMISSIONS, self::EMPLOYEE_SAMPLE, self::EMPLOYEE_BY_NAME, self::EMPLOYEE_BY_NUMBER, self::EMPLOYEE_SEARCH, self::EMPLOYEE_DETAIL, self::EMPLOYEE_STATUS, self::EMPLOYEE_DUPLICATES, self::EMPLOYEE_UNIQUE_SAMPLE], true)) { return true; }
        foreach (self::EMPLOYEE_COLUMNS as $column) { if ($sql === self::probeColumn($column)) { return true; } }
        foreach(self::ORDER_COLUMNS as $table=>$columns) { foreach($columns as $column) { if($sql===self::probeOrderColumn($table,$column)) { return true; } } }
        return false;
    }
}
