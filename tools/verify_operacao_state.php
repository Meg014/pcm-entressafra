<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';require dirname(__DIR__).'/config/bootstrap.php';
$db=Cake\Datasource\ConnectionManager::get('default');if($db->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra')exit(2);
foreach([
    'Entressafras ativas'=>"SELECT COUNT(*) AS n FROM entressafras WHERE status='ativa'",
    'O.S. vinculadas em entressafra ativa'=>"SELECT COUNT(*) AS n FROM atividades a JOIN cadernos c ON c.id=a.caderno_id JOIN entressafras e ON e.id=c.entressafra_id WHERE e.status='ativa' AND c.ativo AND a.setor_executante_id IS NOT NULL",
    'Contas de computador configuradas'=>"SELECT COUNT(*) AS n FROM usuarios WHERE computador_operacional",
    'Apontamentos aguardando TOTVS'=>"SELECT COUNT(*) AS n FROM apontamentos WHERE NOT cancelado AND controle_pcm IN ('pendente','conferido')",
    'Semanas confirmadas em entressafras ativas'=>"SELECT COUNT(*) AS n FROM evolucao_semanas s JOIN entressafras e ON e.id=s.entressafra_id WHERE e.status='ativa'",
    'Históricos de porcentagem existentes'=>'SELECT COUNT(*) AS n FROM evolucao_registros',
] as $label=>$sql){echo $label,': ',(int)$db->execute($sql)->fetch('assoc')['n'],PHP_EOL;}
