<?php
declare(strict_types=1);
namespace App\Service;
final class BatchEntryException extends \DomainException
{
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Revise as linhas indicadas. Nenhum apontamento foi salvo.');
    }
}
