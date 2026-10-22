# Validação da Etapa 2 — 21/10/2026

## Resultado

- PHPUnit: **32 testes, 156 assertions**, todos aprovados.
- Sintaxe PHP: **89 arquivos**, sem erros.
- Composer validate --strict: aprovado.
- PostgreSQL exclusivo confirmado: `pcm_controle_entressafra`, usuário `pcm_entressafra_app`.
- Duas novas migrations aplicadas, ambas `up`; quatro migrations anteriores preservadas.
- Restrição `ex_apontamento_funcionario_horario` e CHECKs confirmados no catálogo PostgreSQL.
- Concorrência real: dois processos PHP independentes utilizando o serviço de
  apontamentos; uma gravação aceita, a outra bloqueada com o conflito identificado.
  Fixtures próprias removidas ao final; nenhum registro preexistente alterado.
- Contagens finais das novas tabelas: 0 usuários, 0 atividades, 0 apontamentos,
  0 auditorias. Nenhuma conta ou dado de demonstração permanente foi criado.
- HTTP GET `/login`: 200, formulário de autenticação presente.
- O servidor já existente deste projeto na porta 8766 foi preservado.

## Cobertura automatizada

Os testes da Etapa 1 continuam aprovados, agora com sessão de administrador de
teste no SQLite. A Etapa 2 cobre:

- Contrato de consulta/vínculo de O.S. e confirmação de funcionário usando mocks.
- Duplicidade de O.S. em cadernos distintos e de apontamento na mesma O.S.
- Duração automática, conversão de fuso e snapshot confirmado no servidor.
- Intervalos idênticos, sobreposição parcial, total e contida.
- Conflitos entre setores e O.S. diferentes, e múltiplos conflitos retornados.
- Intervalos consecutivos permitidos e funcionários de identidades distintas.
- Datas inválidas, fim igual ao início, fim anterior ao início e meia-noite.
- Edição que exclui o próprio registro e edição que colide com outro registro.
- Motivo obrigatório, antes/depois em auditoria e preservação dos dados no formulário.
- Cancelamento lógico, liberação do intervalo e histórico de confirmações PCM.
- Permissões por setor/perfil em serviços e rotas; consulta global apenas PCM/admin.
- Login, senha inválida, logout, rotas privadas e invalidação por conta inativa.
- Hash de senha e ocultação do hash na serialização da entidade de usuário.
- Renderização das novas telas, incluindo detalhes, correção e administração.
- Bloqueio de SQL fora da allowlist e de execução direta no driver TOTVS.

O teste PostgreSQL de concorrência usa mock do TOTVS, mas executa o serviço
real de apontamentos e a restrição real do banco em conexões independentes.
Não substitui a validação da futura integração SQL Server.

## Pendências explícitas

1. Preencher HOST, DATABASE, USERNAME e PASSWORD da conexão PROTHEUS_DB_*.
2. Aguardar confirmação da configuração antes de consultar o catálogo SQL Server.
3. Validar tabelas/colunas, exclusões lógicas, situação cadastral e chaves completas
   de O.S. e funcionários no esquema real. A referência não é prova do esquema atual.
4. Implementar e validar o adaptador de SELECTs de negócio após essa identificação.
   Até lá, vínculo de O.S. e seleção de funcionário real permanecem bloqueados.
5. Criar o primeiro administrador real pelo comando documentado em ETAPA2.md.
6. Validar a política conservadora de correções exclusivas do PCM e a política
   de múltiplos vínculos intencionais da mesma O.S.
7. Validar visualmente as telas no navegador com os dados reais autorizados.

Nenhum teste de integração real com TOTVS foi executado sem as credenciais.
O aviso de OpenSSL duplicado no PHP global permanece, sem alteração do php.ini.
Nenhum projeto ou banco de outro sistema PCM foi alterado. Etapa 3 não iniciada.
