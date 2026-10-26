<?php
declare(strict_types=1);
use Migrations\BaseMigration;

class CreateServicosSugeridos extends BaseMigration
{
    public function up(): void
    {
        $this->table('servicos_sugeridos')
            ->addColumn('descricao','string',['limit'=>500])
            ->addColumn('descricao_busca','string',['limit'=>500])
            ->addColumn('ativo','boolean',['default'=>true])
            ->addColumn('created','datetime')->addColumn('modified','datetime')
            ->addIndex(['descricao_busca'],['unique'=>true])->create();
        if($this->getAdapter()->getAdapterType()==='pgsql'){
            $this->execute('CREATE INDEX idx_servicos_sugeridos_prefixo ON servicos_sugeridos (descricao_busca varchar_pattern_ops) WHERE ativo');
        }else{$this->table('servicos_sugeridos')->addIndex(['ativo','descricao_busca'])->update();}
        $initial=['DESMONTAGEM'=>'desmontagem','DESMONTAGEM E MONTAGEM'=>'desmontagem e montagem','DESMONTAGEM DE EQUIPAMENTO'=>'desmontagem de equipamento','MONTAGEM'=>'montagem','MANUTENÇÃO PREVENTIVA'=>'manutencao preventiva','MANUTENÇÃO CORRETIVA'=>'manutencao corretiva','LIMPEZA'=>'limpeza','LUBRIFICAÇÃO'=>'lubrificacao','INSPEÇÃO'=>'inspecao','REGULAGEM'=>'regulagem','AJUSTE'=>'ajuste','SUBSTITUIÇÃO DE PEÇAS'=>'substituicao de pecas','SOLDAGEM'=>'soldagem','TORQUEAMENTO'=>'torqueamento','TESTE DE FUNCIONAMENTO'=>'teste de funcionamento'];
        $rows=[];$now=gmdate('Y-m-d H:i:s');
        foreach($initial as $description=>$key)$rows[]=['descricao'=>$description,'descricao_busca'=>$key,'ativo'=>true,'created'=>$now,'modified'=>$now];
        $this->table('servicos_sugeridos')->insert($rows)->saveData();
    }
    public function down(): void { throw new RuntimeException('Preserve o catálogo administrado; reversão automática não permitida.'); }
}
