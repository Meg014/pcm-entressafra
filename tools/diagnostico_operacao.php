<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/config/bootstrap.php';
use App\Service\Access;
$db=Access::table('Usuarios')->getConnection();
$columns=Access::table('Usuarios')->getSchema()->columns();
$users=$db->execute("SELECT u.id,u.login,u.perfil,u.ativo,u.computador_operacional,count(us.setor_id) AS setores FROM usuarios u LEFT JOIN usuarios_setores us ON us.usuario_id=u.id WHERE lower(u.login) LIKE '%computador%' GROUP BY u.id ORDER BY u.login")->fetchAll('assoc');
$constraints=$db->execute("SELECT conname,pg_get_constraintdef(oid) AS regra FROM pg_constraint WHERE conrelid='apontamentos'::regclass AND contype='x'")->fetchAll('assoc');
echo json_encode(['usuarios'=>$users,'sessao_permanente_coluna'=>in_array('sessao_computador_hash',$columns,true),'session'=>\Cake\Core\Configure::read('Session'),'exclusao_horarios'=>$constraints,'aguardando_bloqueio'=>$db->execute("SELECT count(*) AS n FROM pg_stat_activity WHERE datname=current_database() AND wait_event_type='Lock'")->fetch('assoc')['n']],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
