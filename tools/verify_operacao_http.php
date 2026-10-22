<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';require dirname(__DIR__).'/config/bootstrap.php';
use App\Service\Access;use Cake\Http\Session;
$db=Access::table('Usuarios')->getConnection();if($db->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra')exit(2);
$tag='__op_http_'.bin2hex(random_bytes(6));$users=[];$failed=false;$stage='preparação';Session::create(Cake\Core\Configure::read('Session'));
try{
    foreach(['ADMIN_PCM','PCM','COMPUTADOR'] as $role){
        $t=Access::table('Usuarios');$now=gmdate('Y-m-d H:i:s');$computer=$role==='COMPUTADOR';$u=$t->saveOrFail(Access::entity($t,['nome'=>$tag,'login'=>$tag.$role,'perfil'=>$computer?'RESPONSAVEL_SETOR':$role,'computador_operacional'=>$computer,'senha_hash'=>password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT),'ativo'=>true,'created'=>$now,'modified'=>$now]));$users[]=$u;
        session_start();$_SESSION['Auth']=['id'=>$u->id,'stamp'=>hash('sha256',$u->senha_hash),'last'=>time()];$_SESSION['Periodo']=['id'=>(int)Access::table('Entressafras')->find()->where(['status'=>'ativa'])->firstOrFail()->id,'usuario_id'=>(int)$u->id];$cookie=session_name().'='.session_id();session_write_close();
        foreach(['/apontamentos/add','/lancar-porcentagem','/apontamentos','/fila-pcm','/atividades','/usuarios','/evolucao/configuracao','/prazos-entressafra','/operacao/ordens?q=ZZZZZZZZ'] as $path){
            $stage=$role.' '.$path;$ctx=stream_context_create(['http'=>['header'=>"Cookie: $cookie\r\n",'ignore_errors'=>true,'follow_location'=>0,'timeout'=>30]]);$body=file_get_contents('http://127.0.0.1:8766'.$path,false,$ctx);preg_match('/\s(\d{3})\s/',$http_response_header[0]??'',$status);
            $expected=($computer&&in_array($path,['/fila-pcm','/atividades','/usuarios','/evolucao/configuracao','/prazos-entressafra'],true))||($role==='PCM'&&$path==='/usuarios')?403:200;
            if($body===false||(int)($status[1]??0)!==$expected)throw new RuntimeException();
            if($path==='/apontamentos/add'&&(str_contains($body,'name="entressafra_id"')||str_contains($body,'name="setor_id"')||str_contains($body,'name="caderno_id"')))throw new RuntimeException();
            if($path==='/apontamentos/add'){
                $dom=new DOMDocument();@$dom->loadHTML($body);$xp=new DOMXPath($dom);$token=$xp->evaluate('string(//input[@name="_csrfToken"]/@value)');if($token==='')throw new RuntimeException();$postCookie=$cookie;
                foreach($http_response_header as $header){if(preg_match('/^Set-Cookie:\s*([^=;]+)=([^;]*)/i',$header,$m)&&$m[1]!==session_name())$postCookie.='; '.$m[1].'='.$m[2];}
                $post=stream_context_create(['http'=>['method'=>'POST','header'=>"Cookie: $postCookie\r\nContent-Type: application/json\r\nX-CSRF-Token: $token\r\n",'content'=>json_encode(['os_chave'=>'chave-invalida']), 'ignore_errors'=>true,'follow_location'=>0,'timeout'=>30]]);
                file_get_contents('http://127.0.0.1:8766/operacao/identificar',false,$post);if(!str_contains($http_response_header[0]??'','422'))throw new RuntimeException();
            }
        }
    }
}catch(Throwable){$failed=true;}finally{if(session_id()!==''){session_start();$_SESSION=[];session_destroy();}foreach($users as $u){Access::table('Usuarios')->deleteAll(['id'=>$u->id,'nome'=>$tag]);}}
if($failed){fwrite(STDERR,'Falha HTTP em '.$stage.'; conteúdo sensível omitido.'.PHP_EOL);exit(1);}echo 'HTTP real: telas simples, fila e bloqueios de administração para computador: OK. Contas temporárias removidas.',PHP_EOL;
