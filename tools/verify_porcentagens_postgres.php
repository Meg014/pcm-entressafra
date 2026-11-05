<?php
declare(strict_types=1);
// All writes are confined to connection-local temporary tables, including sequences.
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/config/bootstrap.php';
use App\Service\{Access,EvolucaoSemanal,PeriodoContexto};
$db=Access::table('Atividades')->getConnection();
if(!($db->getDriver() instanceof \Cake\Database\Driver\Postgres))throw new RuntimeException('PostgreSQL required');
$check=static function(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);};
$tables=['usuarios','entressafras','setores','areas_fabrica','cadernos','atividades','usuarios_setores','cadernos_setores','evolucao_registros','evolucao_semanas','evolucao_lotes','auditorias'];
$hashes=static function()use($db,$tables):array{
    $out=[];foreach($tables as $table)$out[$table]=hash('sha256',json_encode($db->execute('SELECT row_to_json(t)::text AS row FROM public.'.$table.' t ORDER BY id')->fetchAll('assoc'),JSON_THROW_ON_ERROR));return $out;
};
$before=$hashes();
try{
    foreach($tables as $table){
        $db->execute('CREATE TEMP TABLE '.$table.' (LIKE public.'.$table.' INCLUDING DEFAULTS)');
        $db->execute('CREATE TEMP SEQUENCE probe_'.$table.' START 100000000');
        $db->execute("ALTER TABLE pg_temp.$table ALTER COLUMN id SET DEFAULT nextval('pg_temp.probe_$table')");
    }
    $db->execute('ALTER TABLE pg_temp.evolucao_semanas ADD UNIQUE(entressafra_id,referencia)');
    $db->execute('INSERT INTO pg_temp.usuarios SELECT * FROM public.usuarios ORDER BY id LIMIT 2');
    if((int)$db->execute('SELECT COUNT(*) AS n FROM pg_temp.usuarios')->fetch('assoc')['n']===1){
        $copy=$db->execute('SELECT * FROM pg_temp.usuarios LIMIT 1')->fetch('assoc');unset($copy['id']);$copy['login'].='_isolated_probe';$db->insert('usuarios',$copy,['ativo'=>'boolean','computador_operacional'=>'boolean']);
    }
    $db->execute("UPDATE pg_temp.usuarios SET perfil='ADMIN_PCM',ativo=true");
    $db->execute("INSERT INTO pg_temp.entressafras SELECT * FROM public.entressafras WHERE status='ativa' ORDER BY id LIMIT 1");
    $db->execute('INSERT INTO pg_temp.setores SELECT * FROM public.setores ORDER BY id LIMIT 1');
    $db->execute('INSERT INTO pg_temp.areas_fabrica SELECT * FROM public.areas_fabrica WHERE entressafra_id=(SELECT id FROM pg_temp.entressafras) ORDER BY id LIMIT 1');
    $db->execute('INSERT INTO pg_temp.atividades SELECT * FROM public.atividades ORDER BY id LIMIT 2');
    $db->execute('UPDATE pg_temp.atividades SET entressafra_id=(SELECT id FROM pg_temp.entressafras),area_curva_id=(SELECT id FROM pg_temp.areas_fabrica),setor_executante_id=(SELECT id FROM pg_temp.setores),caderno_id=NULL');
    $season=(int)$db->execute('SELECT id FROM pg_temp.entressafras')->fetch('assoc')['id'];
    $area=(int)$db->execute('SELECT id FROM pg_temp.areas_fabrica')->fetch('assoc')['id'];
    $users=array_column($db->execute('SELECT id FROM pg_temp.usuarios ORDER BY id')->fetchAll('assoc'),'id');
    $orders=array_column($db->execute('SELECT id FROM pg_temp.atividades ORDER BY id')->fetchAll('assoc'),'id');
    $check(count($users)===2&&count($orders)===2,'Two users/orders required');
    PeriodoContexto::set($season);$service=new EvolucaoSemanal();$weeks=$service->weeks($season);$reference=$weeks[0]['fim'];
    $cell=static function(int $i,float $value)use($service,$users,$season,$area,$orders,$reference):array{
        $rows=$service->page(Access::actor((int)$users[0]),$season,$area,'',1)['rows'];$rows=array_column($rows,null,'id');$c=$rows[$orders[$i]]['cells'][$reference];
        return ['atividade_id'=>(int)$orders[$i],'referencia'=>$reference,'percentual'=>$value,'base'=>$c['valor'],'base_registro'=>$c['registro_id'],'revisao'=>$c['revisao']];
    };
    $key=bin2hex(random_bytes(16));$rows=[$cell(0,0),$cell(1,50)];
    $saved=$service->save((int)$users[0],$season,$area,$rows,$key);
    $check($saved['quantidade']===2&&!$db->inTransaction(),'Save must return after COMMIT');
    $check($saved===$service->save((int)$users[0],$season,$area,$rows,$key),'Idempotent retry');
    $check($cell(0,0)['base']===0.&&$cell(1,0)['base']===50.,'Reload persisted zero and fifty');
    $stale=$cell(1,60);$service->save((int)$users[1],$season,$area,[$cell(1,70)],bin2hex(random_bytes(16)));
    try{$service->save((int)$users[0],$season,$area,[$stale],bin2hex(random_bytes(16)));throw new RuntimeException('Stale write accepted');}
    catch(\DomainException $e){$check(str_contains($e->getMessage(),'outra pessoa'),'Conflict message');}
    $db->execute("CREATE FUNCTION pg_temp.fail_percentage() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN RAISE EXCEPTION ''Simulated percentage failure''; END'");
    $db->execute('CREATE TRIGGER fail_insert BEFORE INSERT ON pg_temp.evolucao_registros FOR EACH ROW WHEN (NEW.percentual=77) EXECUTE FUNCTION pg_temp.fail_percentage()');
    $counts=static fn()=>array_map(fn($t)=>(int)$db->execute('SELECT COUNT(*) AS n FROM pg_temp.'.$t)->fetch('assoc')['n'],['evolucao_registros','auditorias','evolucao_lotes']);
    $count=$counts();
    try{$service->save((int)$users[0],$season,$area,[$cell(0,25),$cell(1,77)],bin2hex(random_bytes(16)));throw new RuntimeException('Database error accepted');}
    catch(\PDOException $e){$check(str_contains($e->getMessage(),'Simulated percentage failure'),'Expected insert failure');}
    $check($count===$counts()&&$cell(0,0)['base']===0.,'Atomic rollback includes records, audit and batch');
    $db->execute('CREATE CONSTRAINT TRIGGER fail_commit AFTER INSERT ON pg_temp.evolucao_registros DEFERRABLE INITIALLY DEFERRED FOR EACH ROW WHEN (NEW.percentual=88) EXECUTE FUNCTION pg_temp.fail_percentage()');
    try{$service->save((int)$users[0],$season,$area,[$cell(1,88)],bin2hex(random_bytes(16)));throw new RuntimeException('COMMIT error accepted');}
    catch(\PDOException $e){$check(str_contains($e->getMessage(),'Simulated percentage failure'),'Expected COMMIT failure');}
    $check($count===$counts()&&$cell(1,0)['base']===70.,'Failed COMMIT cannot report success or persist changes');
    echo "OK: PostgreSQL COMMIT, reload, zero, revision conflict, retry, insert failure and deferred COMMIT failure.\n";
}finally{
    if($db->inTransaction())$db->rollback();
    PeriodoContexto::set(null);
    $check($before===$hashes(),'Public data changed');
    echo "Public tables preserved; temporary tables disappear on disconnect.\n";
}
