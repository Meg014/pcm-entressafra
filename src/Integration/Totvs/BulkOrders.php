<?php
declare(strict_types=1);
namespace App\Integration\Totvs;

final class BulkOrders
{
    public function __construct(private ?\Closure $reader=null) {}
    private function read(string $sql,array $params): array
    {
        try { return $this->reader ? ($this->reader)($sql,$params) : \Cake\Datasource\ConnectionManager::get('protheus')->execute($sql,$params)->fetchAll('assoc'); }
        catch (\Throwable) { throw new \DomainException('Não foi possível consultar os serviços/O.S. no TOTVS. Confira a conexão e as permissões SELECT.'); }
    }
    private function params(string $area,string $branch): array
    {
        if ($area==='' || strlen($area)>6 || strlen($branch)>2) { throw new \DomainException('Configure Área TOTVS e Filial no setor.'); }
        return ['inicio'=>'20260101','fim'=>'20261231','entressafra'=>'%ENTRESSAFRA%','area'=>$area,'filial'=>$branch];
    }
    public function services(string $area,string $branch): array
    {
        $rows=$this->read(BulkOrderQueries::services(),$this->params($area,$branch));
        if (count($rows)>500) { throw new \DomainException('Mais de 500 serviços encontrados para a área. Solicite a conferência do PCM.'); }
        return array_map(fn($r)=>['codigo'=>rtrim((string)$r['codigo']),'nome'=>rtrim((string)$r['nome']),'quantidade'=>(int)$r['quantidade']],$rows);
    }
    public function page(array $filters,string $cursor=''): array
    {
        $codes=$filters['servicos']??[];
        if (!is_array($codes) || !$codes || count($codes)>20 || strlen($cursor)>6) { throw new \DomainException('Selecione de 1 a 20 serviços válidos.'); }
        $params=$this->params($filters['area'],$filters['filial']);
        foreach (range(0,19) as $i) {
            $code=$codes[$i]??'';
            if (!is_string($code) || strlen($code)>6) { throw new \DomainException('Código de serviço inválido.'); }
            $params['s'.$i]=$code;
        }
        $params['cursor']=$cursor;
        $pattern=static fn($s)=>'%'.strtr($s,['['=>'[[]','%'=>'[%]','_'=>'[_]']).'%';
        foreach (['cc','bem','busca'] as $field) {
            $value=$filters[$field]??'';
            if (!is_string($value) || mb_strlen($value)>60) { throw new \DomainException('Filtro inválido. Use até 60 caracteres.'); }
            $params[$field]=$value;
        }
        $params+=['cc_busca'=>$pattern($params['cc']),'bem_busca'=>$pattern($params['bem']),'numero'=>$pattern($params['busca']),'descricao'=>$pattern($params['busca'])];
        $rows=$this->read(BulkOrderQueries::page(),$params);
        $hasMore=count($rows)>200; $rows=array_slice($rows,0,200);
        $mapped=array_map(fn($r)=>Orders::mapRow($r),$rows);
        return ['rows'=>$mapped,'has_more'=>$hasMore,'cursor'=>$mapped ? $mapped[count($mapped)-1]['numero'] : $cursor];
    }
}
