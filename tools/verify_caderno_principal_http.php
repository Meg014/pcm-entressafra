<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';require dirname(__DIR__).'/config/bootstrap.php';
use App\Service\Access;
use Cake\Http\Session;
$db=Access::table('Usuarios')->getConnection();if($db->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra')exit(2);
$tables=array_column($db->execute("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename")->fetchAll('assoc'),'tablename');
$hashes=function()use($db,$tables){$out=[];foreach($tables as $table){$rows=array_column($db->execute('SELECT row_to_json(t)::text AS row FROM "'.$table.'" t')->fetchAll('assoc'),'row');sort($rows,SORT_STRING);$out[$table]=hash('sha256',implode("\n",$rows));}return $out;};
$before=$hashes();$admin=Access::table('Usuarios')->find()->where(['perfil'=>'ADMIN_PCM','ativo'=>true])->firstOrFail();$season=Access::table('Entressafras')->find()->orderBy(['id'=>'DESC'])->firstOrFail();
$expected=(new \App\Service\SetoresApresentacao())->availableAreas((int)$season->id)->find('list')->orderBy(['nome'=>'ASC'])->all()->toArray();
Session::create(Cake\Core\Configure::read('Session'));session_start();$_SESSION=['Auth'=>['id'=>$admin->id,'stamp'=>hash('sha256',$admin->senha_hash),'last'=>time()],'Periodo'=>['id'=>$season->id,'usuario_id'=>$admin->id]];$cookie=session_name().'='.session_id();session_write_close();
try{
    $body=file_get_contents('http://127.0.0.1:8766/cadernos/add',false,stream_context_create(['http'=>['header'=>'Cookie: '.$cookie."\r\n",'ignore_errors'=>true,'follow_location'=>0,'timeout'=>15]]));
    if(!str_contains($http_response_header[0]??'',' 200 '))throw new RuntimeException('Formulário indisponível.');
    $dom=new DOMDocument();@$dom->loadHTML((string)$body);$xp=new DOMXPath($dom);$found=[];
    foreach($xp->query('//select[@name="area_curva_id"]/option[@value!=""]') as $option)$found[(int)$option->getAttribute('value')]=$option->textContent;
    if($found!==$expected||$xp->query('//*[@required]')->length!==2||$xp->query('//select[contains(@name,"setores")]')->length)throw new RuntimeException('Campos ou setores divergentes.');
    file_put_contents(TMP.'caderno-principal-preview.html',str_replace(['href="/','src="/'],['href="http://localhost:8766/','src="http://localhost:8766/'],(string)$body));
    if($before!==$hashes())throw new RuntimeException('Dados alterados durante verificação.');
}finally{session_start();$_SESSION=[];session_destroy();}
echo json_encode(['status'=>'OK','entressafra'=>$season->nome,'setores_existentes'=>$found,'obrigatorios'=>['nome','area_curva_id'],'sem_gravacoes'=>true,'tabelas_preservadas'=>count($tables)],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
