<?php
declare(strict_types=1);
if (getenv('PCM_RUN_CONCURRENCY_TEST') !== '1') { fwrite(STDERR, "Defina PCM_RUN_CONCURRENCY_TEST=1 para validar no banco local exclusivo.\n"); exit(2); }
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/bootstrap.php';
use App\Service\Access;
use App\Service\Activities;
use App\Test\Support\FakeTotvs;
$db = Access::table('Usuarios')->getConnection();
$identity = $db->execute('SELECT current_database() AS db, current_user AS usr')->fetch('assoc');
if ($identity['db'] !== 'pcm_controle_entressafra' || $identity['usr'] !== 'pcm_entressafra_app') { throw new RuntimeException('Conexão exclusiva não confirmada.'); }
$tag = '__concurrency_' . bin2hex(random_bytes(8));
$user = $season = $sector = $notebook = $activity = null;
$workers = [];
try {
    $now = gmdate('Y-m-d H:i:s'); $table = Access::table('Usuarios');
    $user = $table->saveOrFail(Access::entity($table, ['nome'=>$tag,'login'=>$tag,'senha_hash'=>password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT),'perfil'=>'ADMIN_PCM','ativo'=>true,'created'=>$now,'modified'=>$now]));
    $table=Access::table('Entressafras'); $season=$table->saveOrFail($table->newEntity(['nome'=>$tag,'data_inicio'=>'2026-10-01','data_fim'=>'2027-04-30','status'=>'planejamento']));
    $table=Access::table('Setores'); $sector=$table->saveOrFail($table->newEntity(['nome'=>$tag,'ativo'=>true,'area_totvs'=>substr($tag,-6),'filial_totvs'=>'01']));
    $table=Access::table('Cadernos'); $notebook=$table->saveOrFail($table->newEntity(['nome'=>$tag,'setor_id'=>$sector->id,'entressafra_id'=>$season->id,'ativo'=>true]));
    $activity=(new Activities(new FakeTotvs((string)$sector->area_totvs)))->link((int)$user->id,(int)$notebook->id,$tag);
    $start = static function(bool $hold) use ($user,$activity,$tag): array {
        $payload=base64_encode(json_encode(['user'=>$user->id,'activity'=>$activity,'employee'=>$tag,'hold'=>$hold],JSON_THROW_ON_ERROR));
        $process=proc_open([PHP_BINARY,__DIR__.'/concurrency_worker.php',$payload],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
        if (!is_resource($process)) { throw new RuntimeException('Falha ao abrir processo independente.'); }
        stream_set_timeout($pipes[1],15);
        return [$process,$pipes];
    };
    $workers[]=$start(true); [$a,$pa]=$workers[0];
    $ready = '';
    while (($line=fgets($pa[1])) !== false) { if (trim($line)==='READY') { $ready='READY'; break; } }
    if (!$ready) { throw new RuntimeException('Primeira transação não iniciou.'); }
    $workers[]=$start(false); [$b,$pb]=$workers[1];
    while (($line=fgets($pb[1])) !== false) { if (trim($line)==='STARTED') { break; } }
    usleep(400000); // Permite que a segunda transação alcance a restrição de exclusão.
    if (!proc_get_status($b)['running']) { throw new RuntimeException('Segunda transação não aguardou a primeira.'); }
    fwrite($pa[0],"COMMIT\n"); fflush($pa[0]);
    $outputA=stream_get_contents($pa[1]); $outputB=stream_get_contents($pb[1]);
    if (!str_contains($outputA,'SAVED') || !str_contains($outputB,'BLOCKED 1')) { throw new RuntimeException('Concorrência não confirmada.'); }
    if (Access::table('Apontamentos')->find()->where(['atividade_id'=>$activity])->count() !== 1) { throw new RuntimeException('Quantidade inesperada após corrida.'); }
    echo "OK: dois processos PHP independentes; uma gravação aceita, a concorrente bloqueada com conflito identificado.\n";
} finally {
    foreach ($workers as [$process,$pipes]) {
        foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
        if (is_resource($process)) { if (proc_get_status($process)['running']) { proc_terminate($process); } proc_close($process); }
    }
    // Exclui somente as fixtures identificadas criadas por esta execução.
    if ($user) {
        Access::table('Auditorias')->deleteAll(['usuario_id'=>$user->id]);
        Access::table('Apontamentos')->deleteAll(['criado_por'=>$user->id]);
        Access::table('Atividades')->deleteAll(['criado_por'=>$user->id]);
    }
    if ($notebook) { Access::table('Cadernos')->deleteAll(['id'=>$notebook->id]); }
    if ($sector) { Access::table('Setores')->deleteAll(['id'=>$sector->id]); }
    if ($season) { Access::table('Entressafras')->deleteAll(['id'=>$season->id]); }
    if ($user) { Access::table('Usuarios')->deleteAll(['id'=>$user->id]); }
    echo "Fixtures temporárias desta execução removidas; dados existentes preservados.\n";
}
