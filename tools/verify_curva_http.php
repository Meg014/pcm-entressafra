<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';require dirname(__DIR__).'/config/bootstrap.php';
use App\Service\Access;use Cake\Http\Session;
$db=Access::table('Usuarios')->getConnection();if($db->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra')exit(2);
$tag='__curva_http_'.bin2hex(random_bytes(6));$users=[];$failed=false;$stage='preparação';Session::create(Cake\Core\Configure::read('Session'));
try {
    $book=Access::table('Cadernos')->find()->firstOrFail();$area=Access::table('AreasFabrica')->find()->where(['nome'=>'Moenda'])->firstOrFail();
    foreach(['ADMIN_PCM','PCM','COMPUTADOR'] as $role){
        $t=Access::table('Usuarios');$stamp=gmdate('Y-m-d H:i:s');$computer=$role==='COMPUTADOR';$u=$t->saveOrFail(Access::entity($t,['nome'=>$tag,'login'=>$tag.$role,'senha_hash'=>password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT),'perfil'=>$computer?'RESPONSAVEL_SETOR':$role,'computador_operacional'=>$computer,'ativo'=>true,'created'=>$stamp,'modified'=>$stamp]));$users[]=$u;
        session_start();$_SESSION['Auth']=['id'=>$u->id,'stamp'=>hash('sha256',$u->senha_hash),'last'=>time()];$_SESSION['Periodo']=['id'=>(int)Access::table('Entressafras')->find()->where(['status'=>'ativa'])->firstOrFail()->id,'usuario_id'=>(int)$u->id];$cookie=session_name().'='.session_id();session_write_close();
        foreach(['/controle-pcm','/controle-pcm?area_id='.$area->id,'/controle-pcm?demonstracao=eletrica','/organizacao-curva?entressafra_id='.$book->entressafra_id.'&caderno_id='.$book->id,'/cadernos/'.$book->id.'/vincular-lista','/evolucao/analise','/prazos-entressafra'] as $path){
            $stage=$role.' '.$path;$ctx=stream_context_create(['http'=>['header'=>"Cookie: $cookie\r\n",'ignore_errors'=>true,'follow_location'=>0,'timeout'=>30]]);
            $body=file_get_contents('http://127.0.0.1:8766'.$path,false,$ctx);preg_match('/\s(\d{3})\s/',$http_response_header[0]??'',$m);
            if((int)($m[1]??0)!==($computer?403:200))throw new RuntimeException();
            if(!$computer&&str_starts_with($path,'/controle-pcm')){
                if(substr_count($body,'<tr data-os-row>')>10||str_contains($body,'Versão oficial de pesos')||str_contains($body,'Classificação física das O.S.'))throw new RuntimeException();
                if($path==='/controle-pcm?demonstracao=eletrica'&&!str_contains($body,'DEMONSTRAÇÃO ISOLADA'))throw new RuntimeException();
                if($role==='ADMIN_PCM'&&($argv[1]??'')==='--preview'){
                    $body=str_replace('<head>','<head><base href="http://localhost:8766/">',$body);
                    $body=str_replace(['href="/','src="/'],['href="http://localhost:8766/','src="http://localhost:8766/'],$body);
                    file_put_contents(TMP.($path==='/controle-pcm?demonstracao=eletrica'?'curva-eletrica-demo.html':($path==='/controle-pcm'?'curva-controle.html':'curva-moenda.html')),$body);
                }
            }
        }
    }
}catch(Throwable){$failed=true;}finally{if(session_id()!==''){session_start();$_SESSION=[];session_destroy();}foreach($users as $u)Access::table('Usuarios')->deleteAll(['id'=>$u->id,'nome'=>$tag]);}
if($failed){fwrite(STDERR,'Falha HTTP em '.$stage.'; conteúdo sensível omitido.'.PHP_EOL);exit(1);}
echo 'OK HTTP: Controle PCM limpo, Moenda, demonstração, organização, formulário de lista, análise e prazos nos três perfis. Contas temporárias removidas.',PHP_EOL;
