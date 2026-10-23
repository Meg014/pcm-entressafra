<?php
declare(strict_types=1);
// Preparation only: no DELETE, TRUNCATE, DROP or application-data writes.
require dirname(__DIR__).'/vendor/autoload.php';require dirname(__DIR__).'/config/bootstrap.php';
use Cake\Datasource\ConnectionManager;
$db=ConnectionManager::get('default');$config=$db->config();
$identity=$db->execute('SELECT current_database() AS banco,current_user AS conta,inet_server_addr() AS host,inet_server_port() AS porta,version() AS versao')->fetch('assoc');
if($identity['banco']!=='pcm_controle_entressafra')throw new RuntimeException('Banco fora do escopo autorizado.');
$bin='C:/Program Files/PostgreSQL/18/bin/';
foreach(['pg_dump','pg_restore','initdb','pg_ctl','psql'] as $exe)if(!is_file($bin.$exe.'.exe'))throw new RuntimeException('Ferramenta PostgreSQL ausente.');
$tag=(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('Ymd_His');
$folder=dirname(__DIR__).'/backups/reinicializacao_'.$tag;if(!mkdir($folder,0700,true))throw new RuntimeException('Pasta de backup indisponível.');
$archive=$folder.'/pcm_controle_entressafra_'.$tag.'.dump';
$environment=getenv();$environment['PGPASSWORD']=(string)$config['password'];$environment['PGCONNECT_TIMEOUT']='10';
$run=function(array $arguments,string $label,array $env)use($folder): string{
    // File handles prevent the detached PostgreSQL server from keeping a pipe
    // open after pg_ctl exits on Windows.
    $stdout=$folder.'/'.$label.'.stdout.log';
    $process=proc_open($arguments,[0=>['pipe','r'],1=>['file',$stdout,'a'],2=>['file',$folder.'/'.$label.'.log','a']],$pipes,null,$env,['bypass_shell'=>true,'create_no_window'=>true]);
    if(!is_resource($process))throw new RuntimeException('Falha ao iniciar '.$label);
    fclose($pipes[0]);$status=proc_close($process);$out=file_get_contents($stdout);
    if($status!==0)throw new RuntimeException('Falha em '.$label.'. Backup não aprovado; limpeza proibida. Consulte o log local.');
    return $out;
};
$tables=array_column($db->execute("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename")->fetchAll('assoc'),'tablename');
$fingerprint=function($connection)use($tables): array{
    $result=[];
    foreach($tables as $table){$quoted='"'.str_replace('"','""',$table).'"';$rows=$connection->execute('SELECT row_to_json(t)::text AS row FROM public.'.$quoted.' t')->fetchAll('assoc');$values=array_column($rows,'row');sort($values,SORT_STRING);$result[$table]=['registros'=>count($values),'sha256'=>hash('sha256',implode("\n",$values))];}
    return $result;
};
$manifest=['status'=>'EM_PREPARACAO','criado_em'=>$tag,'origem'=>$identity,'arquivo'=>basename($archive)];$started=false;$transaction=false;
try{
    $db->begin();$transaction=true;$db->execute('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
    $snapshot=$db->execute('SELECT pg_export_snapshot() AS snapshot')->fetch('assoc')['snapshot'];
    $manifest['tabelas']=$fingerprint($db);
    $manifest['administradores']=$db->execute("SELECT id,nome,login,perfil,ativo FROM usuarios WHERE perfil='ADMIN_PCM' ORDER BY id")->fetchAll('assoc');
    $manifest['auditorias_remocao_necessaria']=(int)$db->execute('SELECT COUNT(*) AS n FROM auditorias WHERE apontamento_id IS NOT NULL')->fetch('assoc')['n'];
    $manifest['restricoes']=$db->execute("SELECT conrelid::regclass::text AS tabela,conname AS nome,pg_get_constraintdef(oid) AS definicao FROM pg_constraint WHERE connamespace='public'::regnamespace ORDER BY 1,2")->fetchAll('assoc');
    usort($manifest['restricoes'],fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));
    echo 'Origem confirmada; snapshot somente leitura. Gerando backup completo...',PHP_EOL;
    $connectionArgs=['--host',(string)$config['host'],'--port',(string)$config['port'],'--username',(string)$config['username']];
    $run(array_merge([$bin.'pg_dump.exe'],$connectionArgs,['--dbname','pcm_controle_entressafra','--format=custom','--snapshot',$snapshot,'--file',$archive]),'pg_dump',$environment);
    $db->rollback();$transaction=false;
    if(!is_file($archive)||filesize($archive)===0)throw new RuntimeException('Arquivo de backup vazio.');
    file_put_contents($folder.'/conteudo_backup.txt',$run([$bin.'pg_restore.exe','--list',$archive],'listar_backup',$environment));
    $manifest['backup_bytes']=filesize($archive);$manifest['backup_sha256']=hash_file('sha256',$archive);
    echo 'Backup gerado. Validando restauração em instância PostgreSQL isolada...',PHP_EOL;
    $isolated=$environment;unset($isolated['PGPASSWORD']);
    $cluster=$folder.'/restore_cluster';$port=15486;
    $run([$bin.'initdb.exe','--pgdata',$cluster,'--username','restore_check','--auth-local=trust','--auth-host=trust','--encoding=UTF8','--locale=C'],'initdb_isolado',$isolated);
    $run([$bin.'pg_ctl.exe','--pgdata',$cluster,'--log',$folder.'/restore_server.log','--options','-h 127.0.0.1 -p '.$port,'--wait','start'],'iniciar_isolado',$isolated);$started=true;
    $run([$bin.'psql.exe','--host','127.0.0.1','--port',(string)$port,'--username','restore_check','--dbname','postgres','--set','ON_ERROR_STOP=1','--command','CREATE DATABASE pcm_restore_verificacao'],'criar_base_isolada',$isolated);
    $run([$bin.'pg_restore.exe','--host','127.0.0.1','--port',(string)$port,'--username','restore_check','--dbname','pcm_restore_verificacao','--no-owner','--no-privileges','--exit-on-error',$archive],'restaurar_isolado',$isolated);
    $check=new \Cake\Database\Connection(['driver'=>\Cake\Database\Driver\Postgres::class,'host'=>'127.0.0.1','port'=>$port,'username'=>'restore_check','password'=>'','database'=>'pcm_restore_verificacao','encoding'=>'utf8','cacheMetadata'=>false]);
    if($fingerprint($check)!==$manifest['tabelas'])throw new RuntimeException('Restauração diverge das contagens/hashes da origem.');
    $constraints=$check->execute("SELECT conrelid::regclass::text AS tabela,conname AS nome,pg_get_constraintdef(oid) AS definicao FROM pg_constraint WHERE connamespace='public'::regnamespace ORDER BY 1,2")->fetchAll('assoc');
    usort($constraints,fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));
    // PostgreSQL can distribute an array cast onto its literal elements when
    // replaying a CHECK. Accept only that precise, equivalent rewrite.
    foreach($constraints as &$restored){
        foreach($manifest['restricoes'] as $original){
            if($restored['tabela']!==$original['tabela']||$restored['nome']!==$original['nome'])continue;
            if(preg_match('/^CHECK \(\(\((\w+)\)::text = ANY \(\(ARRAY\[(.*)\]\)::text\[\]\)\)\)$/D',$original['definicao'],$match)){
                $values=explode(', ',$match[2]);$valid=true;
                foreach($values as $value)if(!preg_match("/^'[A-Za-z_]+'::character varying$/D",$value))$valid=false;
                $equivalent='CHECK ((('.$match[1].')::text = ANY (ARRAY['.implode(', ',array_map(fn($v)=>'('.$v.')::text',$values)).'])))';
                if($valid&&$restored['definicao']===$equivalent)$restored['definicao']=$original['definicao'];
            }
        }
    }unset($restored);
    usort($constraints,fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));
    if($constraints!==$manifest['restricoes']){file_put_contents($folder.'/restricoes_restauradas.json',json_encode($constraints,JSON_PRETTY_PRINT));throw new RuntimeException('Restrições restauradas divergentes.');}
    $adminHashes=$db->execute("SELECT id,senha_hash FROM usuarios WHERE perfil='ADMIN_PCM' ORDER BY id")->fetchAll('assoc');
    if($adminHashes!==$check->execute("SELECT id,senha_hash FROM usuarios WHERE perfil='ADMIN_PCM' ORDER BY id")->fetchAll('assoc'))throw new RuntimeException('Credenciais administrativas divergentes.');
    $check->getDriver()->disconnect();
    if($fingerprint($db)!==$manifest['tabelas'])throw new RuntimeException('Origem mudou durante a validação; atualizar inventário antes da limpeza.');
    $manifest['status']='BACKUP_RESTAURADO_E_VALIDADO_SEM_LIMPEZA';
    $manifest['validacao']=['tabelas'=>count($tables),'dados_hashes'=>true,'restricoes'=>count($constraints),'credenciais_admin_preservadas'=>true,'instancia'=>'127.0.0.1:'.$port,'origem_inalterada'=>true];
}catch(Throwable $error){$manifest['status']='FALHA_LIMPEZA_PROIBIDA';$manifest['erro']=$error->getMessage();throw $error;}
finally{
    if($transaction)$db->rollback();
    if($started)$run([$bin.'pg_ctl.exe','--pgdata',$folder.'/restore_cluster','--mode','fast','--wait','stop'],'parar_isolado',$environment);
    file_put_contents($folder.'/manifesto.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}
echo json_encode(['pasta'=>$folder,'status'=>$manifest['status'],'tabelas'=>$manifest['tabelas'],'admin'=>$manifest['administradores'],'auditorias_remocao_necessaria'=>$manifest['auditorias_remocao_necessaria'],'backup_bytes'=>$manifest['backup_bytes'],'backup_sha256'=>$manifest['backup_sha256']],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
