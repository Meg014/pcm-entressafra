<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/config/bootstrap.php';
$db=Cake\Datasource\ConnectionManager::get('default');
if ($db->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra') { exit(2); }
foreach ([
    'Apontamentos existentes'=>'SELECT COUNT(*) AS n FROM apontamentos',
    'Duração superior a 24 horas'=>'SELECT COUNT(*) AS n FROM apontamentos WHERE fim-inicio > interval \'24 hours\'',
    'Sobreposições existentes'=>'SELECT COUNT(*) AS n FROM apontamentos a JOIN apontamentos b ON a.id<b.id AND a.funcionario_chave=b.funcionario_chave AND a.inicio<b.fim AND a.fim>b.inicio WHERE NOT a.cancelado AND NOT b.cancelado',
    'Restrições de exclusão'=>'SELECT COUNT(*) AS n FROM pg_constraint WHERE conrelid=\'apontamentos\'::regclass AND contype=\'x\'',
] as $label=>$sql) { echo $label,': ',(int)$db->execute($sql)->fetch('assoc')['n'],PHP_EOL; }
foreach($db->execute('SELECT id,inicio,fim,minutos FROM apontamentos WHERE minutos>1440 ORDER BY id')->fetchAll('assoc') as $r) {
    echo 'Conferir apontamento #',(int)$r['id'],': ',(int)$r['minutos'],' minutos; datas UTC ', $r['inicio'],' até ',$r['fim'],'. Nenhuma alteração executada.',PHP_EOL;
}
