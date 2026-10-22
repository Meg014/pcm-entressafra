<?php
declare(strict_types=1);
// Test doubles only. They never connect to SQL Server.
function bulkTestService(array $row): \App\Service\BulkLinks
{
    $source=new \App\Integration\Totvs\BulkOrders(fn($sql,$params)=>$sql===\App\Integration\Totvs\BulkOrderQueries::services()
        ? [['codigo'=>$row['codigo_servico'],'nome'=>$row['servico'],'quantidade'=>1]]
        : ($row['numero']>$params['cursor']?[$row]:[]));
    $gateway=new class($row) implements \App\Integration\Totvs\GatewayInterface {
        public function __construct(private array $row) {}
        public function order(string $key): array { if ($key!==$this->row['chave']) { throw new DomainException('Chave de teste inválida.'); } return $this->row; }
        public function orders(string $search,string $area='',?string $branch=null): array { return []; }
        public function employees(string $search): array { return []; }
        public function employee(string $key): array { throw new DomainException('Fora do escopo do teste.'); }
    };
    return new \App\Service\BulkLinks($source,$gateway);
}
