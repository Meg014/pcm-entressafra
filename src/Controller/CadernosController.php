<?php
declare(strict_types=1);
namespace App\Controller;
class CadernosController extends CadastrosController
{
    protected string $cadastro = 'Cadernos';
    protected string $singular = 'Caderno';
    public function view($id=null): void
    {
        parent::view($id);
        $book=$this->fetchTable('Cadernos')->get($id);
        $this->set('areaCurvaNome',$book->area_curva_id?$this->fetchTable('AreasFabrica')->get($book->area_curva_id)->nome:'Pendente de definição administrativa');
    }
}
