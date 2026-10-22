<?php
declare(strict_types=1);
namespace App\Integration\Totvs;
class Gateway implements GatewayInterface
{
    private function unavailable(): never { throw new \DomainException('Integração TOTVS pendente: revisar permissões dos identificadores e concluir a validação das consultas. Nenhum vínculo será salvo sem identificação confirmada.'); }
    public function orders(string $search, string $area = '', ?string $branch = null): array { return (new Orders())->search($search,$area,$branch); }
    public function order(string $key): array { return (new Orders())->find($key); }
    public function employees(string $search): array { return (new Employees())->search($search); }
    public function employee(string $key): array { return (new Employees())->find($key); }
}
