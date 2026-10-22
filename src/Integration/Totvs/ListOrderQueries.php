<?php
declare(strict_types=1);
namespace App\Integration\Totvs;

final class ListOrderQueries
{
    public static function numbers(): string
    {
        $slots=implode(',',array_map(fn($i)=>':n'.$i,range(0,99)));
        return 'SELECT '.OrderQueries::FIELDS.", (SELECT COUNT_BIG(*) FROM DADOSP12.dbo.STJ010 d WHERE d.TJ_ORDEM=j.TJ_ORDEM AND d.TJ_FILIAL=j.TJ_FILIAL AND d.D_E_L_E_T_ <> '*') AS identidades ".OrderQueries::FROM.
            " WHERE j.D_E_L_E_T_ <> '*' AND j.TJ_FILIAL=:filial AND j.TJ_ORDEM IN (".$slots.') ORDER BY j.TJ_ORDEM';
    }
}
