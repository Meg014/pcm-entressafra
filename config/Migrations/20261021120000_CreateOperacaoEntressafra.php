<?php
declare(strict_types=1);
use Migrations\BaseMigration;

class CreateOperacaoEntressafra extends BaseMigration
{
    public function change(): void
    {
        $this->table('usuarios')
            ->addColumn('nome', 'string', ['limit' => 150])
            ->addColumn('login', 'string', ['limit' => 100])
            ->addColumn('senha_hash', 'string', ['limit' => 255])
            ->addColumn('perfil', 'string', ['limit' => 30])
            ->addColumn('ativo', 'boolean', ['default' => true])
            ->addColumn('falhas_login', 'integer', ['default' => 0])
            ->addColumn('bloqueado_ate', 'datetime', ['null' => true])
            ->addColumn('created', 'datetime')->addColumn('modified', 'datetime')
            ->addIndex(['login'], ['unique' => true])->create();
        $this->table('usuarios_setores')
            ->addColumn('usuario_id', 'integer')->addColumn('setor_id', 'integer')
            ->addIndex(['usuario_id', 'setor_id'], ['unique' => true])
            ->addForeignKey('usuario_id', 'usuarios', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('setor_id', 'setores', 'id', ['delete' => 'RESTRICT'])->create();
        $this->table('atividades')
            ->addColumn('caderno_id', 'integer')
            ->addColumn('os_chave', 'string', ['limit' => 255])
            ->addColumn('os_numero', 'string', ['limit' => 100])
            ->addColumn('os_filial', 'string', ['limit' => 100])
            ->addColumn('servico', 'text')->addColumn('bem', 'string', ['limit' => 255])
            ->addColumn('situacao_totvs', 'string', ['limit' => 100])
            ->addColumn('snapshot_totvs', 'text')
            ->addColumn('criado_por', 'integer')
            ->addColumn('created', 'datetime')->addColumn('modified', 'datetime')
            ->addIndex(['os_chave'], ['unique' => true])
            ->addIndex(['os_numero', 'os_filial'])->addIndex(['caderno_id'])
            ->addForeignKey('caderno_id', 'cadernos', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('criado_por', 'usuarios', 'id', ['delete' => 'RESTRICT'])->create();
        $this->table('apontamentos')
            ->addColumn('atividade_id', 'integer')
            ->addColumn('funcionario_chave', 'string', ['limit' => 255])
            ->addColumn('funcionario_nome', 'string', ['limit' => 255])
            ->addColumn('funcionario_matricula', 'string', ['limit' => 100])
            ->addColumn('funcionario_filial', 'string', ['limit' => 100])
            ->addColumn('funcionario_vinculo', 'string', ['limit' => 255])
            ->addColumn('snapshot', 'text')
            ->addColumn('inicio', 'datetime')->addColumn('fim', 'datetime')
            ->addColumn('minutos', 'integer')
            ->addColumn('descricao', 'text')
            ->addColumn('cancelado', 'boolean', ['default' => false])
            ->addColumn('controle_pcm', 'string', ['limit' => 30, 'default' => 'pendente'])
            ->addColumn('criado_por', 'integer')->addColumn('alterado_por', 'integer')
            ->addColumn('created', 'datetime')->addColumn('modified', 'datetime')
            ->addIndex(['funcionario_chave', 'inicio', 'fim'])
            ->addIndex(['atividade_id', 'inicio'])->addIndex(['controle_pcm'])
            ->addForeignKey('atividade_id', 'atividades', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('criado_por', 'usuarios', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('alterado_por', 'usuarios', 'id', ['delete' => 'RESTRICT'])->create();
        $this->table('auditorias')
            ->addColumn('apontamento_id', 'integer', ['null' => true])
            ->addColumn('usuario_id', 'integer')
            ->addColumn('usuario_nome', 'string', ['limit' => 150])
            ->addColumn('acao', 'string', ['limit' => 40])
            ->addColumn('motivo', 'text', ['null' => true])
            ->addColumn('antes', 'text', ['null' => true])->addColumn('depois', 'text')
            ->addColumn('created', 'datetime')
            ->addIndex(['apontamento_id', 'created'])
            ->addForeignKey('apontamento_id', 'apontamentos', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('usuario_id', 'usuarios', 'id', ['delete' => 'RESTRICT'])->create();
    }
}
