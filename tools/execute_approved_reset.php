<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';require dirname(__DIR__).'/config/bootstrap.php';
use Cake\Datasource\ConnectionManager;
if(($argv[1]??'')!=='--authorized-2583')throw new RuntimeException('Execução exige autorização explícita do plano de 2.583 registros.');
$folder=dirname(__DIR__).'/backups/reinicializacao_20261022_204134';
$manifest=json_decode(file_get_contents($folder.'/manifesto.json'),true,512,JSON_THROW_ON_ERROR);
if($manifest['status']!=='BACKUP_RESTAURADO_E_VALIDADO_SEM_LIMPEZA'||hash_file('sha256',$folder.'/'.$manifest['arquivo'])!=='626e6e709767b398e00754195812e9611f47dfd18af4be23c841add228e2f91e')throw new RuntimeException('Backup aprovado ausente ou divergente.');
$db=ConnectionManager::get('default');$config=$db->config();
if(!in_array($config['host'],['127.0.0.1','localhost','::1'],true)||$db->execute('SELECT current_database() AS banco')->fetch('assoc')['banco']!=='pcm_controle_entressafra')throw new RuntimeException('Somente o banco local autorizado.');
$tables=array_keys($manifest['tabelas']);
$rowsHash=function(string $table,string $where='')use($db): array{
    $rows=array_column($db->execute('SELECT row_to_json(t)::text AS row FROM public."'.$table.'" t'.$where)->fetchAll('assoc'),'row');sort($rows,SORT_STRING);
    return ['registros'=>count($rows),'sha256'=>hash('sha256',implode("\n",$rows))];
};
$schema=function()use($db): array{
    return [
        $db->execute("SELECT table_name,column_name,ordinal_position,data_type,column_default,is_nullable FROM information_schema.columns WHERE table_schema='public' ORDER BY table_name,ordinal_position")->fetchAll('assoc'),
        $db->execute("SELECT tablename,indexname,indexdef FROM pg_indexes WHERE schemaname='public' ORDER BY tablename,indexname")->fetchAll('assoc'),
        $db->execute("SELECT conrelid::regclass::text AS tabela,conname,pg_get_constraintdef(oid) AS definicao,convalidated FROM pg_constraint WHERE connamespace='public'::regnamespace ORDER BY 1,2")->fetchAll('assoc'),
        $db->execute("SELECT * FROM pg_sequences WHERE schemaname='public' ORDER BY sequencename")->fetchAll('assoc'),
        $db->execute("SELECT tgrelid::regclass::text AS tabela,tgname,tgenabled,pg_get_triggerdef(oid) AS definicao FROM pg_trigger WHERE NOT tgisinternal ORDER BY 1,2")->fetchAll('assoc'),
        $db->execute("SELECT proname,pg_get_functiondef(oid) AS definicao FROM pg_proc WHERE pronamespace='public'::regnamespace AND proname LIKE 'pcm_%' ORDER BY proname")->fetchAll('assoc'),
    ];
};
$report=['autorizacao'=>'Usuário autorizou explicitamente 2.583 registros em 2026-10-23','backup'=>$manifest['arquivo'],'inicio'=>(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format(DATE_ATOM)];
$db->transactional(function()use($db,$tables,$manifest,$rowsHash,$schema,&$report){
    $db->execute("SET LOCAL lock_timeout = '10s'");
    $db->execute('LOCK TABLE '.implode(',',array_map(fn($t)=>'public."'.$t.'"',$tables)).' IN ACCESS EXCLUSIVE MODE');
    foreach($tables as $table)if($rowsHash($table)!==$manifest['tabelas'][$table])throw new RuntimeException('Base divergente do plano em '.$table.'; nenhuma exclusão autorizada fora do snapshot.');
    $admin=$db->execute('SELECT * FROM usuarios')->fetchAll('assoc');
    if(count($admin)!==1||(int)$admin[0]['id']!==3||$admin[0]['perfil']!=='ADMIN_PCM'||$admin[0]['login']!=='pcm@biolasa.com.br'||!$admin[0]['ativo'])throw new RuntimeException('Administrador divergente.');
    $technical=$schema();$audit=$rowsHash('auditorias',' WHERE apontamento_id IS NULL');
    if($audit['registros']!==1551)throw new RuntimeException('Auditorias preservadas divergentes.');
    $removed=[];$removed['auditorias']=$db->execute('DELETE FROM public.auditorias WHERE apontamento_id IS NOT NULL')->rowCount();
    $immutable=['evolucao_registros','evolucao_metas','evolucao_pesos','evolucao_lotes','evolucao_versoes'];
    foreach($immutable as $table)$removed[$table]=$rowsHash($table)['registros'];
    $db->execute('TRUNCATE TABLE '.implode(',',array_map(fn($t)=>'public."'.$t.'"',$immutable)).' CONTINUE IDENTITY RESTRICT');
    $order=['vinculos_itens','vinculos_lotes','apontamentos','atividades','calendarios_operacionais','evolucao_semanas','cadernos_areas','cadernos_setores','cadernos','usuarios_setores','setores','areas_fabrica','entressafras'];
    foreach($order as $table){$removed[$table]=$db->execute('DELETE FROM public."'.$table.'"')->rowCount();if($removed[$table]!==$manifest['tabelas'][$table]['registros'])throw new RuntimeException('Quantidade divergente em '.$table);}
    if(array_sum($removed)!==2583||$removed['auditorias']!==3)throw new RuntimeException('Total fora do autorizado.');
    if($schema()!==$technical||$rowsHash('usuarios')!==$manifest['tabelas']['usuarios']||$rowsHash('cake_migrations')!==$manifest['tabelas']['cake_migrations']||$rowsHash('auditorias')!==$audit)throw new RuntimeException('Preservação divergente; rollback obrigatório.');
    $final=[];
    foreach($tables as $table){$final[$table]=$rowsHash($table)['registros'];$expected=match($table){'usuarios'=>1,'cake_migrations'=>17,'auditorias'=>1551,default=>0};if($final[$table]!==$expected)throw new RuntimeException('Integridade final divergente em '.$table);}
    $report+=['excluidos'=>$removed,'total_excluido'=>2583,'contagem_final'=>$final,'admin_integralmente_preservado'=>true,'estrutura_indices_restricoes_sequencias_preservados'=>true,'auditorias_preservadas'=>1551,'migrations_preservadas'=>17];
});
$report['status']='COMMIT_CONCLUIDO';$report['fim']=(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format(DATE_ATOM);
file_put_contents($folder.'/limpeza_autorizada_20261023.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),PHP_EOL;
