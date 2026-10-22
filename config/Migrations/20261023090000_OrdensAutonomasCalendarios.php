<?php
declare(strict_types=1);
use Migrations\BaseMigration;
class OrdensAutonomasCalendarios extends BaseMigration
{
    public function up(): void
    {
        $this->table('areas_fabrica')->addColumn('nome','string',['limit'=>150])->addIndex(['nome'],['unique'=>true])->create();
        $this->table('entressafras')->addColumn('prazo_padrao','date',['null'=>true])->update();
        $this->table('atividades')->addColumn('entressafra_id','integer',['null'=>true])->addColumn('area_fabrica_id','integer',['null'=>true])->update();
        $this->execute('UPDATE atividades SET entressafra_id=(SELECT entressafra_id FROM cadernos WHERE cadernos.id=atividades.caderno_id)');
        $this->table('cadernos')->addIndex(['id','entressafra_id'],['unique'=>true,'name'=>'uq_caderno_entressafra'])->update();
        $this->table('atividades')->changeColumn('caderno_id','integer',['null'=>true])->changeColumn('entressafra_id','integer',['null'=>false])
            ->removeIndex(['os_chave'])->addIndex(['entressafra_id','os_filial','os_numero'],['unique'=>true,'name'=>'uq_os_entressafra'])
            ->addIndex(['entressafra_id','os_chave'],['unique'=>true])
            ->addForeignKey('entressafra_id','entressafras','id',['delete'=>'RESTRICT'])->addForeignKey('area_fabrica_id','areas_fabrica','id',['delete'=>'RESTRICT'])
            ->addForeignKey(['caderno_id','entressafra_id'],'cadernos',['id','entressafra_id'],['delete'=>'RESTRICT','constraint'=>'fk_os_caderno_entressafra'])->update();
        $this->table('calendarios_operacionais')->addColumn('entressafra_id','integer')->addColumn('chave','string',['limit'=>50])
            ->addColumn('area_fabrica_id','integer',['null'=>true])->addColumn('setor_id','integer',['null'=>true])
            ->addColumn('data_inicio','date',['null'=>true])->addColumn('data_fim','date')->addColumn('inicio_confirmado','boolean',['default'=>false])
            ->addIndex(['entressafra_id','chave'],['unique'=>true])
            ->addForeignKey('entressafra_id','entressafras','id',['delete'=>'RESTRICT'])->addForeignKey('area_fabrica_id','areas_fabrica','id',['delete'=>'RESTRICT'])->addForeignKey('setor_id','setores','id',['delete'=>'RESTRICT'])->create();
        $this->table('evolucao_versoes')->addColumn('calendario_id','integer',['null'=>true])->addForeignKey('calendario_id','calendarios_operacionais','id',['delete'=>'RESTRICT'])->update();
        foreach($this->fetchAll("SELECT id FROM entressafras WHERE nome='2026/2027'") as $s){
            $this->execute('UPDATE entressafras SET data_fim=?,prazo_padrao=? WHERE id=?',['2027-04-10','2027-03-26',$s['id']]);
            $this->execute('INSERT INTO calendarios_operacionais(entressafra_id,chave,data_fim,inicio_confirmado) VALUES (?,?,?,?)',[$s['id'],'padrao','2027-03-26',0]);
            foreach(['Moenda'=>'2027-04-10','Tratamento de Caldo'=>'2027-03-26','Destilaria'=>'2027-03-26'] as $name=>$end){
                $area=$this->fetchRow("SELECT id FROM areas_fabrica WHERE nome='".str_replace("'","''",$name)."'");if(!$area){$this->execute('INSERT INTO areas_fabrica(nome) VALUES (?)',[$name]);$area=$this->fetchRow("SELECT id FROM areas_fabrica WHERE nome='".str_replace("'","''",$name)."'");}
                $this->execute('INSERT INTO calendarios_operacionais(entressafra_id,chave,area_fabrica_id,data_fim,inicio_confirmado) VALUES (?,?,?,?,?)',[$s['id'],'area:'.$area['id'],$area['id'],$end,0]);
            }
        }
        if($this->getAdapter()->getAdapterType()==='pgsql'){
            $this->execute('ALTER TABLE calendarios_operacionais ADD CONSTRAINT ck_calendario_periodo CHECK ((data_inicio IS NULL OR data_inicio<=data_fim) AND (NOT inicio_confirmado OR data_inicio IS NOT NULL) AND NOT (area_fabrica_id IS NOT NULL AND setor_id IS NOT NULL))');
        }
    }
    public function down(): void { throw new RuntimeException('Preservar ordens, classificações e históricos; reversão automática não permitida.'); }
}
