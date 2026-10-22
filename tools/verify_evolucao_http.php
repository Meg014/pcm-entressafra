<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/config/bootstrap.php';
use App\Service\Access;
use Cake\Http\Session;
$db=Access::table('Usuarios')->getConnection();
if($db->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra'){exit(2);}
$users=[];$tag='__http_evolucao_'.bin2hex(random_bytes(6));$failed=false;$stage='preparação';
Session::create(Cake\Core\Configure::read('Session'));
try {
    foreach(['ADMIN_PCM','PCM','RESPONSAVEL_SETOR'] as $role) {
        $stage=$role;$t=Access::table('Usuarios');$now=gmdate('Y-m-d H:i:s');
        $u=$t->saveOrFail(Access::entity($t,['nome'=>$tag,'login'=>$tag.$role,'senha_hash'=>password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT),'perfil'=>$role,'ativo'=>true,'created'=>$now,'modified'=>$now]));$users[]=$u;
        session_start();$_SESSION['Auth']=['id'=>$u->id,'stamp'=>hash('sha256',$u->senha_hash),'last'=>time()];$_SESSION['Periodo']=['id'=>(int)Access::table('Entressafras')->find()->where(['status'=>'ativa'])->firstOrFail()->id,'usuario_id'=>(int)$u->id];$cookie=session_name().'='.session_id();session_write_close();
        foreach(['/evolucao','/evolucao/configuracao','/controle-pcm','/evolucao/modelo/pesos','/evolucao/modelo/metas'] as $path) {
            $stage=$role.' '.$path;
            $ctx=stream_context_create(['http'=>['header'=>"Cookie: $cookie\r\n",'ignore_errors'=>true,'follow_location'=>0,'timeout'=>30]]);
            $body=file_get_contents('http://127.0.0.1:8766'.$path,false,$ctx);preg_match('/\s(\d{3})\s/',$http_response_header[0]??'',$match);$status=(int)($match[1]??0);
            $expected=$role==='RESPONSAVEL_SETOR'&&$path!=='/evolucao'?403:200;
            if($body===false||$status!==$expected){throw new RuntimeException();}
            if($expected===200&&str_contains($path,'/modelo/')&&!str_starts_with($body,'PK')){throw new RuntimeException();}
            if($path==='/evolucao'&&(!str_contains($body,'Salvar alterações em lote')||!str_contains($body,'/js/evolucao.js'))){throw new RuntimeException();}
        }
    }
}catch(Throwable){$failed=true;}
finally{
    if(session_id()!==''){session_start();$_SESSION=[];session_destroy();}
    foreach($users as $u){Access::table('UsuariosSetores')->deleteAll(['usuario_id'=>$u->id]);Access::table('Usuarios')->deleteAll(['id'=>$u->id,'nome'=>$tag]);}
}
if($failed){fwrite(STDERR,'Falha HTTP em '.$stage.'; conteúdo sensível omitido.'.PHP_EOL);exit(1);}
echo 'HTTP local: evolução, configuração, controle PCM e modelos Excel validados nos três perfis. Usuários temporários removidos.',PHP_EOL;
