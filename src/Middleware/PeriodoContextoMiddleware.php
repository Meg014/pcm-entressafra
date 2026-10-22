<?php
declare(strict_types=1);
namespace App\Middleware;
use App\Service\PeriodoContexto;
use Psr\Http\Message\{ResponseInterface,ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface,RequestHandlerInterface};
final class PeriodoContextoMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request,RequestHandlerInterface $handler): ResponseInterface
    {
        PeriodoContexto::set(null);
        try{return $handler->handle($request);}finally{PeriodoContexto::set(null);}
    }
}
