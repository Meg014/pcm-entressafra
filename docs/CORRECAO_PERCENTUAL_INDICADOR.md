# Correção: evolução é indicador, sem bloqueio de lançamentos

Novo Apontamento aceita O.S. em qualquer percentual. Foram removidos o bloqueio do serviço `TimeEntries`, o critério de liberação do autocomplete, a desativação do botão por percentual e o aviso amarelo. A informação do percentual pode continuar aparecendo como texto neutro. As validações de identidade, situação do funcionário, data, intervalo, conflito de horários, O.S., entressafra e permissões permanecem em vigor.

Insumos continuam independentes do percentual. Horas e insumos não gravam na evolução, e o TOTVS permanece somente leitura.

## Fila e detalhe

`/fila-pcm` apresenta a coluna **Evolução (%)**. `OrderProgress` obtém o percentual real de `Evolucao::state()` usando `OperacaoRapida::reference()`, a mesma referência automática de Lançar porcentagem. Sem histórico de evolução, retorna 0%. A situação da O.S. no TOTVS não é usada como percentual.

A consulta de pendências não tem condição de percentual: considera horas não canceladas em `pendente`/`conferido` ou insumos `pendente`, respeitando o período selecionado. A evolução é anexada aos resultados, sem mudar quem entra na fila. Uma atualização de evolução aparece na próxima consulta e mantém as contagens e os registros de recebimento.

O detalhe por O.S. mostra **Percentual atual de evolução**, com horas e insumos nas seções existentes. Seleção, confirmação e reabertura continuam independentes e auditadas. O ajuste de intervalo continua exclusivo de ADMIN_PCM.

## Diagnóstico do banco local

Executado `php tools/diagnosticar_fila_pcm.php`, com transação PostgreSQL explicitamente somente leitura. Na entressafra 2026/2027 (id 43), havia:

- 626 O.S. cadastradas;
- 0 registros de apontamentos de horas;
- 0 registros de insumos;
- 0 O.S. com pendências na fila.

Nesse banco, a fila vazia está correta por inexistirem recebimentos. Não foram criados dados operacionais fictícios, modificadas situações, excluídos registros ou alterada a evolução para popular a tela.

## Arquivos

- `src/Service/OrderProgress.php`: indicador de evolução real; substitui `OrderCompletion.php`, removido.
- `src/Service/TimeEntries.php`: retirada da autorização por percentual.
- `src/Service/OperacaoRapida.php`: percentual informativo no retorno da O.S.
- `src/Service/FilaPcm.php`: evolução no resultado da fila, sem filtro por percentual.
- `src/Controller/FilaPcmController.php`: indicador no detalhe.
- `templates/Apontamentos/batch.php`, `templates/element/order_autocomplete.php`: apresentação sem bloqueio.
- `templates/FilaPcm/index.php` e `view.php`: coluna de evolução e cabeçalho do detalhe.
- `webroot/js/apontamentos-batch.js` e `order-autocomplete.js`: retirada das regras de liberação por percentual.
- `tools/diagnosticar_fila_pcm.php`: diagnóstico somente leitura por entressafra.
- `tests/TestCase/Controller/EtapaDoisTest.php`, `FilaPcmUnificadaTest.php`, `tests/TestCase/Service/CalendariosOrdensTest.php`: regressão da regra corrigida.
- `tools/test_apontamentos_batch_browser.js`, `tools/test_fila_pcm_browser.js`: verificações de interface.
- Documentação deste ajuste e atualização de `docs/EVOLUCAO_APONTAMENTOS_INSUMOS.md`.

Não é necessária migration para esta correção.

## Executar os testes

```powershell
php vendor/phpunit/phpunit/phpunit
$env:BATCH_PREVIEW='1'
$env:FILA_PREVIEW='1'
php vendor/phpunit/phpunit/phpunit --filter 'testBatchFormAndSingleSubmission|testUnifiedHttpPageHasIndependentFormsAndAdminOnlyEditHour|testSuppliesHttpFormAndSingleSave'
powershell -ExecutionPolicy Bypass -File tools/test_apontamentos_batch_browser.ps1
powershell -ExecutionPolicy Bypass -File tools/test_apontamentos_batch_browser.ps1 -Preview fila-unificada-preview.html -TestScript test_fila_pcm_browser.js
powershell -ExecutionPolicy Bypass -File tools/test_apontamentos_batch_browser.ps1 -Preview supplies-preview.html -TestScript test_insumos_browser.js
php tools/diagnosticar_fila_pcm.php
```

PHPUnit usa SQLite isolado. Os testes cobrem lançamentos únicos e em lote em 0%, 10%, 50%, 99% e 100%, manutenção exata do histórico de evolução, indicador na fila em 0/50/100%, atualização para 70% após evolução real, fila com horas/insumos/ambos, permissões, conflitos, auditoria e confirmações independentes. Os testes de navegador usam somente prévias e respostas fictícias, sem gravar no banco operacional.
## Resultados

- Suíte completa: 277 testes e 2.322 asserções aprovados.
- Conferência final das prévias: 3 testes e 40 asserções aprovados.
- Navegador: 203 verificações aprovadas em computador, tablet e celular, cobrindo Novo Apontamento, central PCM e Insumos.
- Diagnóstico PostgreSQL executado em transação somente leitura: fila local vazia confirmada pela ausência de recebimentos.
- Sintaxe PHP e `git diff --check`: aprovados.
- Nenhuma migration necessária ou executada para esta correção; nenhum registro operacional criado ou alterado.