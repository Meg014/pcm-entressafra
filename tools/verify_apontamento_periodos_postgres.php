<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/config/bootstrap.php';
$db=\Cake\Datasource\ConnectionManager::get('default');
if ($db->execute('SELECT current_database()')->fetch()[0]!=='pcm_controle_entressafra') { throw new RuntimeException('Banco inesperado'); }
$db->begin();
try {
    $db->execute('CREATE TEMP TABLE periodos_check (LIKE apontamentos INCLUDING DEFAULTS INCLUDING CONSTRAINTS)');
    foreach (['inicio_manha','fim_manha','inicio_tarde','fim_tarde'] as $field) {
        $db->execute('ALTER TABLE periodos_check ADD COLUMN IF NOT EXISTS '.$field.' timestamp NULL');
    }
    $db->execute('ALTER TABLE periodos_check DROP CONSTRAINT ck_apontamento_intervalo');
    $migration=file_get_contents(dirname(__DIR__).'/config/Migrations/20261027100000_AddApontamentoPeriodos.php');
    preg_match("/<<<'SQL'\R(.*?)\RSQL/s",$migration,$match);
    foreach (explode(';',$match[1]) as $statement) { if(trim($statement)!=='') { $db->execute(str_replace('ALTER TABLE apontamentos','ALTER TABLE periodos_check',$statement)); } }
    $insert=static function(string $employee,array $hours,int $minutes,bool $legacy=false) use ($db): void {
        [$a,$b,$c,$d]=$hours;
        $start=$a??$c;$end=$d??$b;
        $timestamp=static fn(?string $v): ?string=>$v===null?null:'2026-10-27 '.$v.':00';
        $db->execute("INSERT INTO periodos_check (id,atividade_id,funcionario_chave,funcionario_nome,funcionario_matricula,funcionario_filial,funcionario_vinculo,snapshot,inicio,fim,minutos,descricao,criado_por,alterado_por,created,modified,inicio_manha,fim_manha,inicio_tarde,fim_tarde) VALUES (?,1,?,'Teste','1','01','teste','{}',?,?,?,'Teste',1,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,?,?,?,?)",[random_int(100000,999999),$employee,$timestamp($start),$timestamp($end),$minutes,...($legacy?[null,null,null,null]:array_map($timestamp,$hours))]);
    };
    $insert('split',['10:00','14:00','15:00','20:00'],540);
    $insert('split',['14:00','15:00',null,null],60,true); // gap accepted
    $insert('morning',['10:00','14:00',null,null],240);
    $insert('afternoon',[null,null,'15:00','17:00'],120);
    foreach ([['split',[null,null,'16:00','17:00'],60,'23P01'],['invalid',['10:00','14:00','13:00','17:00'],480,'23514'],['invalid',['10:00','14:00',null,null],999,'23514'],['invalid',['10:00',null,'15:00','17:00'],240,'23514']] as [$employee,$hours,$minutes,$state]) {
        $db->execute('SAVEPOINT period_check');
        try { $insert($employee,$hours,$minutes); throw new RuntimeException('Deveria bloquear: '.$state); }
        catch (\Cake\Database\Exception\QueryException $e) { if(!str_contains($e->getMessage(),$state)){throw $e;} }
        finally { $db->execute('ROLLBACK TO SAVEPOINT period_check'); }
    }
    echo "OK: períodos, totais, legado, intervalo livre e conflitos PostgreSQL.\n";
} finally { $db->rollback(); }
