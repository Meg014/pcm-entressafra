# Apontamentos em dois períodos

Novo apontamento mantém a planilha e o lote por O.S., com início/fim da manhã e início/fim da tarde. Um período pode ficar vazio; cada período informado exige o par completo, fim posterior ao início e mesma data. Os períodos devem estar em ordem, sem sobreposição. Não existem horários fixos de almoço.

O total soma somente os períodos trabalhados. Conflitos com outros registros e entre linhas do lote consideram cada período separadamente, permitindo utilizar o intervalo entre eles. A transação do lote continua integral.

## Banco e histórico

Migration `20261027100000_AddApontamentoPeriodos`: acrescenta quatro timestamps UTC opcionais. `inicio` e `fim` mantêm os limites externos para filtros existentes; `minutos` passa a somar os períodos. Nenhum registro antigo é reescrito. Registros com as quatro colunas nulas usam seu intervalo original, exibido na primeira coluna de período.

A restrição PostgreSQL usa `tsmultirange` (PostgreSQL 14 ou superior) para impedir conflitos concorrentes sem bloquear o intervalo. A migration preserva a restrição de duração dos registros antigos e valida os novos pares. O rollback é bloqueado para não apagar períodos e histórico.

Correções de horários continuam exclusivas de ADMIN_PCM, com motivo, auditoria antes/depois e revisão PCM. A visão “Ver registros”, os detalhes e as listagens mostram os períodos e o total. Confirmação no TOTVS permanece manual.

No ambiente local, somente esta migration foi aplicada usando uma pasta temporária como `--source`; as duas migrations anteriores que já estavam pendentes permaneceram pendentes. O cache de schema foi limpo.

## Verificação

```powershell
php vendor/phpunit/phpunit/phpunit --filter 'EntryPeriodsTest|testSplitPeriods'
php tools/verify_apontamento_periodos_postgres.php
```

A verificação PostgreSQL usa uma tabela temporária com as restrições da migration e reverte a transação. Testa períodos isolados, soma, legado, intervalo livre, sobreposição, pares incompletos e duração incorreta. Não consulta nem altera o TOTVS.

Os testes JavaScript estão em `tools/test_apontamento_masks_js.js` (formulário de correção, máscaras e períodos) e `tools/test_entry_periods_js.js` (planilha em lote, preenchimento opcional, totais e erros).
