<?php
declare(strict_types=1);
namespace App\Model\Entity;
use Cake\ORM\Entity;
class Usuario extends Entity
{
    protected array $_accessible = ['*' => false];
    protected array $_hidden = ['senha_hash'];
}
