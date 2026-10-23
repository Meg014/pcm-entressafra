# Reinício autorizado e vinculação simples — 23/10/2026

## Resultado da limpeza

Executada exclusivamente no PostgreSQL local `pcm_controle_entressafra`, após a autorização explícita do usuário para 2.583 registros. Antes de gravar, o script comparou os dados de todas as 21 tabelas com o manifesto do backup aprovado, conferiu o SHA-256 do arquivo, a identidade local do banco e o administrador. Foram bloqueadas as tabelas durante a transação e os dados ficaram sujeitos às FKs e proteções normais do banco.

Foi necessário lidar com a imutabilidade dos históricos: a primeira tentativa foi completamente revertida. Para não desabilitar essa proteção, as cinco tabelas de evolução expressamente incluídas no plano foram esvaziadas por um `TRUNCATE` conjunto com `RESTRICT` e `CONTINUE IDENTITY`, sem `CASCADE`. As demais exclusões utilizaram DELETE em ordem de dependência. Estrutura, índices, constraints, sequências, gatilhos e funções `pcm_*` foram comparados antes/depois, assim como os registros preservados, antes do commit.

| Registros finais | Quantidade |
|---|---:|
| Administrador ADMIN_PCM (ID 3, pcm@biolasa.com.br) | 1 |
| Migrations executadas | 17 |
| Auditorias preservadas | 1.551 |
| Entressafras | 0 |
| Setores executantes e áreas de apresentação | 0 |
| Cadernos e suas associações | 0 |
| Atividades/O.S. | 0 |
| Apontamentos | 0 |
| Percentuais, semanas, versões e lotes de evolução | 0 |
| Pesos e metas | 0 |
| Calendários operacionais | 0 |
| Itens e lotes de vínculos/prévias | 0 |
| Associações de usuários e setores | 0 |

Total removido: **2.583**. Total preservado: **1.569**. Nenhuma senha foi redefinida. O registro completo do administrador, incluindo seu hash de senha, permaneceu idêntico ao backup.

Backup de recuperação: `backups/reinicializacao_20261022_204134/pcm_controle_entressafra_20261022_204134.dump`. Relatório transacional e contagem individual das 21 tabelas: `backups/reinicializacao_20261022_204134/limpeza_autorizada_20261023.json`.

`tools/execute_approved_reset.php --authorized-2583` registra a operação executada. O script recusa banco não local, backup divergente ou qualquer diferença do inventário original, portanto não permite executar novamente sobre a base vazia. Não é uma ação disponível pela interface.

## Acesso administrativo depois do reinício

Abra http://localhost:8766/login e use o login e senha habituais. A seleção de entressafra ficará vazia e oferece **Cadastrar entressafra**. Contextos antigos de período e prévias de Curva S são descartados quando o período não existe mais.

`tools/verify_reset_http.php` validou por HTTP a tela de login, o acesso autenticado do próprio ADMIN_PCM, o descarte do período removido e o formulário da primeira entressafra. A verificação não solicitou nem usou a senha em texto: conferiu que o registro/hash permaneceu idêntico e validou a sessão autenticada pela aplicação. O teste de entrada com senha ocorre na suíte isolada, com credenciais próprias de teste. As 21 tabelas permaneceram inalteradas durante a verificação HTTP.

## Vinculação simplificada

Rota mantida: **`/cadernos/{id}/vincular-lista`**. O botão da listagem e do detalhe agora se chama **Vincular O.S.**. Os IDs antigos de cadernos não existem mais; use o caderno que cadastrar na nova entressafra.

1. Selecione a entressafra e abra o caderno.
2. Clique **Vincular O.S.**, cole os números e clique **Conferir**.
3. Aguarde a consulta automática dos blocos. A tela mostra totais, repetições ignoradas e pendências.
4. Clique uma vez em **Confirmar vínculos**. Todas as O.S. elegíveis, inclusive as de outras páginas, entram no processamento automático.

Não há checkbox, seleção manual de equipe/serviço/equipamento/centro de custo, quantidade digitada, justificativa inicial ou aprovação de calendário. A confirmação calcula a quantidade no servidor, sob lock do lote, e seleciona exclusivamente os itens `elegivel`. Seleções antigas de transferência são descartadas. Confirmação repetida não duplica vínculos nem auditoria de confirmação.

O período e o caderno permanecem no contexto existente. Zeros à esquerda, deduplicação, blocos, consulta TOTVS somente leitura, revalidação antes da gravação, auditoria e paginação de dez registros foram preservados. A prévia e a navegação não criam vínculos. Descrições específicas, trava histórica/global de horários, porcentagens e cálculos da Curva S não foram reconstruídos ou alterados.

As pendências aparecem diretamente na tela. A relação completa fica em **Consultar O.S. encontradas e resultados**, e a lista original permanece disponível para consulta/retomada. Área executante sem identificação inequívoca continua sendo uma pendência; o sistema não inventa classificação ou equipe.

O.S. de outro caderno não são transferidas na confirmação simples. A pendência oferece **Revisar transferências**, que abre a operação administrativa separada em **`/cadernos/{id}/transferir-os`**, reutilizando os serviços existentes com seleção explícita e justificativa. Histórico e equipe original são preservados. Contas operacionais não acessam nenhuma dessas ações administrativas.

## Testes e escopo

Suíte completa aprovada: **232 testes e 1.736 asserções**, com relatório em `tmp/reinicio-simplificado-junit.xml`. A verificação HTTP do reinício foi repetida ao final e confirmou as mesmas contagens e hashes preservados, sem gravações na base local.

Testes isolados cobrem confirmação única de lista com várias páginas, elegíveis e pendências misturadas, remoção de seleção antiga de transferência, duplo clique, ausência de checkboxes/quantidade/justificativa no HTML, tentativa de pular a prévia, bloqueio de ações antigas de seleção na rota simples e permissões na rota administrativa separada. As regressões existentes verificam listas de 1, 10, 45 e 105 números, retomada, token, contexto, CSRF, duplicidades, trabalhadores intercalados, porcentagem, horas/conflitos e Curva S por setor.

Os testes funcionais gravam apenas na base SQLite isolada. A base PostgreSQL recém-limpa não recebeu vínculos ou fixtures. Não houve conexão com Ubuntu `srv-pcm`, alteração de outros projetos/bancos ou escrita no TOTVS.

## Arquivos alterados nesta entrega

- `tools/execute_approved_reset.php` e `tools/verify_reset_http.php`.
- `src/Service/ListLinks.php`.
- `src/Controller/OrganizacaoController.php` e `src/Controller/AppController.php`.
- `config/routes.php` (rota administrativa separada, sem nova migration).
- `templates/Organizacao/lista.php`, `templates/Organizacao/transferencia.php` e `templates/Organizacao/index.php`.
- `templates/Cadastros/index.php` e `templates/Cadastros/view.php`.
- `tests/TestCase/Service/CurvaReorganizacaoTest.php` e `tests/TestCase/Controller/PeriodosNavegacaoTest.php`.
- O plano atualizado e este relatório.
