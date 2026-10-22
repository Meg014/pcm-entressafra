<?php
declare(strict_types=1);
if (getenv('PCM_RUN_BULK_HTTP_TEST')!=='1') { fwrite(STDERR,"Defina PCM_RUN_BULK_HTTP_TEST=1 para o teste temporário no servidor local 8766.\n"); exit(2); }
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/config/bootstrap.php';
use App\Service\Access;
use Cake\Http\Session;
if (Access::table('Usuarios')->getConnection()->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra') { exit(2); }
$tag='__bulk_http_'.bin2hex(random_bytes(8)); $user=$season=$sector=$notebook=null; $ownSector=false; $session=false; $failed=false; $stage='preparação'; $total=0;
try {
    $now=gmdate('Y-m-d H:i:s'); $table=Access::table('Usuarios');
    $user=$table->saveOrFail(Access::entity($table,['nome'=>$tag,'login'=>$tag,'senha_hash'=>password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT),'perfil'=>'PCM','ativo'=>true,'created'=>$now,'modified'=>$now]));
    $table=Access::table('Setores'); $sector=$table->find()->where(['area_totvs'=>'MECANI','filial_totvs'=>'01','ativo'=>true])->first();
    if (!$sector) { $sector=$table->saveOrFail($table->newEntity(['nome'=>$tag,'area_totvs'=>'MECANI','filial_totvs'=>'01','ativo'=>true])); $ownSector=true; }
    $table=Access::table('Entressafras'); $season=$table->saveOrFail($table->newEntity(['nome'=>$tag,'data_inicio'=>'2026-01-01','data_fim'=>'2027-12-31','status'=>'planejamento']));
    $table=Access::table('Cadernos'); $notebook=$table->saveOrFail($table->newEntity(['nome'=>$tag,'setor_id'=>$sector->id,'entressafra_id'=>$season->id,'ativo'=>true]));
    Session::create(Cake\Core\Configure::read('Session')); session_start(); $session=true;
    $_SESSION['Auth']=['id'=>$user->id,'stamp'=>hash('sha256',$user->senha_hash),'last'=>time()]; $cookie=session_name().'='.session_id(); session_write_close();
    $context=stream_context_create(['http'=>['header'=>"Cookie: $cookie\r\n",'ignore_errors'=>true,'follow_location'=>0,'timeout'=>30]]);
    $stage='tela HTTP'; $html=@file_get_contents('http://127.0.0.1:8766/vinculos-lote',false,$context);
    if (!$html || !str_contains($http_response_header[0]??'','200') || !str_contains($html,'Vincular O.S. em Lote') || !preg_match('/name="_csrfToken"[^>]*value="([^"]+)"/',$html,$match)) { throw new RuntimeException(); }
    $csrf=html_entity_decode($match[1],ENT_QUOTES,'UTF-8');
    foreach ($http_response_header as $header) {
        if (preg_match('/^Set-Cookie: (csrfToken)=([^;]+)/i',$header,$cookieMatch)) { $cookie.='; '.$cookieMatch[1].'='.$cookieMatch[2]; }
    }
    $api=static function(string $action,array $input=[],bool $post=false) use($cookie,$csrf): array {
        $params=['acao'=>$action]+$input; $url='http://127.0.0.1:8766/vinculos-lote/api';
        $options=['header'=>"Cookie: $cookie\r\nAccept: application/json\r\n",'ignore_errors'=>true,'follow_location'=>0,'timeout'=>30];
        if ($post) { $options['method']='POST'; $options['header'].="Content-Type: application/json\r\nX-CSRF-Token: $csrf\r\n"; $options['content']=json_encode($params,JSON_THROW_ON_ERROR); }
        else { $url.='?'.http_build_query($params); }
        $body=@file_get_contents($url,false,stream_context_create(['http'=>$options]));
        if (!$body || !str_contains($http_response_header[0]??'','200')) { throw new RuntimeException(); }
        return json_decode($body,true,512,JSON_THROW_ON_ERROR);
    };
    $scope=['entressafra_id'=>(string)$season->id,'setor_id'=>(string)$sector->id,'caderno_id'=>(string)$notebook->id];
    $stage='serviços HTTP reais'; $services=$api('servicos',$scope)['servicos'];
    $created=$api('criar',$scope+['servicos'=>array_column($services,'codigo')],true); $job=['token'=>$created['token']];
    $stage='prévia paginada real'; do { $state=$api('preparar',$job,true); } while ($state['estado']==='preparando');
    $total=$state['contagens']['encontradas']; $eligible=$state['elegiveis_filtradas'];
    if ($total<=50 || $eligible<1 || $state['contagens']['vinculadas']!==0) { throw new RuntimeException(); }
    $stage='seleção entre páginas'; $state=$api('selecionar',$job+['todas'=>true,'selecionado'=>true,'quantidade_confirmada'=>$eligible],true);
    if ($state['contagens']['selecionadas']!==$eligible) { throw new RuntimeException(); }
    $state=$api('previa',$job+['pagina'=>2]);
    if (!array_filter($state['rows'],fn($r)=>$r['selecionado'])) { throw new RuntimeException(); }
    $api('selecionar',$job+['todas'=>true,'selecionado'=>false],true);
    $state=$api('previa',$job); $item=array_values(array_filter($state['rows'],fn($r)=>$r['estado']==='elegivel'))[0];
    $api('selecionar',$job+['ids'=>[$item['id']],'selecionado'=>true],true);
    $stage='confirmação e gravação temporária'; $api('confirmar',$job+['quantidade_confirmada'=>1],true);
    do { $state=$api('processar',$job,true); } while ($state['estado']==='processando');
    if ($state['contagens']['vinculadas']!==1 || $state['contagens']['falhas']!==0) { throw new RuntimeException(); }
    $stage='reenvio idempotente'; $state=$api('processar',$job,true);
    if ($state['contagens']['vinculadas']!==1) { throw new RuntimeException(); }
    if (Access::table('Auditorias')->find()->where(['usuario_id'=>$user->id,'acao'=>'item_vinculo_lote'])->count()!==1) { throw new RuntimeException(); }
} catch (Throwable) { $failed=true; }
finally {
    if ($session) { session_start(); $_SESSION=[]; session_destroy(); }
    if ($user) {
        $ids=Access::table('VinculosLotes')->find()->select(['id'])->where(['usuario_id'=>$user->id]);
        Access::table('VinculosItens')->deleteAll(['lote_id IN'=>$ids]);
        Access::table('VinculosLotes')->deleteAll(['usuario_id'=>$user->id]);
        Access::table('Auditorias')->deleteAll(['usuario_id'=>$user->id]);
        if ($notebook) { Access::table('Atividades')->deleteAll(['caderno_id'=>$notebook->id,'criado_por'=>$user->id]); }
    }
    if ($notebook) { Access::table('Cadernos')->deleteAll(['id'=>$notebook->id,'nome'=>$tag]); }
    if ($season) { Access::table('Entressafras')->deleteAll(['id'=>$season->id,'nome'=>$tag]); }
    if ($sector && $ownSector) { Access::table('Setores')->deleteAll(['id'=>$sector->id,'nome'=>$tag]); }
    if ($user) { Access::table('Usuarios')->deleteAll(['id'=>$user->id,'login'=>$tag]); }
}
if ($failed) { fwrite(STDERR,'Falha no teste em lote: '.$stage.'. Dados sensíveis omitidos.'.PHP_EOL); exit(1); }
echo 'HTTP real: serviços dinâmicos, prévia de ',$total,' O.S., seleção de todas entre páginas, confirmação, vínculo temporário, auditoria e reenvio idempotente: OK.',PHP_EOL;
echo 'Fixtures e vínculo temporários removidos. Nenhum dado preexistente alterado; TOTVS somente SELECT.',PHP_EOL;
