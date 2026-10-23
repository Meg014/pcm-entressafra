<?php
declare(strict_types=1);
namespace App\Service;
use Cake\Http\ServerRequest;

final class NavegacaoCadernos
{
    public static function remember(ServerRequest $request): void
    {
        $query=[];
        foreach(['q','area_curva_id','setor_id','page','sort','direction'] as $key){
            $value=$request->getQuery($key);
            if(!is_scalar($value)||strlen((string)$value)>150)continue;
            $query[$key]=(string)$value;
        }
        $request->getSession()->write('Cadernos.retorno',['usuario_id'=>$request->getSession()->read('Auth.id'),'entressafra_id'=>PeriodoContexto::id(),'query'=>$query]);
    }
    public static function target(ServerRequest $request): array
    {
        $saved=$request->getSession()->read('Cadernos.retorno');
        $route=['controller'=>'Cadernos','action'=>'index'];
        if(is_array($saved)&&(int)$saved['usuario_id']===(int)$request->getSession()->read('Auth.id')&&(int)$saved['entressafra_id']===(int)PeriodoContexto::id()&&!empty($saved['query']))$route['?']=$saved['query'];
        return $route;
    }
}
