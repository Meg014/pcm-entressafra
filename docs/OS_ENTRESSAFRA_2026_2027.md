# Regras definitivas de O.S. — entressafra 2026/2027

## Consulta validada no SQL Server

A credencial da aplicação recebeu SELECT autorizado para os campos utilizados:

- DADOSP12.dbo.STJ010: TJ_ORDEM, TJ_FILIAL, TJ_SERVICO, TJ_CODAREA, TJ_CCUSTO,
  TJ_DTORIGI, TJ_SITUACA, TJ_TERMINO, TJ_CODBEM e D_E_L_E_T_.
- DADOSP12.dbo.ST4010: T4_SERVICO, T4_FILIAL, T4_NOME e D_E_L_E_T_.

O relacionamento foi validado por TJ_SERVICO = T4_SERVICO. Usa o serviço da
mesma filial; na ausência dele, usa cadastro compartilhado com filial vazia.
Exige exatamente uma correspondência e não escolhe arbitrariamente entre
serviços ambíguos. Na amostra real de 200 O.S., as 200 correspondências foram
inequívocas. Cinco identificações de O.S. também foram confirmadas individualmente.

## Disponibilidade para novo vínculo

A consulta de abertas aplica simultaneamente:

```text
TJ_SITUACA = L
TJ_TERMINO = N
20260101 <= TJ_DTORIGI <= 20261231
T4_NOME contendo ENTRESSAFRA
O.S. e serviço não apagados logicamente
Serviço relacionado de forma inequívoca
```

Não há lista fixa de códigos TJ_SERVICO: códigos antigos e atuais entram se
atenderem aos filtros. Situações C/P, término diferente de N, datas fora do
período ou serviço incompatível não podem gerar novo vínculo.

Pesquisas por número ou descrição usam parâmetros, com limite de 51 registros
na fonte e 50 na interface. Área executante e filial são filtradas no SQL
antes do limite quando há setor selecionado. Não há escrita no TOTVS.

A chave da O.S. mantém origem da família 010, número e filial. A confirmação
por chave exige exatamente uma linha; ambiguidade bloqueia o vínculo. O backend
consulta e valida as condições novamente antes de gravar no PostgreSQL.

## Mapeamento explícito de área executante

Em **Setores → Editar**, configure:

- Área executante TOTVS: código TJ_CODAREA, preservando zeros à esquerda.
- Filial TOTVS: código confirmado na O.S.; filial vazia só quando de fato compartilhada.

Uma combinação área/filial não pode estar atribuída a dois setores. Deixar
a área vazia mantém o setor sem mapeamento; nenhum valor foi preenchido
automaticamente nos setores existentes.

Em **Atividades / O.S. → Vincular O.S.**, selecione setor ou caderno e pesquise.
Se o caderno estiver selecionado, seu setor é o responsável pela autorização.
O vínculo exige que TJ_CODAREA/filial correspondam ao setor do caderno. Cadernos
continuam sendo escolhidos explicitamente no PostgreSQL; TJ_CCUSTO é preservado
como informação cadastral, sem distribuir O.S. automaticamente.

## Histórico e sincronização

A consulta de detalhe por número/filial é separada da busca de abertas. Ela
permite consultar O.S. encerradas, C/P, fora do período e outros estados, desde
que a identidade seja confirmada. Não aplica os filtros de disponibilidade.

Em **Atividades / O.S. → Visualizar**, PCM e ADMIN_PCM podem usar
**Consultar / sincronizar situação no TOTVS**. A operação:

1. Consulta o detalhe, inclusive para O.S. fora das abertas.
2. Atualiza situação e disponibilidade no PostgreSQL.
3. Preserva o snapshot original e guarda a última consulta em bloco separado.
4. Registra autor, antes/depois e data na auditoria.
5. Mantém ID, caderno, serviço original, bem original e todos os apontamentos.

Se a consulta falhar ou não confirmar a identidade, o vínculo e os dados
anteriores são preservados. Não existe rotina que exclua uma atividade porque
ela sumiu da pesquisa de abertas. Após sincronização como indisponível, a O.S.
não aparece na busca local de novos apontamentos, mas continua no cadastro,
na consulta da O.S., nos totais de horas e no histórico. Correções dos
apontamentos existentes preservam o vínculo histórico.

O autocomplete de Novo Apontamento agora consulta as abertas diretamente
no TOTVS por área/filial autorizadas e apresenta inclusive O.S. sem vínculo,
com seleção bloqueada. Para selecionar, exige atividade disponível no
caderno autorizado correspondente. O botão do PCM atualiza o snapshot local.
Veja `CONSULTAS_DIRETAS_APONTAMENTOS.md` para configuração e testes atuais.
Não foi criado agendamento automático de sincronização nesta tarefa.

Situação/encerramento TOTVS não é avanço físico. Nenhum percentual é criado,
atualizado ou derivado dessas informações. A execução semanal, conferência de
100% e Curva S seguem reservadas para etapas futuras. Os registros foram
preservados para os futuros cálculos.

## Migrations aplicadas somente no banco exclusivo

- 20261021160000_MapSetoresTotvs: area_totvs/filial_totvs opcionais e índice único.
- 20261021160100_AddDisponibilidadeAtividades: disponibilidade booleana opcional.

Atividades antigas sem disponibilidade confirmada ficam aguardando sincronização;
não são excluídas ou marcadas como concluídas. A migration não inventa situação
TOTVS, setor, caderno ou percentual.

## Testes e resultados

- **82 testes e 454 assertions aprovados**.
- L/N liberada, C, P, término S, datas inicial/final, datas fora de 2026 e inválidas.
- Serviço antigo e atual, sem hardcode de códigos, e serviço ambíguo.
- Pesquisa por número, descrição, área/filial e setor autorizado.
- Rejeição de área divergente do caderno e de novo vínculo fora das regras.
- Sincronização de encerramento preservando caderno, snapshot inicial e horas.
- O.S. encerrada ausente no autocomplete local após sincronização, com histórico acessível.
- SQL Injection, allowlist, chave inequívoca, autenticação e permissões existentes.
- SQL Server real: pesquisa limitada de abertas (51 retornos), confirmação por
  chave, número/área/filial e consulta histórica de O.S. fora das abertas aprovadas.
- Concorrência PostgreSQL com dois processos independentes novamente aprovada.
  Fixtures próprias removidas; nenhum dado operacional permanente criado.

## Utilitários

```powershell
php tools/validate_order_access.php
php tools/verify_orders.php
php bin/cake.php migrations status --connection default
php vendor/phpunit/phpunit/phpunit
```

Os relatórios exibem somente contagens e resultados de validação, sem credenciais
ou registros pessoais. O passo operacional pendente é o PCM preencher e conferir
os mapeamentos reais dos setores antes dos novos vínculos. Validação visual das
telas permanece manual.

## Arquivos principais

- src/Integration/Totvs/OrderQueries.php e Orders.php; Gateway/GatewayInterface.
- src/Service/Activities.php: pesquisa autorizada, vínculo e sincronização auditada.
- src/Controller/AtividadesController.php: filtro por setor e sincronização POST.
- Campos de mapeamento no cadastro de setores e disponibilidade em atividades.
- src/Controller/ApontamentosController.php e TimeEntries.php: disponibilidade local.
- Ferramentas de validação e testes OrdersTest/EtapaDoisTest.

Nenhum outro projeto foi alterado. Nenhum GRANT, UPDATE, encerramento ou
qualquer escrita foi executado no SQL Server.
