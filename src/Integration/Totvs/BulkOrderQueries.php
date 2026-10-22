<?php
declare(strict_types=1);
namespace App\Integration\Totvs;

final class BulkOrderQueries
{
    public static function services(): string
    {
        return 'SELECT TOP (501) RTRIM(j.TJ_SERVICO) AS codigo, service.name AS nome, COUNT_BIG(*) AS quantidade '.OrderQueries::FROM.' WHERE '.OrderQueries::OPEN.' AND j.TJ_CODAREA=:area AND j.TJ_FILIAL=:filial GROUP BY j.TJ_SERVICO, service.name ORDER BY j.TJ_SERVICO, service.name';
    }
    public static function page(): string
    {
        $codes=implode(',',array_map(fn($i)=>':s'.$i,range(0,19)));
        return 'SELECT TOP (201) '.OrderQueries::FIELDS.", (SELECT COUNT_BIG(*) FROM DADOSP12.dbo.STJ010 d WHERE d.TJ_ORDEM=j.TJ_ORDEM AND d.TJ_FILIAL=j.TJ_FILIAL AND d.D_E_L_E_T_ <> '*') AS identidades ".OrderQueries::FROM.' WHERE '.OrderQueries::OPEN.
            ' AND j.TJ_CODAREA=:area AND j.TJ_FILIAL=:filial AND j.TJ_SERVICO IN ('.$codes.') AND j.TJ_ORDEM > :cursor'.
            " AND (:cc='' OR j.TJ_CCUSTO LIKE :cc_busca) AND (:bem='' OR UPPER(j.TJ_CODBEM) LIKE UPPER(:bem_busca)) AND (:busca='' OR j.TJ_ORDEM LIKE :numero OR UPPER(service.name) LIKE UPPER(:descricao)) ORDER BY j.TJ_ORDEM";
    }
    public static function allows(string $sql): bool { return in_array($sql,[self::services(),self::page()],true); }
}
