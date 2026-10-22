<?php
declare(strict_types=1);
namespace App\Test\Support;
use App\Integration\Totvs\GatewayInterface;
final class FakeTotvs implements GatewayInterface
{
    public function __construct(private ?string $area=null) {}
    public function orders(string $search, string $area='', ?string $branch=null): array { return [$this->order('os-1')]; }
    public function order(string $key): array { return ['chave' => $key, 'numero' => $key, 'filial' => '01', 'servico' => 'Serviço de teste ENTRESSAFRA', 'bem' => 'Bem de teste', 'situacao' => 'L', 'termino'=>'N','data_origem'=>'20260101','codigo_servico'=>'ANTIGO','codigo_area'=>$this->area??($key==='os-1'?'A1':'A0'),'correspondencias_servico'=>1,'disponivel'=>true]; }
    public function employees(string $search): array { return [$this->employee('pessoa-1')]; }
    public function employee(string $key): array { return ['chave' => $key, 'nome' => 'Funcionário de teste', 'matricula' => '0001', 'filial' => '01', 'vinculo' => $key, 'ativo' => true]; }
}
