<?php
declare(strict_types=1);
namespace App\Service;
final class ConflictException extends \RuntimeException
{
    public function __construct(public readonly array $conflicts) { parent::__construct('Este funcionário já possui apontamentos no intervalo informado. Ajuste a data ou os horários.'); }
}
