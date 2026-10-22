<?php
declare(strict_types=1);
use Migrations\BaseMigration;

class CadernosMultiplosSetores extends BaseMigration
{
    public function up(): void
    {
        $this->table('cadernos_setores')->addColumn('caderno_id','integer')->addColumn('setor_id','integer')
            ->addIndex(['caderno_id','setor_id'],['unique'=>true,'name'=>'uq_caderno_setor'])
            ->addIndex(['setor_id','caderno_id'])
            ->addForeignKey('caderno_id','cadernos','id',['delete'=>'CASCADE'])
            ->addForeignKey('setor_id','setores','id',['delete'=>'RESTRICT'])->create();
        $this->execute('INSERT INTO cadernos_setores (caderno_id,setor_id) SELECT id,setor_id FROM cadernos');
        $this->table('cadernos')->addColumn('area_fisica','string',['limit'=>150,'null'=>true])->update();
        $this->table('atividades')
            ->addColumn('setor_executante_id','integer',['null'=>true])
            ->addColumn('area_executante_totvs','string',['limit'=>6,'null'=>true])
            ->addColumn('identificacao_setor','string',['limit'=>20,'default'=>'pendente'])
            ->addIndex(['setor_executante_id','caderno_id'])->update();
        $pending=0;
        foreach ($this->fetchAll('SELECT a.id,a.caderno_id,a.os_filial,a.snapshot_totvs,c.setor_id FROM atividades a JOIN cadernos c ON c.id=a.caderno_id') as $row) {
            $snapshot=json_decode($row['snapshot_totvs'],true);
            $area=is_array($snapshot)?trim((string)($snapshot['codigo_area']??'')):'';
            $branch=is_array($snapshot)?trim((string)($snapshot['filial']??$row['os_filial'])):trim($row['os_filial']);
            $sector=null; $origin='pendente';
            if ($area!=='') {
                $matches=[];
                foreach ($this->fetchAll('SELECT id,area_totvs,filial_totvs FROM setores') as $candidate) {
                    if ($candidate['area_totvs']===$area && $candidate['filial_totvs']===$branch) { $matches[]=(int)$candidate['id']; }
                }
                if (count($matches)===1) { $sector=$matches[0]; $origin='area_totvs'; }
            } elseif (is_array($snapshot)) { $sector=(int)$row['setor_id']; $origin='legado'; }
            if ($sector!==null) {
                $exists=false;
                foreach ($this->fetchAll('SELECT setor_id FROM cadernos_setores WHERE caderno_id='.(int)$row['caderno_id']) as $link) { if ((int)$link['setor_id']===$sector) { $exists=true; } }
                if (!$exists) { $this->execute('INSERT INTO cadernos_setores(caderno_id,setor_id) VALUES (?,?)',[(int)$row['caderno_id'],$sector]); }
            } else { ++$pending; }
            $this->execute('UPDATE atividades SET setor_executante_id=?,area_executante_totvs=?,identificacao_setor=? WHERE id=?',[$sector,$area!==''?$area:null,$origin,(int)$row['id']]);
        }
        $this->table('atividades')->addForeignKey(['caderno_id','setor_executante_id'],'cadernos_setores',['caderno_id','setor_id'],['delete'=>'RESTRICT','update'=>'RESTRICT','constraint'=>'fk_atividade_caderno_setor'])->update();
    }
    public function down(): void
    { throw new \RuntimeException('Não é seguro reduzir cadernos multissetoriais automaticamente. A coluna legada foi preservada; use restauração controlada para reversão.'); }
}
