<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';require dirname(__DIR__).'/config/bootstrap.php';
use App\Service\Access;use Cake\Http\Session;
$db=Access::table('Usuarios')->getConnection();if($db->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra')exit(2);
$tables=array_column($db->execute("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename")->fetchAll('assoc'),'tablename');
$hashes=function()use($db,$tables){$out=[];foreach($tables as $table){$rows=array_column($db->execute('SELECT row_to_json(t)::text AS row FROM "'.$table.'" t')->fetchAll('assoc'),'row');sort($rows,SORT_STRING);$out[$table]=hash('sha256',implode("\n",$rows));}return $out;};$before=$hashes();
$admin=Access::table('Usuarios')->find()->where(['perfil'=>'ADMIN_PCM','ativo'=>true])->firstOrFail();$season=Access::table('Entressafras')->find()->where(['status'=>'ativa'])->firstOrFail();
$sector=$db->execute('SELECT c.area_curva_id,COUNT(*) AS total FROM atividades a JOIN cadernos c ON c.id=a.caderno_id WHERE a.entressafra_id=? AND c.entressafra_id=? AND c.area_curva_id IS NOT NULL GROUP BY c.area_curva_id ORDER BY total DESC',[$season->id,$season->id])->fetch('assoc');
if(!$sector)throw new RuntimeException('Nenhuma O.S. vinculada para conferir.');$area=(int)$sector['area_curva_id'];
Session::create(Cake\Core\Configure::read('Session'));session_start();$_SESSION=['Auth'=>['id'=>$admin->id,'stamp'=>hash('sha256',$admin->senha_hash),'last'=>time()],'Periodo'=>['id'=>$season->id,'usuario_id'=>$admin->id]];$cookie=session_name().'='.session_id();session_write_close();
$checked=0;
try{
    foreach([1,2] as $page){
        $body=file_get_contents('http://127.0.0.1:8766/lancar-porcentagem?area_id='.$area.'&pagina='.$page,false,stream_context_create(['http'=>['header'=>'Cookie: '.$cookie."\r\n",'ignore_errors'=>true,'follow_location'=>0,'timeout'=>20]]));
        if(!str_contains($http_response_header[0]??'',' 200 '))throw new RuntimeException('Tela indisponível.');
        $dom=new DOMDocument();@$dom->loadHTML($body);$xp=new DOMXPath($dom);
        $weeks=$xp->query('//*[@data-week-header]')->length;$orders=$xp->query('//*[@data-order-row]')->length;
        if($xp->query('//main//table/thead/tr/th')->length!==4+$weeks||$orders>50||$xp->query('//*[@data-percentage]')->length!==$orders*$weeks||!$xp->query('//*[@id="porcentagens-salvar"]')->length)throw new RuntimeException('Estrutura da planilha semanal divergente.');
        foreach(['setor_id','caderno_id','referencia','observacoes','motivo'] as $field)if($xp->query('//*[@name="'.$field.'"]')->length)throw new RuntimeException('Campo removido reapareceu.');
        if(str_contains($dom->textContent,'Sem atualização na semana'))throw new RuntimeException('Status semanal indevido.');
        if($page===1)file_put_contents(TMP.'porcentagens-lote-preview.html',str_replace(['href="/','src="/'],['href="http://localhost:8766/','src="http://localhost:8766/'],$body));
        $checked++;
    }
    if($before!==$hashes())throw new RuntimeException('Dados alterados na verificação de leitura.');
}finally{session_start();$_SESSION=[];session_destroy();}
echo json_encode(['status'=>'OK','paginas'=>$checked,'os_no_setor'=>(int)$sector['total'],'tabelas_preservadas'=>count($tables),'lancamentos_reais'=>0,'rota'=>'/lancar-porcentagem'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
