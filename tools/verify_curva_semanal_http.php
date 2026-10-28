<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';require dirname(__DIR__).'/config/bootstrap.php';
use App\Service\{Access,CurvaSemanal,PeriodoContexto};
use Cake\Http\Session;
$db=Access::table('Atividades')->getConnection();
if($db->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra')throw new RuntimeException('Banco inesperado.');
$hashes=static function()use($db):array {
    $out=[];foreach(['atividades','apontamentos','insumos','evolucao_registros','evolucao_versoes','calendarios_operacionais','auditorias'] as $table)$out[$table]=hash('sha256',json_encode($db->execute('SELECT row_to_json(t)::text AS r FROM '.$table.' t ORDER BY id')->fetchAll('assoc'),JSON_THROW_ON_ERROR));return $out;
};
$before=$hashes();$admin=Access::table('Usuarios')->find()->where(['perfil'=>'ADMIN_PCM','ativo'=>true])->firstOrFail();$season=Access::table('Entressafras')->find()->where(['status'=>'ativa'])->firstOrFail();PeriodoContexto::set((int)$season->id);
$area=(int)array_key_first((new CurvaSemanal())->areas((int)$season->id));if(!$area)throw new RuntimeException('Nenhum setor disponível.');
Session::create(Cake\Core\Configure::read('Session'));session_start();$_SESSION=['Auth'=>['id'=>$admin->id,'stamp'=>hash('sha256',$admin->senha_hash),'last'=>time()],'Periodo'=>['id'=>$season->id,'usuario_id'=>$admin->id]];$cookie=session_name().'='.session_id();session_write_close();$checked=0;
try {
    $electric=\App\Service\CurvaSimulacao::electricalArea((new CurvaSemanal())->areas((int)$season->id));
    if(!$electric)throw new RuntimeException('ELÉTRICA não encontrada.');
    $realElectric=null;
    foreach(['/controle-pcm?area_id='.$area,'/curva-s/programacao?area_id='.$area,'/controle-pcm?area_id='.$electric,'/controle-pcm?area_id='.$electric.'&simular=eletrica','/controle-pcm?area_id='.$electric] as $path) {
        $body=file_get_contents('http://127.0.0.1:8766'.$path,false,stream_context_create(['http'=>['header'=>'Cookie: '.$cookie."\r\n",'ignore_errors'=>true,'follow_location'=>0,'timeout'=>30]]));
        if(!str_contains($http_response_header[0]??'',' 200 '))throw new RuntimeException('Tela indisponível: '.$path);
        $dom=new DOMDocument();@$dom->loadHTML((string)$body);$xp=new DOMXPath($dom);
        if($checked===0&&$xp->query('//*[@data-series]')->length!==3)throw new RuntimeException('As três linhas não foram renderizadas.');
        if($checked===0){
            $bar=$xp->query('//*[contains(@class,"curve-day-bar")]')->item(0);
            if(!$bar||(int)$bar->getAttribute('data-total')!==(int)$bar->getAttribute('data-elapsed')+(int)$bar->getAttribute('data-remaining'))throw new RuntimeException('Indicadores de dias inconsistentes.');
            if($xp->query('//*[@data-week]')->length<20)throw new RuntimeException('Semanas do calendário longo ausentes.');
            if(getenv('CURVA_SEMANAL_PREVIEW'))file_put_contents(TMP.'curva-semanal-real-preview.html',(string)$body);
        }
        if($checked===1&&($xp->query('//input[starts-with(@name,"metas[")]')->length!==0||!str_contains($dom->textContent,'Programado automático')))throw new RuntimeException('Programação automática ausente.');
        if($checked>=2){
            $bar=$xp->query('//*[@data-total]')->item(0);
            $position=['planned'=>$xp->query('//*[@data-series="programado"]')->item(0)->getAttribute('d'),'days'=>[$bar->getAttribute('data-total'),$bar->getAttribute('data-elapsed'),$bar->getAttribute('data-remaining')],'weeks'=>$xp->query('//*[@data-week]')->length];
            if($checked===2)$realElectric=$position;
            elseif($position!==$realElectric)throw new RuntimeException('Simulação alterou calendário ou programado.');
            if($checked===3){
                if($xp->query('//*[@data-simulation="true"]')->length!==1||$xp->query('//*[@data-point-series="realizado"]')->length!==$position['weeks']||$xp->query('//*[@data-point-series="tendencia"]')->length!==$position['weeks']-3)throw new RuntimeException('Simulação incompleta.');
                if(getenv('CURVA_SEMANAL_PREVIEW'))file_put_contents(TMP.'curva-simulacao-real-preview.html',(string)$body);
            }elseif($xp->query('//*[@data-simulation="true"]')->length)throw new RuntimeException('Simulação permaneceu ativa.');
        }
        $checked++;
    }
    if($before!==$hashes())throw new RuntimeException('A consulta modificou dados.');
} finally {session_start();$_SESSION=[];session_destroy();PeriodoContexto::set(null);}
echo json_encode(['status'=>'OK','paginas'=>$checked,'tabelas_preservadas'=>7,'totvs'=>'não acessado'],JSON_UNESCAPED_UNICODE).PHP_EOL;
