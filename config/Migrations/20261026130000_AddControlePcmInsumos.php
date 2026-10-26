<?php
declare(strict_types=1);
use Migrations\BaseMigration;

class AddControlePcmInsumos extends BaseMigration
{
    public function up(): void
    {
        $this->table('insumos')
            ->addColumn('controle_pcm','string',['limit'=>30,'default'=>'pendente'])
            ->addColumn('revisao_pcm','integer',['default'=>0])
            ->addColumn('confirmado_por','integer',['null'=>true])
            ->addColumn('confirmado_em','datetime',['null'=>true])
            ->addColumn('alterado_por','integer',['null'=>true])
            ->addColumn('modified','datetime',['null'=>true])
            ->addIndex(['controle_pcm','atividade_id','created'])
            ->addForeignKey('confirmado_por','usuarios','id',['delete'=>'RESTRICT'])
            ->addForeignKey('alterado_por','usuarios','id',['delete'=>'RESTRICT'])->update();
        $this->table('auditorias')->addColumn('insumo_id','integer',['null'=>true])
            ->addIndex(['insumo_id','created'])
            ->addForeignKey('insumo_id','insumos','id',['delete'=>'RESTRICT'])->update();
        // Link prior receipt audits without replacing any historical payload.
        foreach($this->fetchAll("SELECT id,depois FROM auditorias WHERE acao='insumo_criacao'") as $audit){
            $data=json_decode((string)$audit['depois'],true);
            $id=is_array($data)?filter_var($data['insumo_id']??null,FILTER_VALIDATE_INT):false;
            if($id&&$id>0&&$this->fetchRow('SELECT id FROM insumos WHERE id='.(int)$id)){
                $this->execute('UPDATE auditorias SET insumo_id='.(int)$id.' WHERE id='.(int)$audit['id']);
            }
        }
        if($this->getAdapter()->getAdapterType()==='pgsql'){
            $this->execute("ALTER TABLE insumos ADD CONSTRAINT ck_insumos_controle_pcm CHECK (controle_pcm IN ('pendente','lancado') AND revisao_pcm >= 0)");
        }
    }
    public function down(): void
    {
        throw new RuntimeException('Os controles e históricos de insumos devem ser preservados.');
    }
}
