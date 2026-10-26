# Novo Apontamento em lote

A tela `/apontamentos/add` mantém a busca de O.S. no topo e começa com uma linha. Cada linha possui pesquisa independente de funcionário, data, início, fim, descrição, duração calculada e remoção. Em telas menores a grade reorganiza os campos. Os autocompletes continuam consultando os endpoints existentes; a conexão TOTVS não foi alterada e permanece somente leitura.

`TimeEntries::saveBatch()` valida todas as linhas com a lógica existente de `save()`, sem persistir nessa primeira etapa. Em seguida detecta sobreposições entre linhas do mesmo funcionário. A persistência acontece em uma transação única, revalidando cada registro e incluindo sua auditoria. Qualquer falha desfaz todos os apontamentos e auditorias do lote. A proteção global de concorrência do PostgreSQL permanece ativa. A contagem de linhas enviada no início do formulário detecta truncamento por `max_input_vars`, impedindo gravação parcial.

O backend aplica a O.S. do topo a todas as linhas, independentemente dos valores individuais recebidos. Permissões, identidade do funcionário, situação NORMAL, duplicidades, mapeamento do setor e disponibilidade da O.S. continuam sendo verificados no servidor. Erros aparecem nas linhas, com os campos preservados. Novos registros e correções exigem início e fim na mesma data e fim maior que início. Registros históricos que atravessam dias continuam armazenados e participando das consultas de conflitos.

Nenhuma migration é necessária. Tabelas, conexões e dados operacionais existentes não foram modificados pela implementação ou pelos testes.

## Arquivos

- `src/Controller/ApontamentosController.php`: ação de lote, erros e seleção da tela.
- `src/Service/TimeEntries.php` e `BatchEntryException.php`: validação, transação e erros por linha.
- `templates/Apontamentos/batch.php` e `templates/element/batch_entry.php`: grade e linhas reutilizáveis.
- `webroot/js/apontamentos-batch.js` e `webroot/css/apontamentos-batch.css`: linhas dinâmicas, máscaras, duração e apresentação responsiva.
- `templates/element/employee_autocomplete.php` e `webroot/js/employee-autocomplete.js`: identificação independente por linha, mantendo o componente existente.
- `templates/Apontamentos/form.php` e `webroot/js/apontamentos.js`: retirada da virada de dia na correção e no formulário compatível anterior.
- Testes: `EtapaDoisTest.php`, `TimeEntryInputTest.php`, `CurvaReorganizacaoTest.php`, `EvolucaoTest.php` e harnesses JavaScript em `tools/`.

## Validação reproduzível

```powershell
php vendor/phpunit/phpunit/phpunit
$env:BATCH_PREVIEW='1'
php vendor/phpunit/phpunit/phpunit --filter testBatchFormAndSingleSubmission
powershell -ExecutionPolicy Bypass -File tools/test_apontamentos_batch_browser.ps1
```

Execute PHPUnit sequencialmente: os testes compartilham o SQLite isolado `tmp/entressafra_tests.sqlite`. A prévia é gerada pelo teste autenticado com dados fictícios. O teste de navegador utiliza Chrome headless e respostas simuladas de funcionários, sem acessar TOTVS ou gravar dados reais. Verifica larguras de computador, tablet e celular, máscaras, duração, identidades independentes, bloqueios, adição/remoção, renumeração e manutenção dos dados. O parâmetro `-Browser` permite informar outro caminho para Chrome/Chromium.

Os testes cobrem lote com uma ou várias linhas, funcionários distintos, intervalos consecutivos, conflitos internos e com registros existentes, falha em revalidação com rollback de auditoria, preservação dos campos, O.S. comum e consultas existentes. Os testes de histórico inserem fixtures antigas com virada de dia diretamente no SQLite, sem habilitar novos lançamentos desse tipo.
## Resultado desta entrega

- Suíte completa: 260 testes e 2.107 asserções aprovados.
- Revalidação final do lote e dos campos: 21 testes e 55 asserções aprovados.
- Chrome: 20 verificações em cada viewport (1440×1000, 820×1180 e 390×844), todas aprovadas.
- Harnesses JavaScript existentes: máscaras (17 asserções) e autocompletes de funcionário/O.S. nos dois modos aprovados.
- Sintaxe PHP, `git diff --check` e `composer validate --strict`: aprovados.

Os testes automatizados de persistência utilizaram SQLite isolado e gateways simulados. Não foi realizado teste de gravação no PostgreSQL operacional nem teste com consultas reais ao TOTVS.