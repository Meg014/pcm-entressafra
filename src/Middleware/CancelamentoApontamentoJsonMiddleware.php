<?php
declare(strict_types=1);
namespace App\Middleware;

use Cake\Http\Response;
use Cake\Log\Log;
use Psr\Http\Message\{ResponseInterface,ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface,RequestHandlerInterface};

/** JSON boundary for the individual cancellation endpoint, including CSRF and beforeFilter. */
final class CancelamentoApontamentoJsonMiddleware implements MiddlewareInterface
{
    private function error(int $status,string $code,string $message): ResponseInterface
    {
        return (new Response())->withStatus($status)->withType('application/json')
            ->withHeader('Cache-Control','no-store')
            ->withStringBody(json_encode(['success'=>false,'code'=>$code,'error'=>$message],JSON_THROW_ON_ERROR));
    }

    public function process(ServerRequestInterface $request,RequestHandlerInterface $handler): ResponseInterface
    {
        if (!preg_match('~/fila-pcm/os/[^/]+/excluir-apontamento/?$~D',$request->getUri()->getPath())) {
            return $handler->handle($request);
        }
        if ($request->getMethod()!=='POST') {
            return $this->error(405,'metodo_invalido','A exclusão exige uma confirmação por POST.')->withHeader('Allow','POST');
        }
        try {
            $response=$handler->handle($request);
            if ($response->getStatusCode()>=300 && $response->getStatusCode()<400) {
                $path=(string)parse_url($response->getHeaderLine('Location'),PHP_URL_PATH);
                if (preg_match('~/login/?$~D',$path)) {
                    return $this->error(401,'sessao_expirada','Sua sessão expirou. Entre novamente antes de excluir o apontamento.');
                }
                return $this->error(409,'contexto_invalido','Selecione novamente a entressafra e abra os registros da O.S.');
            }
            if (!str_contains(strtolower($response->getHeaderLine('Content-Type')),'application/json')) {
                Log::error('Exclusão individual: resposta inesperada sem JSON. HTTP '.$response->getStatusCode());
                return $this->error(500,'resposta_invalida','Não foi possível confirmar a exclusão. Atualize a lista antes de tentar novamente.');
            }
            return $response->withHeader('Cache-Control','no-store');
        } catch (\Cake\Http\Exception\InvalidCsrfTokenException $e) {
            return $this->error(403,'csrf_invalido','A confirmação de segurança expirou. Atualize a página e tente novamente.');
        } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
            return $this->error(404,'registro_nao_encontrado','O.S. ou apontamento não encontrado nesta entressafra.');
        } catch (\Cake\Routing\Exception\MissingRouteException $e) {
            return $this->error(404,'rota_nao_encontrada','Endereço de exclusão inválido. Atualize a página.');
        } catch (\Cake\Http\Exception\HttpException $e) {
            $status=(int)$e->getCode();
            if ($status<400 || $status>=500) {
                Log::error((string)$e);
                return $this->error(500,'erro_interno','Não foi possível confirmar a exclusão. Atualize a lista antes de tentar novamente.');
            }
            return $this->error($status,'requisicao_recusada',$e->getMessage());
        } catch (\DomainException $e) {
            $status=in_array($e->getCode(),[404,409],true)?$e->getCode():422;
            return $this->error($status,$status===409?'registro_alterado':'validacao',$e->getMessage());
        } catch (\Throwable $e) {
            Log::error((string)$e);
            return $this->error(500,'erro_interno','Não foi possível confirmar a exclusão. Atualize a lista antes de tentar novamente.');
        }
    }
}
