<?php
declare(strict_types=1);
namespace App\Integration\Totvs;

final class ListOrders
{
    public function __construct(private ?\Closure $reader=null) {}
    public function find(array $numbers): array
    {
        if(!$numbers || count($numbers)>100)throw new \DomainException('Consulte de 1 a 100 números por bloco.');
        $params=['filial'=>'01'];
        foreach(range(0,99) as $i) {
            $number=$numbers[$i]??'';
            if(!is_string($number)||($number!==''&&!preg_match('/^[0-9]{6}$/D',$number)))throw new \DomainException('Número de O.S. inválido.');
            $params['n'.$i]=$number;
        }
        try {
            $rows=$this->reader?($this->reader)(ListOrderQueries::numbers(),$params):\Cake\Datasource\ConnectionManager::get('protheus')->execute(ListOrderQueries::numbers(),$params)->fetchAll('assoc');
            $out=[];
            foreach($rows as $row) {
                $mapped=Orders::mapRow($row);$number=$mapped['numero'];
                if(isset($out[$number]))$mapped['identidades']=2;
                $out[$number]=$mapped;
            }
            return $out;
        } catch(\Throwable) { throw new \DomainException('Consulta em bloco ao TOTVS indisponível. Nenhum vínculo deste bloco foi gravado.'); }
    }
}
