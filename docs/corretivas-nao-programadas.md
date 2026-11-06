# O.S. corretivas não programadas

Implementação de 06/11/2026.

## Banco e implantação

Executar `php bin/cake.php migrations migrate` antes de disponibilizar o código.
A migration `20261106100000_AddTipoPlanejamento` adiciona
`atividades.tipo_planejamento`, obrigatório, com valor padrão `PROGRAMADA`, e índice
por entressafra/classificação. As O.S. existentes permanecem programadas; não há
reclassificação retroativa, alteração de porcentagens, movimentação de cadernos ou
reescrita de fechamentos. A migration foi executada automaticamente no SQLite de
testes, não no banco operacional PostgreSQL.

As constraints existentes já permitem caderno e setor executante nulos. Os índices
únicos de entressafra/filial/número e entressafra/chave foram preservados. O cadastro
pela identificação mantém o advisory lock transacional por identidade no PostgreSQL;
uma segunda identificação retorna a mesma atividade após adquirir o bloqueio.

## Identificação e setor

`Planejamento::corretiva()` compara o nome oficial do serviço, recebido de
`ST4010.T4_NOME`, ignorando caixa, acentos e espaços repetidos. Também reconhece
exatamente os nove códigos informados. `TJ_OBSERVA` e a descrição livre local não
participam da classificação. O código, o nome do serviço e os demais campos do
TOTVS são preservados em `snapshot_totvs`; a classificação é uma coluna persistida.

Consulta real, somente SELECT, realizada em 06/11/2026 por meio do driver existente:
`Queries::CORRECTIVE_SERVICES`. Foram encontrados na filial 01:
`CNPECA`, `CNPEDT`, `CNPEIN`, `CNPELB`, `CNPEMC`, `CNPEMO`, `CNPETR`.
Todos têm o mesmo nome: `CORRETIVA NAO PROGRAMADA ENTRESSAFRA`.
`CNPECD` e `CNPEEL` não retornaram nessa consulta, mas são reconhecidos como solicitado.

Como os nomes não distinguem setores, não foi criado um mapeamento presumido pelos
sufixos. Utiliza-se a associação já cadastrada de filial/`TJ_CODAREA` ao setor.
Na ausência de associação única e ativa, a O.S. permanece sem setor e pode ser
consultada e receber horas/insumos pelo PCM. A interface informa pendência de
conferência. Responsáveis de setor não recebem acesso adicional a O.S. sem setor.

## Comportamento

- Novo apontamento e Insumos consultam e identificam a O.S. sem exigir caderno ou
  porcentagem. Permanecem as validações de funcionário, períodos, conflitos,
  materiais, quantidades, auditoria e autorização.
- Apontamentos recebidos apresenta a identificação discreta e evolução `—`.
  Para corretivas, pesquisar o número exato prevalece sobre o filtro de evolução; elas não
  são tratadas como 0% nos filtros percentuais.
- `Planejamento` centraliza a condição de planejamento. Curva S, apurações,
  evolução semanal, grade de porcentagens, organização e prazos filtram programadas.
  Também há bloqueio no backend para gravação de porcentagem e vínculo a caderno,
  incluindo processos de lista e lote.
- O TOTVS continua protegido pelo driver de consultas SELECT permitidas. Foi
  acrescentada somente uma consulta fixa para conferir os nomes oficiais.

## Arquivos

Novos: `src/Service/Planejamento.php`,
`config/Migrations/20261106100000_AddTipoPlanejamento.php`,
`tests/TestCase/Service/CorretivasTest.php` e este documento.

Integração: `src/Integration/Totvs/Orders.php`, `OrderQueries.php`, `Queries.php`.

Serviços em `src/Service/`: `Activities.php`, `BulkLinks.php`,
`ClassificacaoCurva.php`, `Curva.php`, `CurvaSemanal.php`, `Evolucao.php`,
`EvolucaoSemanal.php`, `FilaPcm.php`, `Insumos.php`, `ListLinks.php`,
`OperacaoRapida.php`, `OrderProgress.php`, `OsApresentacao.php`, `OsSemCaderno.php`,
`PorcentagensLote.php`, `TimeEntries.php`.

Controllers em `src/Controller/`: `AtividadesController.php`,
`EvolucaoController.php`, `OrganizacaoController.php`, `PrazosController.php`.

Templates: `Apontamentos/batch.php`, `Atividades/index.php`, `Atividades/view.php`,
`FilaPcm/index.php`, `FilaPcm/view.php`, `element/activity_metrics.php`,
`element/os_identificacao.php`.

JavaScript em `webroot/js/`: `apontamentos-batch.js`, `order-autocomplete.js`.

## Validação

`CorretivasTest` cobre reconhecimento pelo nome/códigos, rejeição de prefixo e
descrição livre, consulta pelo número, preservação de zeros e snapshot, cadastro
sem caderno, repetição por usuários distintos, horas, insumos, auditoria, fila PCM,
pesquisa direta com filtro percentual, conflitos, acesso restrito, setor pendente,
constraint de duplicidade, bloqueio de caderno/porcentagem e inclusão de 100
corretivas sem mudar a Curva S e a evolução semanal de O.S. programadas.

A suíte utiliza SQLite. O bloqueio concorrente específico do PostgreSQL precisa de
homologação com duas sessões nesse banco; os testes locais verificam idempotência
entre usuários e a constraint de unicidade, sem simular concorrência PostgreSQL.
O ambiente não possui Node no PATH para executar `node --check`.

Execuções realizadas:

- Conjunto inicial de integração/curvas: 55 testes e 335 asserções aprovados.
- Suíte completa durante a implementação: 382 testes, 8.096 asserções, três
  falhas. A regressão do filtro de número da fila foi corrigida e passou na
  reexecução direcionada.
- Dez testes específicos de corretivas aprovados após ampliar a cobertura.
- Verificação final direcionada (corretivas, filtro da fila e apresentação de
  cadernos): 15 testes, 157 asserções, todos aprovados.
- As duas falhas restantes foram reproduzidas isoladamente no commit original
  `9b2c5b7`, em um worktree temporário já removido:
  `CalendariosOrdensTest::testUnauthorizedProgressRollsBackAutomaticWeek`
  (teste espera restrição por setor para conta operacional que já acessa todos
  os setores) e
  `OperacaoSimplificadaTest::testNoWeightsShowsUnweightedCountInsteadOfOfficialCurve`
  (teste espera um texto antigo na tela). Essas regras e textos não foram alterados
  para fazer a suíte passar.
- Verificação de sintaxe PHP dos 33 arquivos PHP novos/alterados e `git diff --check`.
