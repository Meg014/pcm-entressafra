<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/config/bootstrap.php';
$db=Cake\Datasource\ConnectionManager::get('default');
if ($db->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra') { exit(2); }
$path=TMP.'cadernos_many_to_many_baseline.json';
try {
    if (($argv[1]??'')==='--before') {
        $baseline=[];
        foreach (['cadernos','atividades','apontamentos','auditorias'] as $name) {
            $columns=$db->getSchemaCollection()->describe($name)->columns();
            $sql='SELECT '.implode(',',array_map(fn($c)=>'"'.$c.'"',$columns)).' FROM "'.$name.'" ORDER BY id';
            $hashes=[];
            foreach ($db->execute($sql)->fetchAll('assoc') as $row) { $hashes[$row['id']]=hash('sha256',json_encode($row,JSON_THROW_ON_ERROR)); }
            $baseline[$name]=['columns'=>$columns,'hashes'=>$hashes];
            echo $name,': ',count($hashes),' registros inspecionados.',PHP_EOL;
        }
        file_put_contents($path,json_encode($baseline,JSON_THROW_ON_ERROR));
        echo 'Manifesto de integridade salvo em tmp; sem dados pessoais ou credenciais.',PHP_EOL;
    } else {
        $baseline=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
        foreach ($baseline as $name=>$info) {
            $sql='SELECT '.implode(',',array_map(fn($c)=>'"'.$c.'"',$info['columns'])).' FROM "'.$name.'" ORDER BY id';
            $current=[];
            foreach ($db->execute($sql)->fetchAll('assoc') as $row) { $current[$row['id']]=hash('sha256',json_encode($row,JSON_THROW_ON_ERROR)); }
            foreach ($info['hashes'] as $id=>$hash) { if (($current[$id]??null)!==$hash) { throw new RuntimeException('Histórico divergente.'); } }
            echo $name,': registros e colunas anteriores preservados: OK.',PHP_EOL;
        }
        $missing=$db->execute('SELECT COUNT(*) AS n FROM cadernos c WHERE NOT EXISTS (SELECT 1 FROM cadernos_setores cs WHERE cs.caderno_id=c.id AND cs.setor_id=c.setor_id)')->fetch('assoc')['n'];
        if ((int)$missing!==0) { throw new RuntimeException(); }
        $pending=$db->execute('SELECT COUNT(*) AS n FROM atividades WHERE setor_executante_id IS NULL')->fetch('assoc')['n'];
        echo 'Vínculos legados migrados: OK. Atividades com setor pendente: ',(int)$pending,PHP_EOL;
        $legacy=$db->execute("SELECT COUNT(*) AS n FROM atividades WHERE identificacao_setor='legado'")->fetch('assoc')['n'];
        echo 'Atividades legadas que exigem confirmação da área antes de novos apontamentos: ',(int)$legacy,PHP_EOL;
    }
} catch (Throwable) { fwrite(STDERR,'Falha na verificação de integridade; detalhes sensíveis omitidos.'.PHP_EOL); exit(1); }
