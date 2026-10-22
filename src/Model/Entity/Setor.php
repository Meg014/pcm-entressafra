<?php
declare(strict_types=1);
namespace App\Model\Entity;
use Cake\ORM\Entity;
class Setor extends Entity
{
    protected array $_accessible = ['nome' => true, 'descricao' => true, 'ativo' => true, 'area_totvs'=>true, 'filial_totvs'=>true];
}
