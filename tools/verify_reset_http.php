<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';require dirname(__DIR__).'/config/bootstrap.php';
use App\Service\Access;
use Cake\Http\Session;
$db=Access::table('Usuarios')->getConnection();
if($db->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra')exit(2);
$folder=dirname(__DIR__).'/backups/reinicializacao_20261022_204134';
$approved=json_decode(file_get_contents($folder.'/manifesto.json'),true,512,JSON_THROW_ON_ERROR);
$executed=json_decode(file_get_contents($folder.'/limpeza_autorizada_20261023.json'),true,512,JSON_THROW_ON_ERROR);
$fingerprint=function()use($db,$approved){$out=[];foreach(array_keys($approved['tabelas']) as $table){$rows=array_column($db->execute('SELECT row_to_json(t)::text AS row FROM public."'.$table.'" t')->fetchAll('assoc'),'row');sort($rows,SORT_STRING);$out[$table]=['registros'=>count($rows),'sha256'=>hash('sha256',implode("\n",$rows))];}return $out;};
$before=$fingerprint();foreach($before as $table=>$item)if($item['registros']!==$executed['contagem_final'][$table])throw new RuntimeException('Contagem final divergente.');
if($before['usuarios']!==$approved['tabelas']['usuarios']||$before['cake_migrations']!==$approved['tabelas']['cake_migrations'])throw new RuntimeException('Administrador/migrations divergentes.');
$admin=Access::actor(3);Session::create(Cake\Core\Configure::read('Session'));session_start();
$_SESSION=['Auth'=>['id'=>3,'stamp'=>hash('sha256',$admin->senha_hash),'last'=>time()],'Periodo'=>['id'=>22,'usuario_id'=>3],'Curva'=>['preview'=>['season'=>22]]];
$cookie=session_name().'='.session_id();session_write_close();
$request=function(string $path)use(&$cookie){$body=file_get_contents('http://127.0.0.1:8766'.$path,false,stream_context_create(['http'=>['header'=>'Cookie: '.$cookie."\r\n",'ignore_errors'=>true,'follow_location'=>0,'timeout'=>15]]));preg_match('/\s(\d{3})\s/',$http_response_header[0]??'',$match);return [(int)($match[1]??0),(string)$body];};
try{
    [$status,$body]=$request('/login');if($status!==200||!str_contains($body,'name="senha"'))throw new RuntimeException('Tela de login indisponível.');
    [$status]=$request('/controle-pcm');if($status!==302)throw new RuntimeException('Período antigo não descartado.');
    [$status,$body]=$request('/escolher-entressafra');if($status!==200||!str_contains($body,'Nenhuma entressafra cadastrada.'))throw new RuntimeException('Sessão administrativa ou contexto vazio inválido.');
    [$status,$body]=$request('/entressafras/add');if($status!==200||!str_contains($body,'name="nome"'))throw new RuntimeException('Cadastro da primeira entressafra indisponível.');
    if($before!==$fingerprint())throw new RuntimeException('Verificação alterou o banco.');
}finally{session_start();$_SESSION=[];session_destroy();}
echo 'OK: tela de login, acesso autenticado ADMIN_PCM, descarte do período antigo, cadastro da primeira entressafra e integridade das 21 tabelas. Cadastro/senha do administrador e migrations idênticos ao backup. Sem gravações. A senha em texto não foi solicitada nem usada nesta verificação.',PHP_EOL;
