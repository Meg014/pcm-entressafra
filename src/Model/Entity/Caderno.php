<?php
declare(strict_types=1);
namespace App\Model\Entity;
use Cake\ORM\Entity;
class Caderno extends Entity
{
    protected array $_accessible = ['nome' => true, 'descricao' => true, 'ativo' => true, 'entressafra_id' => true, 'setor_id' => true,'setores'=>true,'area_fisica'=>true];
}
