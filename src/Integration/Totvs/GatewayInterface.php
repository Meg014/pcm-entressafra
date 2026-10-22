<?php
declare(strict_types=1);
namespace App\Integration\Totvs;
interface GatewayInterface
{
    public function orders(string $search, string $area = '', ?string $branch = null): array;
    public function order(string $key): array;
    public function employees(string $search): array;
    public function employee(string $key): array;
}
