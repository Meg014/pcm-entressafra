<?php
declare(strict_types=1);
// Temporary records are tagged and removed in finally; no existing records are changed.
if (getenv('PCM_RUN_HTTP_LOOKUP_TEST')!=='1') { fwrite(STDERR,"Defina PCM_RUN_HTTP_LOOKUP_TEST=1 para testar no PostgreSQL exclusivo e servidor local 8766.\n"); exit(2); }
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/config/bootstrap.php';
use App\Service\Access;
use Cake\Http\Session;
$db=Access::table('Usuarios')->getConnection();
if ($db->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra') { exit(2); }
$user=$sector=$season=$notebook=$activity=null; $session=null; $stage='preparação'; $failed=false;
$tag='__http_lookup_'.bin2hex(random_bytes(8));
try {
    $now=gmdate('Y-m-d H:i:s'); $table=Access::table('Usuarios');
    $user=$table->saveOrFail(Access::entity($table,['nome'=>$tag,'login'=>$tag,'senha_hash'=>password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT),'perfil'=>'ADMIN_PCM','ativo'=>true,'created'=>$now,'modified'=>$now]));
    $table=Access::table('Setores');
    $sector=$table->find()->where(['area_totvs'=>'CALDEI','filial_totvs'=>'01','ativo'=>true])->first();
    $ownedSector=false;
    if (!$sector) { $sector=$table->saveOrFail($table->newEntity(['nome'=>$tag,'area_totvs'=>'CALDEI','filial_totvs'=>'01','ativo'=>true])); $ownedSector=true; }
    $table=Access::table('Entressafras'); $season=$table->saveOrFail($table->newEntity(['nome'=>$tag,'data_inicio'=>'2026-01-01','data_fim'=>'2027-12-31','status'=>'planejamento']));
    $table=Access::table('Cadernos'); $notebook=$table->saveOrFail($table->newEntity(['nome'=>$tag,'setor_id'=>$sector->id,'entressafra_id'=>$season->id,'ativo'=>true]));
    $session=Session::create(Cake\Core\Configure::read('Session'));
    // Cake's CLI session is intentionally in-memory; use a real temporary PHP session for HTTP.
    session_start();
    $_SESSION['Auth']=['id'=>$user->id,'stamp'=>hash('sha256',$user->senha_hash),'last'=>time()];
    $cookie=session_name().'='.session_id(); session_write_close();
    $get=static function(string $path) use($cookie): array {
        $context=stream_context_create(['http'=>['header'=>"Cookie: $cookie\r\nAccept: application/json\r\n",'ignore_errors'=>true,'follow_location'=>0,'timeout'=>20]]);
        $body=file_get_contents('http://127.0.0.1:8766'.$path,false,$context);
        if ($body===false || !str_contains($http_response_header[0]??'','200')) { preg_match('/\s(\d{3})\s/',$http_response_header[0]??'', $status); throw new RuntimeException('HTTP indisponível.',(int)($status[1]??0)); }
        return json_decode($body,true,512,JSON_THROW_ON_ERROR);
    };
    $stage='HTTP funcionários'; $baseline=null;
    foreach (['Maria','MARIA','maria'] as $term) {
        $data=$get('/apontamentos/funcionarios?q='.rawurlencode($term));
        if (empty($data['results']) || count($data['results'])>20) { throw new RuntimeException(); }
        $fingerprint=hash('sha256',json_encode($data['results'],JSON_THROW_ON_ERROR));
        if ($baseline!==null && $baseline!==$fingerprint) { throw new RuntimeException(); } $baseline=$fingerprint;
        $eligible=array_filter($data['results'],fn($r)=>$r['ativo']===true);
        if (!$eligible) { throw new RuntimeException(); }
        $selected=reset($eligible);
        foreach(['nome','matricula','funcao','situacao','chave'] as $field) { if (!isset($selected[$field])) { throw new RuntimeException(); } }
    }
    $number=$get('/apontamentos/funcionarios?q='.rawurlencode($selected['matricula']));
    if (!array_filter($number['results'],fn($r)=>$r['chave']===$selected['chave'])) { throw new RuntimeException(); }
    $injection=$get('/apontamentos/funcionarios?q='.rawurlencode("' OR 1=1 --"));
    if ($injection['results']!==[]) { throw new RuntimeException(); }
    $stage='HTTP O.S.';
    $path='/apontamentos/ordens?q=005523&setor_id='.$sector->id.'&caderno_id='.$notebook->id;
    $data=$get($path);
    if (count($data['results'])!==1 || $data['results'][0]['numero']!=='005523' || $data['results'][0]['selecionavel']!==false || $data['results'][0]['id']!==null) { throw new RuntimeException(); }
    $stage='vínculo temporário';
    $key=App\Integration\Totvs\Orders::key('01','005523');
    if (!Access::table('Atividades')->exists(['os_chave'=>$key])) {
        $activity=(new App\Service\Activities(new App\Integration\Totvs\Gateway()))->link((int)$user->id,(int)$notebook->id,$key);
        $data=$get($path);
        if ($data['results'][0]['selecionavel']!==true || $data['results'][0]['id']!==(string)$activity) { throw new RuntimeException(); }
    }
    $stage='arquivos JavaScript';
    $context=stream_context_create(['http'=>['header'=>"Cookie: $cookie\r\n",'ignore_errors'=>true,'follow_location'=>0,'timeout'=>20]]);
    $form=file_get_contents('http://127.0.0.1:8766/apontamentos/add',false,$context);
    if (!str_contains($form,'id="funcionario-resumo"') || !str_contains($form,'id="funcionario-chave"') || str_contains($form,'funcionario_exibicao_')) { throw new RuntimeException(); }
    foreach (['employee-autocomplete','order-autocomplete'] as $name) {
        if (!str_contains($form,$name.'.js?v=')) { throw new RuntimeException(); }
        $body=file_get_contents('http://127.0.0.1:8766/js/'.$name.'.js');
        if ($body!==file_get_contents(WWW_ROOT.'js/'.$name.'.js')) { throw new RuntimeException(); }
    }
} catch (Throwable $error) {
    $failed=true; $failureType=get_class($error); $failureCode=(int)$error->getCode();
} finally {
    if ($session && session_id()!=='') { session_start(); $_SESSION=[]; session_destroy(); }
    if ($activity) {
        Access::table('Auditorias')->deleteAll(['usuario_id'=>$user->id]);
        Access::table('Atividades')->deleteAll(['id'=>$activity,'caderno_id'=>$notebook->id]);
    }
    if ($notebook) { Access::table('Cadernos')->deleteAll(['id'=>$notebook->id,'nome'=>$tag]); }
    if ($season) { Access::table('Entressafras')->deleteAll(['id'=>$season->id,'nome'=>$tag]); }
    if ($sector && ($ownedSector??false)) { Access::table('Setores')->deleteAll(['id'=>$sector->id,'nome'=>$tag]); }
    if ($user) { Access::table('Usuarios')->deleteAll(['id'=>$user->id,'login'=>$tag]); }
}
if ($failed) { fwrite(STDERR,'Falha na etapa '.$stage.' ('.$failureType.', código '.$failureCode.'). Dados sensíveis omitidos.'.PHP_EOL); exit(1); }
echo 'HTTP autenticado real: nomes com variação de caixa, matrícula e SQL Injection: OK.',PHP_EOL;
echo 'HTTP O.S. 005523: fonte real visível sem vínculo, seleção habilitada somente com atividade autorizada: OK.',PHP_EOL;
echo 'JavaScript servido corresponde aos arquivos locais: OK. Registros temporários removidos.',PHP_EOL;
