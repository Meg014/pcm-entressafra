<?php
declare(strict_types=1);
if(getenv('PCM_RUN_OPERATIONAL_TEST')!=='1'){fwrite(STDERR,"Defina PCM_RUN_OPERATIONAL_TEST=1. Somente fixtures em schema temporário.\n");exit(2);}
require dirname(__DIR__).'/vendor/autoload.php';require dirname(__DIR__).'/config/bootstrap.php';require __DIR__.'/operational_test_support.php';require __DIR__.'/apache_pcm_config.php';
use App\Service\Access;
use Cake\Datasource\ConnectionManager;
$public=ConnectionManager::get('default');
if($public->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra')throw new RuntimeException('Banco local esperado não encontrado.');
$schema='pcm_op_test_'.bin2hex(random_bytes(8));$workers=[];$servers=[];$checks=[];
$check=static function(bool $ok,string $label)use(&$checks){if(!$ok)throw new RuntimeException($label);$checks[]=$label;};
$hash=static function()use($public){$result=[];foreach(['usuarios','usuarios_setores','atividades','apontamentos','insumos','auditorias','entressafras','evolucao_registros','evolucao_versoes'] as $table)$result[$table]=hash('sha256',json_encode($public->execute('SELECT row_to_json(t)::text AS r FROM public.'.$table.' t ORDER BY id')->fetchAll('assoc')));return $result;};
$before=$hash();$public->execute('CREATE SCHEMA "'.$schema.'"');
try{
    foreach($public->execute("SELECT tablename FROM pg_tables WHERE schemaname='public'")->fetchAll('assoc') as $row){
        $name=$row['tablename'];if(!preg_match('/^[a-z_0-9]+$/D',$name))throw new RuntimeException('Tabela inesperada.');
        $public->execute('CREATE TABLE "'.$schema.'"."'.$name.'" (LIKE public."'.$name.'" INCLUDING ALL)');
        $id=$public->execute("SELECT data_type,is_identity FROM information_schema.columns WHERE table_schema='public' AND table_name=? AND column_name='id'",[$name])->fetch('assoc');
        if($id&&$id['is_identity']==='NO'&&in_array($id['data_type'],['integer','bigint'],true)){
            $sequence=$schema.'.seq_'.$name;$public->execute('CREATE SEQUENCE '.$sequence);
            $public->execute('ALTER TABLE "'.$schema.'"."'.$name.'" ALTER COLUMN id SET DEFAULT nextval(\''.$sequence.'\')');
        }
    }
    // Only the disposable schema receives a missing column from the already-existing migration.
    $public->execute('ALTER TABLE "'.$schema.'".usuarios ADD COLUMN IF NOT EXISTS sessao_computador_hash varchar(64) NULL');
    mkdir(TMP.$schema);
    operationalTestConnect($schema);$db=Access::table('Usuarios')->getConnection();$now=gmdate('Y-m-d H:i:s');$users=[];
    foreach(['computador1','computador2'] as $login){$table=Access::table('Usuarios');$users[]=(int)$table->saveOrFail(Access::entity($table,['login'=>$login,'nome'=>$login,'senha_hash'=>password_hash('fixture-Test123',PASSWORD_DEFAULT),'perfil'=>'RESPONSAVEL_SETOR','computador_operacional'=>true,'ativo'=>true,'created'=>$now,'modified'=>$now]))->id;}
    $table=Access::table('Entressafras');$season=$table->saveOrFail($table->newEntity(['nome'=>'CONCORRÊNCIA ISOLADA','data_inicio'=>'2026-01-01','data_fim'=>'2027-12-31','status'=>'ativa']));$orders=[];
    foreach(['ELÉTRICA'=>'005316','MECÂNICA'=>'005900','CALDEIRARIA'=>'005525','MOENDA'=>'005901'] as $name=>$number){
        $table=Access::table('Setores');$sector=$table->saveOrFail($table->newEntity(['nome'=>$name,'area_totvs'=>'T'.count($orders),'filial_totvs'=>'01','ativo'=>true]));
        $table=Access::table('Atividades');$a=$table->saveOrFail(Access::entity($table,['entressafra_id'=>$season->id,'setor_executante_id'=>$sector->id,'area_executante_totvs'=>$sector->area_totvs,'identificacao_setor'=>'area_totvs','os_chave'=>\App\Integration\Totvs\Orders::key('01',$number),'os_numero'=>$number,'os_filial'=>'01','servico'=>'ENTRESSAFRA TESTE','bem'=>'TESTE','situacao_totvs'=>'L','disponivel_totvs'=>true,'snapshot_totvs'=>'{}','criado_por'=>$users[0],'created'=>$now,'modified'=>$now]));$orders[]=(int)$a->id;
    }
    $hours=static fn($order,$person,$key)=>['atividade_id'=>(string)$order,'envio_chave'=>$key,'apontamentos'=>[['funcionario_chave'=>'teste-'.$person,'inicio'=>'2026-10-27T07:00','fim'=>'2026-10-27T09:00','descricao'=>'Fixture concorrente']]];
    $supplies=static fn($order,$person,$key)=>['atividade_id'=>(string)$order,'envio_chave'=>$key,'insumos'=>[['funcionario_chave'=>'teste-'.$person,'data'=>'27/10/2026','material'=>'Material de teste','quantidade'=>'2']]];
    $start=static function($user,$module,$input,$hold)use($schema,&$workers){
        $payload=base64_encode(json_encode(compact('schema','user','module','input','hold')));
        $process=proc_open([PHP_BINARY,__DIR__.'/operational_concurrency_worker.php',$payload],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
        if(!is_resource($process))throw new RuntimeException('Processo não iniciou.');
        stream_set_timeout($pipes[1],15);$workers[]=[$process,$pipes];return [$process,$pipes];
    };
    $readUntil=static function($pipe,$marker){while(($line=fgets($pipe))!==false){if(str_starts_with(trim($line),$marker))return trim($line);}throw new RuntimeException('Marcador não recebido: '.$marker);};
    $finish=static function($worker){[$p,$pipes]=$worker;$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);$status=proc_close($p);if($status!==0)throw new RuntimeException('Worker: '.$error);return $out;};
    // A held transaction must not prevent independent B from completing its transaction.
    foreach([
        ['A: O.S. diferentes','apontamentos','apontamentos',$orders[0],$orders[2]],
        ['B: mesma O.S., funcionários distintos','apontamentos','apontamentos',$orders[0],$orders[0]],
        ['C: horas e insumos','apontamentos','insumos',$orders[0],$orders[0]],
        ['D: dois insumos no mesmo módulo','insumos','insumos',$orders[2],$orders[2]],
    ] as $i=>[$label,$moduleA,$moduleB,$orderA,$orderB]){
        $a=$start($users[0],$moduleA,($moduleA==='insumos'?$supplies:$hours)($orderA,'a'.$i,bin2hex(random_bytes(16))),true);$readUntil($a[1][1],'READY');
        $b=$start($users[1],$moduleB,($moduleB==='insumos'?$supplies:$hours)($orderB,'b'.$i,bin2hex(random_bytes(16))),false);$outB=$finish($b);
        fwrite($a[1][0],"COMMIT\n");fflush($a[1][0]);$outA=$finish($a);$workers=[];
        $check(str_contains($outA,'SAVED')&&str_contains($outB,'SAVED'),$label.' — B salva antes do commit de A');
    }
    // A true cross-session employee overlap waits for A and is then rejected.
    $a=$start($users[0],'apontamentos',$hours($orders[0],'conflito',bin2hex(random_bytes(16))),true);$readUntil($a[1][1],'READY');
    $b=$start($users[1],'apontamentos',$hours($orders[2],'conflito',bin2hex(random_bytes(16))),false);$readUntil($b[1][1],'STARTED');usleep(300000);
    $check(proc_get_status($b[0])['running'],'Conflito verdadeiro aguarda transação concorrente');
    fwrite($a[1][0],"COMMIT\n");fflush($a[1][0]);$finish($a);$out=$finish($b);$workers=[];$check(str_contains($out,'CONFLICT'),'Mesmo funcionário e horário rejeitado entre O.S. e sessões');
    $a=$start($users[0],'apontamentos',$hours($orders[0],'zzz-atomic',bin2hex(random_bytes(16))),true);$readUntil($a[1][1],'READY');
    $batch=$hours($orders[2],'aaa-atomic',bin2hex(random_bytes(16)));$batch['apontamentos'][]=$hours($orders[2],'zzz-atomic','')['apontamentos'][0];
    $b=$start($users[1],'apontamentos',$batch,false);$readUntil($b[1][1],'STARTED');usleep(200000);fwrite($a[1][0],"COMMIT\n");fflush($a[1][0]);$finish($a);$out=$finish($b);$workers=[];
    $check(str_contains($out,'CONFLICT')&&Access::table('Apontamentos')->find()->where(['funcionario_chave'=>'teste-aaa-atomic'])->count()===0,'Conflito na segunda linha desfaz o lote inteiro');
    // Repeated submissions return the original IDs, with no duplicate supply or audit.
    $payload=$supplies($orders[0],'reenvio',bin2hex(random_bytes(16)));
    $a=$start($users[0],'insumos',$payload,true);$readUntil($a[1][1],'READY');$b=$start($users[0],'insumos',$payload,false);$readUntil($b[1][1],'STARTED');usleep(200000);fwrite($a[1][0],"COMMIT\n");fflush($a[1][0]);$outA=$finish($a);$outB=$finish($b);$workers=[];
    $check(trim($outA)===trim($outB),'Reenvio simultâneo devolve os mesmos IDs sem duplicar');
    $check(Access::table('Insumos')->find()->where(['funcionario_chave'=>'teste-reenvio'])->count()===1,'Um único insumo após dois envios');
    // One multithreaded Apache process, two independent HTTP cookie jars.
    putenv('PCM_OPERATIONAL_SCHEMA='.$schema);
    $sock=@stream_socket_client('tcp://127.0.0.1:18771',$errno,$errstr,.1);if($sock){fclose($sock);throw new RuntimeException('Porta de teste já ocupada.');}
    $web=TMP.$schema.'-web';mkdir($web);file_put_contents($web.'/index.php','<?php require '.var_export(__DIR__.'/operational_http_server.php',true).';');
    $config=TMP.$schema.'-apache.conf';file_put_contents($config,apachePcmConfig($web,TMP.$schema.'-apache',18771));
    $log=TMP.$schema.'-http.log';$p=proc_open(['C:/xampp/apache/bin/httpd.exe','-X','-f',$config],[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,dirname(__DIR__));$servers[]=[$p,$pipes,$log];
    usleep(1000000);$jars=[[],[]];$csrf=['',''];
    $request=static function($who,$path,$post=null)use(&$jars,&$csrf){
        $headers=[];if($jars[$who])$headers[]='Cookie: '.http_build_query($jars[$who],'','; ');
        $options=['ignore_errors'=>true,'follow_location'=>0,'timeout'=>20,'header'=>$headers];
        if($post!==null){$options['method']='POST';$options['header'][]='Content-Type: application/x-www-form-urlencoded';$options['content']=http_build_query($post+['_csrfToken'=>$csrf[$who]]);}
        $body=file_get_contents('http://127.0.0.1:18771'.$path,false,stream_context_create(['http'=>$options]));
        foreach($http_response_header as $header)if(preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i',$header,$m))$jars[$who][$m[1]]=rawurldecode($m[2]);
        if($body!==''){$doc=new DOMDocument();@$doc->loadHTML((string)$body);$field=(new DOMXPath($doc))->query('//input[@name="_csrfToken"]')->item(0);if($field)$csrf[$who]=$field->getAttribute('value');}
        preg_match('/\s(\d{3})\s/',$http_response_header[0],$m);if($post!==null&&$path==='/login'&&(int)$m[1]!==302)file_put_contents(TMP.'operational-test-login-error.html',$body);return [(int)$m[1],$body];
    };
    foreach([0,1] as $who){[$status]=$request($who,'/login');$check($status===200,'Login HTTP disponível '.($who+1));[$status]=$request($who,'/login',['login'=>'computador'.($who+1),'senha'=>'fixture-Test123']);$check(in_array($status,[302,303],true),'Sessão HTTP autenticada '.($who+1).' (HTTP '.$status.')');}
    $check(($jars[0]['pcm_operational_test']??null)!==($jars[1]['pcm_operational_test']??null),'Cookies de sessão independentes');
    foreach([[0,'005316'],[0,'005900'],[1,'005525'],[1,'005901']] as [$who,$number]){[$status,$body]=$request($who,'/operacao/ordens?q='.$number);$data=json_decode($body,true);if($status!==200||count($data['results']??[])!==1)file_put_contents(TMP.'operational-test-order-error.html',$body);$check($status===200&&count($data['results']??[])===1&&$data['results'][0]['numero']===$number,'Pesquisa HTTP sem vínculo por setor: computador'.($who+1).' / '.$number.' (HTTP '.$status.')');}
    foreach([0,1] as $who){[$status]=$request($who,'/apontamentos/funcionarios?q=teste');$check($status===200,'Autocomplete funcionários '.($who+1));[$status]=$request($who,'/usuarios');$check($status===403,'Administração bloqueada '.($who+1));}
    $parallel=static function(array $posts)use(&$jars,&$csrf){
        $multi=curl_multi_init();$handles=[];
        foreach($posts as $who=>[$path,$data]){$ch=curl_init('http://127.0.0.1:18771'.$path);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>20,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($data+['_csrfToken'=>$csrf[$who]]),CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded','Cookie: '.http_build_query($jars[$who],'','; ')]]);curl_multi_add_handle($multi,$ch);$handles[]=$ch;}
        do{$status=curl_multi_exec($multi,$running);if($running)curl_multi_select($multi,.2);}while($running&&$status===CURLM_OK);
        $codes=[];foreach($handles as $ch){$codes[]=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_multi_remove_handle($multi,$ch);curl_close($ch);}curl_multi_close($multi);return $codes;
    };
    foreach([
        [['/apontamentos/add',$hours($orders[0],'http-a',bin2hex(random_bytes(16)))],['/apontamentos/add',$hours($orders[2],'http-b',bin2hex(random_bytes(16)))]],
        [['/apontamentos/add',$hours($orders[0],'http-c',bin2hex(random_bytes(16)))],['/apontamentos/add',$hours($orders[0],'http-d',bin2hex(random_bytes(16)))]],
        [['/apontamentos/add',$hours($orders[0],'http-e',bin2hex(random_bytes(16)))],['/insumos/add',$supplies($orders[0],'http-f',bin2hex(random_bytes(16)))]],
        [['/insumos/add',$supplies($orders[0],'http-g',bin2hex(random_bytes(16)))],['/insumos/add',$supplies($orders[0],'http-h',bin2hex(random_bytes(16)))]],
    ] as $i=>$posts){$codes=$parallel($posts);$check(count(array_filter($codes,static fn($code)=>in_array($code,[302,303],true)))===2,'POSTs HTTP simultâneos cenário '.chr(65+$i));}
    $codes=$parallel([['/apontamentos/add',$hours($orders[0],'http-conflito',bin2hex(random_bytes(16)))],['/apontamentos/add',$hours($orders[2],'http-conflito',bin2hex(random_bytes(16)))]]);sort($codes);
    $check($codes===[200,302]&&Access::table('Apontamentos')->find()->where(['funcionario_chave'=>'teste-http-conflito'])->count()===1,'Conflito HTTP simultâneo: um salvo e outro com validação, sem erro 500');
    [$status]=$request(0,'/insumos/add',['_csrfToken'=>'invalido']);$check($status===403,'POST sem CSRF válido rejeitado');
    // CSRF and ownership remain enforced after widening access to order sectors.
    [$status,$body]=$request(0,'/insumos');$check($status===200&&!str_contains($body,'teste-http-f')&&str_contains($body,'teste-http-g'),'Computador1 consulta somente seus insumos');
    $other=Access::table('Apontamentos')->find()->where(['criado_por'=>$users[1]])->firstOrFail();[$status]=$request(0,'/apontamentos/view/'.$other->id);$check($status===403,'Detalhe de apontamento de outro usuário bloqueado');
    [$status]=$request(0,'/logout',[]);$check($status===302,'Logout do computador1');[$status]=$request(1,'/apontamentos/add');$check($status===200,'Computador2 permanece autenticado após logout do computador1');
    $check($before===$hash(),'Nove tabelas reais preservadas integralmente');
    echo json_encode(['status'=>'OK','verificacoes'=>$checks,'totvs'=>'substituto de teste; nenhuma conexão real'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
}finally{
    foreach($workers as [$process,$pipes]){foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);if(is_resource($process)){proc_terminate($process);proc_close($process);}}
    foreach($servers as [$process,$pipes,$log]){if(is_resource($process)){proc_terminate($process);proc_close($process);}foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);}
    if(preg_match('/^pcm_op_test_[a-f0-9]{16}$/D',$schema))$public->execute('DROP SCHEMA "'.$schema.'" CASCADE');
    foreach(glob(TMP.$schema.'/sess_*')?:[] as $file)unlink($file);if(is_dir(TMP.$schema))rmdir(TMP.$schema);
    if(isset($web)&&is_dir($web)){unlink($web.'/index.php');rmdir($web);}
}
