<?php
declare(strict_types=1);
namespace App\Integration\Totvs;
final class Orders
{
    public function __construct(private ?\Closure $reader=null) {}
    private function read(string $sql,array $params): array
    {
        try { return $this->reader ? ($this->reader)($sql,$params) : \Cake\Datasource\ConnectionManager::get('protheus')->execute($sql,$params)->fetchAll('assoc'); }
        catch(\Throwable) { throw new \DomainException('Não foi possível consultar as O.S. no TOTVS. Verifique conexão e permissões SELECT.'); }
    }
    public static function key(string $branch,string $number): string
    { return 'stj010:'.rtrim(strtr(base64_encode(json_encode([$branch,$number],JSON_THROW_ON_ERROR)),'+/','-_'),'='); }
    private static function decode(string $key): array
    {
        if(!str_starts_with($key,'stj010:') || strlen($key)>255) { throw new \DomainException('Selecione uma O.S. válida da família 010.'); }
        try { $values=json_decode((string)base64_decode(strtr(substr($key,7),'-_','+/'),true),true,4,JSON_THROW_ON_ERROR); }
        catch(\Throwable) { throw new \DomainException('Chave da O.S. inválida.'); }
        if(!is_array($values) || array_keys($values)!==[0,1] || !is_string($values[0]) || strlen($values[0])>2 || !is_string($values[1]) || trim($values[1])==='' || strlen($values[1])>6 || self::key(...$values)!==$key) { throw new \DomainException('Chave da O.S. inválida.'); }
        return $values;
    }
    public static function available(array $row): bool
    {
        $date=(string)($row['data_origem']??'');
        return ($row['situacao']??'')==='L' && ($row['termino']??'')==='N' && preg_match('/^[0-9]{8}$/D',$date) && $date>='20260101' && $date<='20261231' && checkdate((int)substr($date,4,2),(int)substr($date,6,2),(int)substr($date,0,4)) && (int)($row['correspondencias_servico']??0)===1 && str_contains(mb_strtoupper((string)($row['servico']??''),'UTF-8'),'ENTRESSAFRA');
    }
    public static function mapRow(array $row): array
    {
        $description=trim((string)($row['descricao_totvs']??''));
        $row['descricao_totvs']=mb_check_encoding($description,'UTF-8')?$description:mb_convert_encoding($description,'UTF-8','Windows-1252');
        foreach(['numero','filial','codigo_servico','codigo_area','centro_custo','data_origem','situacao','termino','bem'] as $field) { if(!array_key_exists($field,$row)) { throw new \DomainException('Resposta incompleta da O.S. no TOTVS.'); } $row[$field]=rtrim((string)$row[$field],' '); }
        $row['servico']=rtrim((string)($row['servico']??''),' ');
        $row['correspondencias_servico']=(int)($row['correspondencias_servico']??0);
        $row['chave']=self::key($row['filial'],$row['numero']); self::decode($row['chave']);
        $row['disponivel']=self::available($row); $row['origem']='DADOSP12.dbo.STJ010';
        return $row;
    }
    public function search(string $term,string $area='',?string $branch=null): array
    {
        $term=trim($term); if(mb_strlen($term)<2 || mb_strlen($term)>60 || strlen($area)>6 || ($branch!==null && strlen($branch)>2)) { throw new \DomainException('Informe de 2 a 60 caracteres e uma área/filial válida.'); }
        $pattern='%'.strtr($term,['['=>'[[]','%'=>'[%]','_'=>'[_]']).'%';
        $rows=$this->read(OrderQueries::search(),['inicio'=>'20260101','fim'=>'20261231','entressafra'=>'%ENTRESSAFRA%','numero'=>$pattern,'descricao'=>$pattern,'todas_areas'=>$area,'area'=>$area,'filtrar_filial'=>$branch===null?0:1,'filial'=>$branch??'']);
        $result=[]; foreach(array_slice($rows,0,51) as $row) { $mapped=self::mapRow($row); if($mapped['disponivel']) {$result[]=$mapped;} }
        return $result;
    }
    public function find(string $key): array
    {
        [$branch,$number]=self::decode($key);
        $rows=$this->read(OrderQueries::detail(),['numero'=>$number,'filial'=>$branch]);
        if(count($rows)!==1) { throw new \DomainException('O.S. não encontrada ou com identificação ambígua. O vínculo existente foi preservado.'); }
        $row=self::mapRow($rows[0]);
        if($row['chave']!==$key) { throw new \DomainException('Identificação da O.S. não confirmada.'); }
        return $row;
    }
}
