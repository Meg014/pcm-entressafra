<?php
declare(strict_types=1);
namespace App\Integration\Totvs;
final class OrderQueries
{
    public const FROM = <<<'SQL'
FROM DADOSP12.dbo.STJ010 j
OUTER APPLY (SELECT COUNT_BIG(*) AS matches, MAX(RTRIM(s.T4_NOME)) AS name FROM DADOSP12.dbo.ST4010 s WHERE s.T4_SERVICO=j.TJ_SERVICO AND s.T4_FILIAL=j.TJ_FILIAL AND s.D_E_L_E_T_ <> '*') local_service
OUTER APPLY (SELECT COUNT_BIG(*) AS matches, MAX(RTRIM(s.T4_NOME)) AS name FROM DADOSP12.dbo.ST4010 s WHERE s.T4_SERVICO=j.TJ_SERVICO AND s.T4_FILIAL='' AND s.D_E_L_E_T_ <> '*') shared_service
CROSS APPLY (SELECT CASE WHEN local_service.matches>0 THEN local_service.matches ELSE shared_service.matches END AS matches, CASE WHEN local_service.matches>0 THEN local_service.name ELSE shared_service.name END AS name) service
SQL;
    public const FIELDS = 'RTRIM(j.TJ_ORDEM) AS numero, RTRIM(j.TJ_FILIAL) AS filial, RTRIM(j.TJ_SERVICO) AS codigo_servico, RTRIM(j.TJ_CODAREA) AS codigo_area, RTRIM(j.TJ_CCUSTO) AS centro_custo, RTRIM(j.TJ_DTORIGI) AS data_origem, RTRIM(j.TJ_SITUACA) AS situacao, RTRIM(j.TJ_TERMINO) AS termino, RTRIM(j.TJ_CODBEM) AS bem, service.name AS servico, service.matches AS correspondencias_servico';
    public const OPEN = "j.D_E_L_E_T_ <> '*' AND j.TJ_SITUACA = 'L' AND j.TJ_TERMINO = 'N' AND j.TJ_DTORIGI >= :inicio AND j.TJ_DTORIGI <= :fim AND service.matches=1 AND UPPER(service.name) LIKE :entressafra";
    public static function search(): string
    {
        return 'SELECT TOP (51) '.self::FIELDS.' '.self::FROM.' WHERE '.self::OPEN." AND (j.TJ_ORDEM LIKE :numero OR UPPER(service.name) LIKE UPPER(:descricao)) AND (:todas_areas = '' OR j.TJ_CODAREA = :area) AND (:filtrar_filial = 0 OR j.TJ_FILIAL = :filial) ORDER BY j.TJ_ORDEM, j.TJ_FILIAL";
    }
    public static function detail(): string
    { return 'SELECT TOP (2) '.self::FIELDS.' '.self::FROM." WHERE j.D_E_L_E_T_ <> '*' AND j.TJ_ORDEM=:numero AND j.TJ_FILIAL=:filial"; }
    public static function profile(): string
    {
        return 'SELECT COUNT(*) AS amostra, SUM(CASE WHEN correspondencias_servico=1 THEN 1 ELSE 0 END) AS servicos_inequivocos, SUM(CASE WHEN correspondencias_servico>1 THEN 1 ELSE 0 END) AS servicos_ambiguos FROM (SELECT TOP (200) service.matches AS correspondencias_servico '.self::FROM." WHERE j.D_E_L_E_T_ <> '*' AND j.TJ_SITUACA='L' AND j.TJ_TERMINO='N' AND j.TJ_DTORIGI>=:inicio AND j.TJ_DTORIGI<=:fim AND UPPER(service.name) LIKE :entressafra ORDER BY j.TJ_ORDEM) p";
    }
    public static function historySample(): string
    {
        return 'SELECT TOP (1) '.self::FIELDS.' '.self::FROM." WHERE j.D_E_L_E_T_ <> '*' AND (j.TJ_TERMINO <> 'N' OR j.TJ_SITUACA IN ('C','P')) AND j.TJ_DTORIGI >= :inicio AND j.TJ_DTORIGI <= :fim AND service.matches=1 AND UPPER(service.name) LIKE :entressafra ORDER BY j.TJ_DTORIGI DESC";
    }
    public static function areas(): string
    { return 'SELECT TOP (100) RTRIM(j.TJ_CODAREA) AS area, RTRIM(j.TJ_FILIAL) AS filial, COUNT_BIG(*) AS quantidade '.self::FROM.' WHERE '.self::OPEN.' GROUP BY j.TJ_CODAREA, j.TJ_FILIAL ORDER BY j.TJ_CODAREA, j.TJ_FILIAL'; }
    public static function allows(string $sql): bool { return in_array($sql,[self::search(),self::detail(),self::profile(),self::historySample(),self::areas()],true); }
}
