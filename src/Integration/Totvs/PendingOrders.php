<?php
declare(strict_types=1);
namespace App\Integration\Totvs;

/** Keyset scan of the same authorized entressafra population used by existing links. */
final class PendingOrders
{
    public function __construct(private ?\Closure $reader=null) {}
    public static function sql(): string
    {
        return 'SELECT TOP (201) '.OrderQueries::FIELDS.", (SELECT COUNT_BIG(*) FROM DADOSP12.dbo.STJ010 d WHERE d.TJ_ORDEM=j.TJ_ORDEM AND d.TJ_FILIAL=j.TJ_FILIAL AND d.D_E_L_E_T_ <> '*') AS identidades ".OrderQueries::FROM.' WHERE '.OrderQueries::OPEN." AND j.TJ_FILIAL=:filial AND j.TJ_ORDEM > :cursor ORDER BY j.TJ_ORDEM";
    }
    public function page(string $cursor=''): array
    {
        if($cursor!==''&&!preg_match('/^[0-9]{6}$/D',$cursor))throw new \DomainException('Cursor de consulta inválido.');
        $params=['inicio'=>'20260101','fim'=>'20261231','entressafra'=>'%ENTRESSAFRA%','filial'=>'01','cursor'=>$cursor];
        try{$rows=$this->reader?($this->reader)(self::sql(),$params):\Cake\Datasource\ConnectionManager::get('protheus')->execute(self::sql(),$params)->fetchAll('assoc');}
        catch(\Throwable){throw new \DomainException('TOTVS indisponível. A consulta foi preservada; tente retomar.');}
        $more=count($rows)>200;$rows=array_slice($rows,0,200);$mapped=array_map([Orders::class,'mapRow'],$rows);
        $next=$mapped?$mapped[count($mapped)-1]['numero']:$cursor;
        if($more&&strcmp($next,$cursor)<=0)throw new \DomainException('A consulta não avançou. Solicite conferência da identificação das O.S.');
        return ['rows'=>$mapped,'cursor'=>$next,'has_more'=>$more];
    }
}
