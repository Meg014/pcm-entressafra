<?php
declare(strict_types=1);
namespace App\Controller;

use App\Service\Access;
use App\Service\BulkLinks;

class VinculosLoteController extends OperacaoController
{
    public function index(): void
    {
        Access::pcm($this->currentUser);
        $this->set('seasons',$this->fetchTable('Entressafras')->find('list')->where(['status !='=>'encerrada'])->orderBy(['nome'=>'DESC'])->all());
        $this->set('sectors',$this->fetchTable('Setores')->find('list')->where(['ativo'=>true])->orderBy(['nome'=>'ASC'])->all());
        $this->set('notebooks',$this->fetchTable('Cadernos')->find()->contain(['Setores'])->where(['ativo'=>true])->orderBy(['nome'=>'ASC'])->all());
        $this->set('token',is_string($this->request->getQuery('lote'))?$this->request->getQuery('lote'):'');
    }
    public function api(BulkLinks $bulk)
    {
        Access::pcm($this->currentUser); $this->request->allowMethod(['get','post']);
        $status=200;
        try {
            $userId=(int)$this->currentUser->id;
            $input=$this->request->is('post')?$this->request->getData():$this->request->getQueryParams();
            $action=$input['acao']??'';
            if (!is_string($action)) { throw new \DomainException('Ação inválida.'); }
            $token=$input['token']??'';
            if (!is_string($token)) { throw new \DomainException('Prévia inválida.'); }
            if ($this->request->is('get')) {
                if ($action==='servicos') { $data=['servicos'=>$bulk->services($userId,$input)]; }
                elseif ($action==='previa') {
                    if (!is_string($input['busca']??'')) { throw new \DomainException('Pesquisa inválida.'); }
                    $data=$bulk->summary($userId,$token,$input['busca']??'',(int)($input['pagina']??1));
                } else { throw new \DomainException('Ação inválida.'); }
            } else {
                $data=match($action) {
                    'criar'=>['token'=>$bulk->create($userId,$input)],
                    'preparar'=>$bulk->prepare($userId,$token),
                    'selecionar'=>$bulk->select($userId,$token,$input),
                    'confirmar'=>$bulk->start($userId,$token,(int)($input['quantidade_confirmada']??0)),
                    'processar'=>$bulk->process($userId,$token),
                    default=>throw new \DomainException('Ação inválida.'),
                };
            }
        } catch (\DomainException $error) { $status=422; $data=['error'=>$error->getMessage()]; }
        catch (\Cake\Datasource\Exception\RecordNotFoundException) { $status=422; $data=['error'=>'Selecione cadastros existentes e compatíveis.']; }
        catch (\Throwable $error) {
            if ($error instanceof \Cake\Http\Exception\HttpException) { throw $error; }
            $status=503; $data=['error'=>'Operação indisponível. O progresso confirmado permanece salvo; tente retomar a prévia.'];
        }
        return $this->response->withStatus($status)->withType('application/json')->withHeader('Cache-Control','no-store, private')->withHeader('X-Content-Type-Options','nosniff')->withStringBody(json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    }
}
