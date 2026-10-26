# Evolução, ajuste de horários e insumos

## Apontamentos liberados por evolução

`OrderCompletion::status()` consulta a evolução oficial da O.S. até a data atual de São Paulo. Sem evolução registrada, o percentual é 0%. O autocomplete e a identificação de O.S. retornam `percentual_atual` e `liberada_apontamento`.

Novo Apontamento mostra o percentual atual e a situação de liberação. O botão de salvar permanece bloqueado até selecionar uma O.S. com exatamente 100%. Ao pesquisar outra O.S. ou limpar a seleção, o botão volta a ficar bloqueado. O serviço `TimeEntries` confirma novamente a evolução no servidor para qualquer novo lançamento, inclusive requisições diretas e lotes. Dados de percentual enviados pelo navegador são desconsiderados.

No PostgreSQL, o salvamento usa o mesmo bloqueio da entressafra utilizado pelas atualizações de evolução. Assim, a conclusão não pode mudar entre a conferência e a gravação do lote. Não há liberação especial para administradores abaixo de 100%. Correções em registros existentes preservam o histórico e não dependem de a evolução continuar em 100%.

## Edição de horário

Nos detalhes do apontamento, ADMIN_PCM recebe a ação **Editar hora**. O formulário permite ajustar data e horas, exige motivo e mantém as validações de intervalo e conflitos. PCM continua podendo corrigir a descrição, com data e horas somente leitura; uma requisição forjada que altere o intervalo recebe 403. RESPONSAVEL_SETOR não pode corrigir apontamentos.

A auditoria existente registra usuário, data, motivo e os valores anteriores/posteriores, incluindo início, fim e minutos. Apontamentos cancelados ou já confirmados como lançados no TOTVS mantêm suas proteções anteriores; uma confirmação precisa ser reaberta pelo fluxo existente antes da correção.

## Insumos

Menu **Insumos** → `/insumos/add`: seleciona a O.S. e preenche linhas com funcionário confirmado no TOTVS, data, material e quantidade. A tela usa o mesmo autocomplete de funcionários e de O.S., com botões compactos **+** e **−**. A quantidade deve ser positiva, aceita vírgula ou ponto decimal e até quatro casas decimais. Materiais são informados como texto, até 500 caracteres.

O lote valida todas as linhas antes de persistir. Insumos e auditorias são gravados na mesma transação; qualquer falha desfaz o lote. Erros de validação aparecem por linha e mantêm os campos preenchidos. A consulta `/insumos` lista apenas os registros da entressafra atual e dos setores autorizados. Contas operacionais também podem lançar e consultar insumos dos seus setores. A exigência de 100% é a regra de novos apontamentos de horas.

A migration `20261026120000_CreateInsumos.php` cria somente a tabela `insumos`, com chaves estrangeiras para atividade e usuário, quantidade decimal, funcionário, material, data, snapshot e horário de criação. Ela foi aplicada ao banco exclusivo `pcm_controle_entressafra`. Nenhum lançamento operacional foi inserido e o TOTVS permanece somente leitura.

## Validação

```powershell
php vendor/phpunit/phpunit/phpunit
$env:BATCH_PREVIEW='1'
php vendor/phpunit/phpunit/phpunit --filter 'testBatchFormAndSingleSubmission|testSuppliesHttpFormAndSingleSave|testCompletion|testOnlyAdmin|testSupplies'
powershell -ExecutionPolicy Bypass -File tools/test_apontamentos_batch_browser.ps1
powershell -ExecutionPolicy Bypass -File tools/test_apontamentos_batch_browser.ps1 -Preview supplies-preview.html -TestScript test_insumos_browser.js
```

Os testes usam SQLite isolado e gateways simulados. Cobrem percentuais 0, 85, 99,99 e 100, tentativa de forjar a liberação, autorização de horário, auditoria, lote de insumos válido e inválido, quantidades fracionadas, data inválida, funcionário não apto e rollback após falha na segunda gravação. Os testes de navegador usam prévias autenticadas fictícias em computador, tablet e celular, sem salvar no banco operacional.
### Resultados

- Suíte completa: 269 testes, 2.187 asserções, todos aprovados.
- Rodada adicional das três funcionalidades, incluindo rollback de insumos: 8 testes, 70 asserções, todos aprovados.
- Conferência final da apresentação do percentual: 2 testes, 14 asserções, aprovados.
- Navegador: 137 verificações aprovadas nas duas telas, em computador, tablet e celular.
- Migration `CreateInsumos`: aplicada no PostgreSQL exclusivo do projeto.
- Sintaxe PHP e `git diff --check`: aprovados.