<?php
declare(strict_types=1);
namespace App\Test\Support;
use App\Integration\Totvs\GatewayInterface;
use App\Service\Access;

/** Only test fixtures; never delegates to a real TOTVS connection. */
final class OperationalTotvs implements GatewayInterface
{
    public function order(string $key): array
    {
        $a=Access::table('Atividades')->find()->where(['os_chave'=>$key])->firstOrFail();
        return ['chave'=>$key,'numero'=>$a->os_numero,'filial'=>$a->os_filial,'codigo_area'=>$a->area_executante_totvs,'servico'=>'ENTRESSAFRA TESTE','bem'=>'BEM TESTE','situacao'=>'L','termino'=>'N','disponivel'=>true,'correspondencias_servico'=>1,'data_origem'=>'20260101'];
    }
    public function orders(string $search,string $area='',?string $branch=null): array
    {
        $out=[];foreach(Access::table('Atividades')->find()->all() as $a){if($a->area_executante_totvs===$area&&$a->os_filial===$branch&&str_contains($a->os_numero,$search))$out[]=$this->order($a->os_chave);}return $out;
    }
    public function employee(string $key): array
    {
        if(!preg_match('/^teste-[a-z0-9-]+$/D',$key))throw new \DomainException('Funcionário de teste inválido.');
        return ['chave'=>$key,'matricula'=>$key,'nome'=>'Funcionário '.$key,'filial'=>'01','vinculo'=>$key,'ativo'=>true];
    }
    public function employees(string $search): array {return [$this->employee('teste-funcionario')];}
}
