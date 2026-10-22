<?php
declare(strict_types=1);
namespace App\Model\Entity;
use Cake\ORM\Entity;
class Entressafra extends Entity
{
    protected array $_accessible = ['nome' => true, 'data_inicio' => true, 'data_fim' => true, 'status' => true, 'observacoes' => true];
}
