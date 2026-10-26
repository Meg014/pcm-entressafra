# Evolução, ajuste de horários e insumos

## Evolução como indicador

`OrderProgress::status()` consulta a evolução oficial pela mesma referência automática utilizada em Lançar porcentagem. Sem evolução registrada, o percentual é 0%. A informação é exibida no lançamento, na fila do PCM e no detalhe da O.S.

Horas e insumos podem ser lançados em qualquer percentual. O lançamento não modifica nem recalcula a evolução. As regras de funcionário, intervalo, sobreposição, disponibilidade da O.S., setor, entressafra, permissões e auditoria continuam ativas.

O percentual não bloqueia o botão de salvar nem o serviço de apontamentos. A fila depende exclusivamente de horas ou insumos pendentes. Veja a correção e seus testes em [CORRECAO_PERCENTUAL_INDICADOR.md](CORRECAO_PERCENTUAL_INDICADOR.md).
## Edição de horário

Nos detalhes do apontamento, ADMIN_PCM recebe a ação **Editar hora**. O formulário permite ajustar data e horas, exige motivo e mantém as validações de intervalo e conflitos. PCM continua podendo corrigir a descrição, com data e horas somente leitura; uma requisição forjada que altere o intervalo recebe 403. RESPONSAVEL_SETOR não pode corrigir apontamentos.

A auditoria existente registra usuário, data, motivo e os valores anteriores/posteriores, incluindo início, fim e minutos. Apontamentos cancelados ou já confirmados como lançados no TOTVS mantêm suas proteções anteriores; uma confirmação precisa ser reaberta pelo fluxo existente antes da correção.

## Insumos

Menu **Insumos** → `/insumos/add`: seleciona a O.S. e preenche linhas com funcionário confirmado no TOTVS, data, material e quantidade. A tela usa o mesmo autocomplete de funcionários e de O.S., com botões compactos **+** e **−**. A quantidade deve ser positiva, aceita vírgula ou ponto decimal e até quatro casas decimais. Materiais são informados como texto, até 500 caracteres.

O lote valida todas as linhas antes de persistir. Insumos e auditorias são gravados na mesma transação; qualquer falha desfaz o lote. Erros de validação aparecem por linha e mantêm os campos preenchidos. A consulta `/insumos` lista apenas os registros da entressafra atual e dos setores autorizados. Contas operacionais também podem lançar e consultar insumos dos seus setores. Horas e insumos são independentes do percentual de evolução.

A migration `20261026120000_CreateInsumos.php` cria somente a tabela `insumos`, com chaves estrangeiras para atividade e usuário, quantidade decimal, funcionário, material, data, snapshot e horário de criação. Ela foi aplicada ao banco exclusivo `pcm_controle_entressafra`. Nenhum lançamento operacional foi inserido e o TOTVS permanece somente leitura.

## Validação

```powershell
php vendor/phpunit/phpunit/phpunit
$env:BATCH_PREVIEW='1'
php vendor/phpunit/phpunit/phpunit --filter 'testBatchFormAndSingleSubmission|testSuppliesHttpFormAndSingleSave|testHoursAndSuppliesAllowEveryPercentage|testPercentageIsInformational|testOnlyAdmin|testSupplies'
powershell -ExecutionPolicy Bypass -File tools/test_apontamentos_batch_browser.ps1
powershell -ExecutionPolicy Bypass -File tools/test_apontamentos_batch_browser.ps1 -Preview supplies-preview.html -TestScript test_insumos_browser.js
```

Os testes usam SQLite isolado e gateways simulados. Cobrem lançamentos em diferentes percentuais, evolução informativa, autorização de horário, auditoria, lote de insumos válido e inválido, quantidades fracionadas, data inválida, funcionário não apto e rollback após falha na segunda gravação. Os testes de navegador usam prévias autenticadas fictícias em computador, tablet e celular, sem salvar no banco operacional.
### Resultados da entrega original

- Suíte completa: 269 testes, 2.187 asserções, todos aprovados.
- Rodada adicional das três funcionalidades, incluindo rollback de insumos: 8 testes, 70 asserções, todos aprovados.
- Conferência final da apresentação do percentual: 2 testes, 14 asserções, aprovados.
- Navegador: 137 verificações aprovadas nas duas telas, em computador, tablet e celular.
- Migration `CreateInsumos`: aplicada no PostgreSQL exclusivo do projeto.
- Sintaxe PHP e `git diff --check`: aprovados.