<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';require dirname(__DIR__).'/config/bootstrap.php';
use App\Service\{Access,PeriodoContexto,OsSemCaderno};
use Cake\Http\Session;
$db=Access::table('Usuarios')->getConnection();if($db->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra')exit(2);
$hashes=function(){
    $db=Access::table('Usuarios')->getConnection();$out=[];
    foreach(array_column($db->execute("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename")->fetchAll('assoc'),'tablename') as $table){
        if(in_array($table,['cake_migrations','pendencias_consultas','pendencias_ordens'],true))continue;
        $rows=array_column($db->execute('SELECT row_to_json(t)::text AS row FROM "'.$table.'" t')->fetchAll('assoc'),'row');sort($rows,SORT_STRING);$out[$table]=['count'=>count($rows),'hash'=>hash('sha256',implode("\n",$rows))];
    }return $out;
};
$baseline=TMP.'os-sem-caderno-baseline.json';
if(($argv[1]??'')==='baseline'){file_put_contents($baseline,json_encode($hashes(),JSON_THROW_ON_ERROR));echo "Baseline dos dados existentes registrada.\n";exit;}
$before=json_decode(file_get_contents($baseline),true,512,JSON_THROW_ON_ERROR);
$admin=Access::table('Usuarios')->find()->where(['perfil'=>'ADMIN_PCM','ativo'=>true])->firstOrFail();$season=Access::table('Entressafras')->find()->where(['status'=>'ativa'])->orderBy(['id'=>'DESC'])->firstOrFail();
PeriodoContexto::set((int)$season->id);$s=new OsSemCaderno();$token=$s->create((int)$admin->id);
for($i=0;$i<100&&$s->job((int)$admin->id,$token)->estado==='consultando';$i++)$s->scan((int)$admin->id,$token);
$data=$s->summary((int)$admin->id,$token);if($data['job']->estado!=='pronto')throw new RuntimeException('Consulta incompleta.');
Session::create(Cake\Core\Configure::read('Session'));session_start();$_SESSION=['Auth'=>['id'=>$admin->id,'stamp'=>hash('sha256',$admin->senha_hash),'last'=>time()],'Periodo'=>['id'=>$season->id,'usuario_id'=>$admin->id]];$cookie=session_name().'='.session_id();session_write_close();
try{
    foreach([1,min(2,$data['pages']),min(3,$data['pages'])] as $page){
        $body=file_get_contents('http://127.0.0.1:8766/os-sem-caderno?token='.$token.'&pagina='.$page,false,stream_context_create(['http'=>['header'=>'Cookie: '.$cookie."\r\n",'ignore_errors'=>true,'follow_location'=>0,'timeout'=>20]]));
        if(!str_contains($http_response_header[0]??'',' 200 '))throw new RuntimeException('Tela indisponível.');
        $dom=new DOMDocument();@$dom->loadHTML($body);$xp=new DOMXPath($dom);foreach($xp->query('//script|//style') as $node)$node->parentNode->removeChild($node);
        if(preg_match('/filial|TOTVS\s+01|centro de custo|C\.C\./iu',$dom->textContent))throw new RuntimeException('Metadado técnico visível.');
        if($xp->query('//input[@name="numeros[]"]')->length>10)throw new RuntimeException('Página excessiva.');
        if($page===1)file_put_contents(TMP.'os-sem-caderno-preview.html',str_replace(['href="/','src="/'],['href="http://localhost:8766/','src="http://localhost:8766/'],$body));
    }
    if($before!==$hashes())throw new RuntimeException('Dados operacionais alterados.');
}finally{session_start();$_SESSION=[];session_destroy();PeriodoContexto::set(null);}
echo json_encode(['status'=>'OK','consultadas'=>$data['job']->consultadas,'sem_caderno'=>$data['pendentes'],'paginas'=>$data['pages'],'paginas_http_conferidas'=>3,'tabelas_existentes_preservadas'=>count($before),'vinculos_reais_executados'=>0,'consulta_salva'=>$token],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
