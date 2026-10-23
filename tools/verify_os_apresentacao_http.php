<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';require dirname(__DIR__).'/config/bootstrap.php';
use App\Service\Access;
use Cake\Http\Session;
$db=Access::table('Usuarios')->getConnection();if($db->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra')exit(2);
$tables=array_column($db->execute("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename")->fetchAll('assoc'),'tablename');
$hashes=function()use($db,$tables){$out=[];foreach($tables as $table){$rows=array_column($db->execute('SELECT row_to_json(t)::text AS row FROM "'.$table.'" t')->fetchAll('assoc'),'row');sort($rows,SORT_STRING);$out[$table]=hash('sha256',implode("\n",$rows));}return $out;};
$before=$hashes();$admin=Access::table('Usuarios')->find()->where(['perfil'=>'ADMIN_PCM','ativo'=>true])->firstOrFail();$season=Access::table('Entressafras')->find()->orderBy(['id'=>'DESC'])->firstOrFail();
$activity=Access::table('Atividades')->find()->where(['entressafra_id'=>$season->id])->orderBy(['id'=>'ASC'])->first();
Session::create(Cake\Core\Configure::read('Session'));session_start();$_SESSION=['Auth'=>['id'=>$admin->id,'stamp'=>hash('sha256',$admin->senha_hash),'last'=>time()],'Periodo'=>['id'=>$season->id,'usuario_id'=>$admin->id]];$cookie=session_name().'='.session_id();session_write_close();
$request=function(string $path)use($cookie){$body=file_get_contents('http://127.0.0.1:8766'.$path,false,stream_context_create(['http'=>['header'=>'Cookie: '.$cookie."\r\n",'ignore_errors'=>true,'follow_location'=>0,'timeout'=>20]]));if(!str_contains($http_response_header[0]??'',' 200 '))throw new RuntimeException('Tela/consulta indisponível: '.$path);return (string)$body;};
$checked=0;$remote=0;
try{
    $paths=['/lancar-porcentagem','/apontamentos/add','/atividades','/controle-pcm','/cadernos'];
    if($activity){$paths[]='/atividades/view/'.$activity->id;$paths[]='/lancar-porcentagem?atividade_id='.$activity->id;}
    foreach($paths as $path){$body=$request($path);$dom=new DOMDocument();@$dom->loadHTML($body);$xp=new DOMXPath($dom);foreach($xp->query('//script|//style') as $node)$node->parentNode->removeChild($node);
        if(preg_match('/filial\s*(?:da O\.S\.|01)|TOTVS\s+01|C\.C\./iu',$dom->textContent))throw new RuntimeException('Metadado técnico visível em '.$path);
        if(str_starts_with($path,'/lancar-porcentagem?'))file_put_contents(TMP.'os-apresentacao-preview.html',str_replace(['href="/','src="/'],['href="http://localhost:8766/','src="http://localhost:8766/'],$body));
        $checked++;
    }
    if($activity){
        $data=json_decode($request('/operacao/ordens?q='.rawurlencode($activity->os_numero)),true,512,JSON_THROW_ON_ERROR);
        foreach($data['results']??[] as $row){if(!isset($row['descricao'],$row['setor_apresentacao'],$row['rotulo_setor'],$row['filial']))throw new RuntimeException('Projeção incompleta.');if($row['filial']!=='01')throw new RuntimeException('Restrição interna divergente.');$remote++;}
    }
    if($before!==$hashes())throw new RuntimeException('Banco alterado durante consulta.');
}finally{session_start();$_SESSION=[];session_destroy();}
echo json_encode(['status'=>'OK','telas'=>$checked,'resultados_reais_totvs'=>$remote,'tabelas_preservadas'=>count($tables),'sem_gravacoes'=>true],JSON_PRETTY_PRINT),PHP_EOL;
