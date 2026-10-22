<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';require dirname(__DIR__).'/config/bootstrap.php';
use App\Service\Access;use Cake\Http\Session;
$db=Access::table('Usuarios')->getConnection();if($db->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra')exit(2);
$tables=['entressafras','setores','cadernos','cadernos_setores','cadernos_areas','atividades','apontamentos','evolucao_registros','evolucao_semanas','evolucao_lotes','evolucao_versoes','evolucao_pesos','evolucao_metas','auditorias','areas_fabrica','calendarios_operacionais','vinculos_lotes','vinculos_itens','usuarios'];
$fingerprint=function()use($tables,$db){$out=[];foreach($tables as $table){$rows=array_map(fn($r)=>json_encode($r,JSON_THROW_ON_ERROR),$db->execute('SELECT * FROM '.$table)->fetchAll('assoc'));sort($rows);$out[$table]=hash('sha256',implode("\n",$rows));}return $out;};
$before=$fingerprint();$users=[];$closed=null;$book=null;$tag='__periodo_http_'.bin2hex(random_bytes(6));$failed=false;$stage='preparação';
Session::create(Cake\Core\Configure::read('Session'));$jar=[];$csrf='';
$request=function(string $path,array $data=[],bool $post=false)use(&$jar,&$csrf): array {
    $cookies=[];foreach($jar as $key=>$value)$cookies[]=$key.'='.$value;
    $http=['method'=>$post?'POST':'GET','header'=>'Cookie: '.implode('; ',$cookies)."\r\n",'ignore_errors'=>true,'follow_location'=>0,'timeout'=>20];
    if($post){$http['header'].="Content-Type: application/x-www-form-urlencoded\r\n";$http['content']=http_build_query($data+['_csrfToken'=>$csrf]);}
    $body=file_get_contents('http://127.0.0.1:8766'.$path,false,stream_context_create(['http'=>$http]));
    preg_match('/\s(\d{3})\s/',$http_response_header[0]??'',$m);$status=(int)($m[1]??0);
    foreach($http_response_header as $header)if(preg_match('/^Set-Cookie:\s*([^=;]+)=([^;]*)/i',$header,$c))$jar[$c[1]]=$c[2];
    if(is_string($body)&&$body!==''){ $dom=new DOMDocument();@$dom->loadHTML($body);$token=(new DOMXPath($dom))->evaluate('string(//input[@name="_csrfToken"]/@value)');if($token!=='')$csrf=$token; }
    return [$status,$body?:''];
};
$snapshot=function(string $name,string $body)use($argv){if(($argv[1]??'')!=='--preview')return;$body=str_replace(['href="/','src="/'],['href="http://localhost:8766/','src="http://localhost:8766/'],$body);file_put_contents(TMP.$name.'.html',$body);};
try {
    $season=Access::table('Entressafras')->find()->where(['status'=>'ativa'])->firstOrFail();
    foreach(['ADMIN_PCM','COMPUTADOR'] as $role){$t=Access::table('Usuarios');$stamp=gmdate('Y-m-d H:i:s');$users[]=$t->saveOrFail(Access::entity($t,['nome'=>'Validação de navegação','login'=>$tag.$role,'perfil'=>$role==='COMPUTADOR'?'RESPONSAVEL_SETOR':'ADMIN_PCM','computador_operacional'=>$role==='COMPUTADOR','ativo'=>true,'senha_hash'=>password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT),'created'=>$stamp,'modified'=>$stamp]));}
    $u=$users[0];session_start();$_SESSION=['Auth'=>['id'=>$u->id,'stamp'=>hash('sha256',$u->senha_hash),'last'=>time()]];$jar=[session_name()=>session_id()];session_write_close();
    $stage='redirecionamento inicial';[$status]=$request('/controle-pcm');if($status!==302){$stage.=' (HTTP '.$status.')';throw new RuntimeException();}
    $stage='formulário de seleção';[$status,$body]=$request('/escolher-entressafra');if($status!==200||!str_contains($body,'Selecione o período')){$stage.=' (HTTP '.$status.')';throw new RuntimeException();}$snapshot('periodo-selecao',$body);
    $stage='POST de seleção';if($request('/escolher-entressafra/entrar',['periodo_id'=>$season->id],true)[0]!==302)throw new RuntimeException();
    $stage='persistência e navegação';foreach(['/controle-pcm','/cadernos','/setores','/atividades','/controle-pcm'] as $path){[$status,$body]=$request($path);if($status!==200||!str_contains($body,'Entressafra '.htmlspecialchars($season->nome,ENT_QUOTES)))throw new RuntimeException();if($path==='/setores')$snapshot('periodo-setores',$body);}
    $stage='acessos reais à vinculação e descrição';
    $target=Access::table('Cadernos')->find()->where(['entressafra_id'=>$season->id,'nome'=>'CALDEIRARIA - MOENDA'])->first();
    if($target){
        foreach(['/cadernos','/cadernos/view/'.$target->id,'/cadernos/'.$target->id.'/vincular-lista'] as $path){[$status,$body]=$request($path);if($status!==200||!str_contains($body,'Vincular O.S. por lista'))throw new RuntimeException();$snapshot('descricao-lista-'.str_replace('/','-',$path),$body);}
        $a=Access::table('Atividades')->find()->where(['entressafra_id'=>$season->id,'os_numero'=>'005978','os_filial'=>'01'])->first();
        if($a){[$status,$body]=$request('/atividades/view/'.$a->id.'?consultar_descricao=1');if($status!==200||!str_contains($body,'STJ010.TJ_OBSERVA')||!str_contains($body,'Histórico de porcentagens'))throw new RuntimeException();$snapshot('descricao-os-005978',$body);}
    }
    $area=Access::table('AreasFabrica')->find()->where(['nome'=>'Moenda'])->firstOrFail();[$status,$body]=$request('/setores/curva/'.$area->id);if($status!==200||!str_contains($body,'2027-04-10'))throw new RuntimeException();$snapshot('periodo-setor-moenda',$body);
    $stage='troca e bloqueio cruzado';$t=Access::table('Entressafras');$closed=$t->saveOrFail($t->newEntity(['nome'=>$tag,'data_inicio'=>'2025-10-19','data_fim'=>'2026-04-10','status'=>'encerrada']));
    $sector=Access::table('Setores')->find()->firstOrFail();$t=Access::table('Cadernos');$book=$t->saveOrFail($t->newEntity(['nome'=>$tag,'entressafra_id'=>$closed->id,'setor_id'=>$sector->id,'ativo'=>true]));
    if($request('/controle-pcm?entressafra_id='.$closed->id)[0]!==403)throw new RuntimeException();
    if(!in_array($request('/cadernos/view/'.$book->id)[0],[403,404],true))throw new RuntimeException();
    if($request('/escolher-entressafra/entrar',['periodo_id'=>$closed->id],true)[0]!==302)throw new RuntimeException();
    foreach(['/controle-pcm','/cadernos','/prazos-entressafra','/evolucao/analise','/organizacao-curva','/vinculos-lote'] as $path){[$status,$body]=$request($path);if($status!==200||!str_contains($body,'consulta histórica'))throw new RuntimeException();}
    $stage='bloqueio de lançamento encerrado';if($request('/apontamentos/add',['atividade_id'=>1,'descricao'=>'Não deve gravar'],true)[0]!==403)throw new RuntimeException();
    if($request('/escolher-entressafra/entrar',['periodo_id'=>$season->id],true)[0]!==302)throw new RuntimeException();if($request('/setores')[0]!==200)throw new RuntimeException();
    $stage='computador ativo sem troca';$u=$users[1];session_start();$_SESSION=['Auth'=>['id'=>$u->id,'stamp'=>hash('sha256',$u->senha_hash),'last'=>time()],'Periodo'=>['id'=>$closed->id,'usuario_id'=>$u->id]];session_write_close();
    [$status,$body]=$request('/apontamentos');if($status!==200||str_contains($body,'Trocar entressafra')||!str_contains($body,'Entressafra '.htmlspecialchars($season->nome,ENT_QUOTES)))throw new RuntimeException();
    if($request('/escolher-entressafra')[0]!==403)throw new RuntimeException();
}catch(Throwable){$failed=true;}finally{
    if(session_id()!==''){session_start();$_SESSION=[];session_destroy();}
    if($book)Access::table('Cadernos')->deleteAll(['id'=>$book->id,'nome'=>$tag]);
    if($closed)Access::table('Entressafras')->deleteAll(['id'=>$closed->id,'nome'=>$tag]);
    foreach($users as $u)Access::table('Usuarios')->deleteAll(['id'=>$u->id,'login'=>$u->login]);
}
if($before!==$fingerprint()){$failed=true;$stage='integridade dos dados';}
if($failed){fwrite(STDERR,'Falha HTTP em '.$stage.'; dados sensíveis omitidos.'.PHP_EOL);exit(1);}
echo 'OK HTTP real: escolha por POST/CSRF; sessão persistente; troca; bloqueio cruzado; encerrada somente leitura; setores/datas; rotas antigas; Computador na ativa. Fixtures removidas e hashes de 19 tabelas preservados.',PHP_EOL;
