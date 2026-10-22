<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';require dirname(__DIR__).'/config/bootstrap.php';
use App\Service\Access;
use Cake\Http\Session;
$db=Access::table('Usuarios')->getConnection();
if($db->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra')exit(2);
$job=null;
foreach($db->execute('SELECT * FROM vinculos_lotes WHERE caderno_id=23 ORDER BY id DESC LIMIT 20')->fetchAll('assoc') as $candidate){$f=json_decode($candidate['filtros'],true);if(($f['tipo']??'')==='lista'&&($f['unicas']??0)===45){$job=$candidate;break;}}
if(!$job)throw new RuntimeException('Prévia de 45 O.S. não encontrada.');
$fingerprint=function()use($db){$out=[];foreach(['entressafras','setores','cadernos','cadernos_setores','cadernos_areas','atividades','apontamentos','evolucao_registros','evolucao_semanas','evolucao_lotes','evolucao_versoes','evolucao_pesos','evolucao_metas','auditorias','areas_fabrica','calendarios_operacionais','vinculos_lotes','vinculos_itens','usuarios'] as $table){$rows=array_map(fn($r)=>json_encode($r,JSON_THROW_ON_ERROR),$db->execute('SELECT * FROM '.$table)->fetchAll('assoc'));sort($rows);$out[$table]=hash('sha256',implode("\n",$rows));}return $out;};
$before=$fingerprint();$user=Access::actor((int)$job['usuario_id']);$f=json_decode($job['filtros'],true);
Session::create(Cake\Core\Configure::read('Session'));session_start();
$_SESSION=['Auth'=>['id'=>$user->id,'stamp'=>hash('sha256',$user->senha_hash),'last'=>time()],'Periodo'=>['id'=>$f['entressafra_id'],'usuario_id'=>$user->id]];
$cookie=session_name().'='.session_id();session_write_close();
$request=function(string $path)use(&$cookie): string{
    $body=file_get_contents('http://127.0.0.1:8766'.$path,false,stream_context_create(['http'=>['header'=>'Cookie: '.$cookie."\r\n",'ignore_errors'=>true,'follow_location'=>0,'timeout'=>20]]));
    if(!str_contains($http_response_header[0]??'',' 200 '))throw new RuntimeException('Falha HTTP na prévia.');
    return (string)$body;
};
try{
    $base='/cadernos/23/vincular-lista';$url=$base.'?token='.$job['token'];
    $home=$request($base);if(!str_contains($home,'Retomar prévia de 45 O.S.'))throw new RuntimeException('Acesso à retomada ausente.');
    foreach(range(1,5) as $page){
        $body=$request($url.'&pagina='.$page);$dom=new DOMDocument();@$dom->loadHTML($body);$xp=new DOMXPath($dom);
        if($xp->query('//table/tbody/tr')->length!==($page===5?5:10))throw new RuntimeException('Página incompleta.');
        foreach($xp->query('//nav[@aria-label="Paginação"]/a') as $link){$parts=parse_url($link->getAttribute('href'));parse_str($parts['query']??'',$q);if(($parts['path']??'')!==$base||($q['token']??'')!==$job['token'])throw new RuntimeException('URL ou token divergente.');$request($link->getAttribute('href'));}
    }
    $request($url); // Reload remains read-only.
    $source=(new \App\Integration\Totvs\ListOrders())->find($f['numbers']);
    $sourceCount=count($source);$suspicious=isset($source['062200'])?'encontrada':'não encontrada na filial 01; preservada sem correção';
    // Exercise the same FOR UPDATE serialization used by ListLinks::locked(),
    // through two independent PostgreSQL connections, without changing the job.
    $other=new \Cake\Database\Connection($db->config());$blocked=false;
    try{
        $db->begin();$db->execute('SELECT id FROM vinculos_lotes WHERE id=? FOR UPDATE',[$job['id']]);
        $other->begin();$other->execute("SET LOCAL lock_timeout = '150ms'");
        try{$other->execute('SELECT id FROM vinculos_lotes WHERE id=? FOR UPDATE',[$job['id']]);}
        catch(\Throwable $error){$blocked=str_contains($error->getMessage(),'55P03')||str_contains($error->getMessage(),'lock timeout');}
        $other->rollback();$db->rollback();
        if(!$blocked)throw new RuntimeException('Bloqueio concorrente não confirmado.');
        $other->begin();$other->execute('SELECT id FROM vinculos_lotes WHERE id=? FOR UPDATE',[$job['id']]);$other->rollback();
    }finally{if($db->inTransaction())$db->rollback();if($other->inTransaction())$other->rollback();}
    if($before!==$fingerprint())throw new RuntimeException('Dados alterados na navegação.');
    $success='OK HTTP real: prévia existente 45/45, cinco páginas (10/10/10/10/5), links seguidos, token/contexto, retomada e reload; concorrência PostgreSQL bloqueada e liberada; '.$sourceCount.'/45 encontradas no TOTVS; 062200 '.$suspicious.'; hashes de 19 tabelas preservados.';
}finally{session_start();$_SESSION=[];session_destroy();}

echo $success,PHP_EOL;
