# Curva S semanal — fechamento de terça-feira

**Modelo anterior de programação manual.** A linha programada passou a ser automática; consulte [Curva S automática](CURVA_S_AUTOMATICA.md) para as regras e telas atuais. As regras de corte, idempotência e preservação de versões descritas aqui continuam sendo reutilizadas.

## Uso

1. Selecione a entressafra e abra **Controle PCM / Curva S** (`/controle-pcm`). Escolha um setor principal para ver seu gráfico. O indicador de evolução simples continua mostrando a posição atual das O.S.; o gráfico semanal usa somente fechamentos consolidados.
2. Como **ADMIN_PCM**, abra **Administração → Programação e apuração semanal** ou `/curva-s/programacao`. Escolha o setor, informe suas metas acumuladas por semana e a justificativa e salve. Valores entre 0 e 100, sem redução entre metas preenchidas. Células vazias não recebem metas automáticas; não se exige completar todas as semanas ou terminar em 100%.
3. Na mesma página, configure os dias da semana trabalhados e eventuais datas não operacionais no calendário operacional já existente. Não há novo cadastro de dias. Datas de início/fim continuam em Setores/Prazos. Se o calendário for padrão, a regra é compartilhada pelos setores que o utilizam. Sem datas confirmadas e dias da semana configurados, os indicadores de dias permanecem vazios.
4. Em **Apuração das semanas encerradas**, abra a terça-feira desejada e clique **Apurar semana encerrada**. Repetir a mesma apuração retorna a versão existente. O gráfico passa a apresentar seu realizado e programado consolidados.
5. Para uma correção, abra a semana já apurada, justifique e clique **Registrar nova versão da apuração**. A versão anterior permanece no histórico. **Consultar composição** permite conferir as O.S., percentuais, registros de origem, regra de corte, calendário e justificativa de cada versão, inclusive versões substituídas.

PCM pode consultar a curva. Metas, regras de dias e apurações exigem ADMIN_PCM, inclusive no backend e comando. O escopo de entressafra é conferido. Nenhuma consulta ao TOTVS é necessária para esse fluxo.

## Semanas e datas de corte

As semanas são derivadas das datas existentes da entressafra, em `America/Sao_Paulo`, sem modificar `evolucao_semanas` ou criar outro calendário de dias. A primeira e a última semana podem ser parciais. Uma semana iniciada numa terça-feira encerra na segunda-feira seguinte; a referência de análise é a terça-feira imediatamente posterior. Se a entressafra terminar antes da segunda-feira, o último período encerra na data final e é analisado na próxima terça-feira.

Análise **27/10/2026**: período **20/10/2026 00:00 a 26/10/2026 23:59:59** em Brasília. Corte armazenado: **27/10/2026 02:59:59 UTC**. A apuração inicial considera registros cuja referência efetiva seja menor ou igual à segunda-feira e cuja criação já tivesse ocorrido até esse corte. Executar o comando dias depois mantém esse mesmo corte; não usa simplesmente o acumulado atual.

Um lançamento retroativo criado após o corte não muda a versão consolidada. Somente uma correção administrativa explícita aceita registros retroativos disponíveis até a data/hora dessa correção, ainda limitados à referência efetiva do período. A versão informa separadamente `corte_utc` e `registros_ate_utc`.

## O.S. consideradas e cálculo

Na primeira apuração de cada semana, selecionam-se O.S. únicas da entressafra, criadas localmente até o corte, classificadas no setor principal pela mesma regra já usada em `Curva::scope`: `COALESCE(atividade.area_curva_id, caderno.area_curva_id)`. Associações secundárias de cadernos não duplicam O.S. Não se filtram O.S. por encerramento no TOTVS.

A classificação é a encontrada na primeira apuração, registrada na composição; não se presume uma classificação histórica que o sistema não consiga comprovar. A lista fica congelada, inclusive nas correções daquela semana. Mudanças posteriores de classificação ou novas O.S. podem alterar as listas de semanas futuras, mas não reescrevem as anteriores.

Cada O.S. tem peso igual. Usa-se o último registro válido por referência, revisão e ID, respeitando os dois cortes de data. Uma O.S. da lista sem registro contribui com zero. **Realizado = soma dos acumulados / quantidade de O.S.** Lista vazia resulta em valor não apurado, evitando divisão por zero. Exemplo testado: 100, 80, 50 e 20 → **62,5%**.

Cada fechamento armazena entressafra, setor, início/fim, terça de análise, programado, realizado, quantidade, horário da apuração, IDs e identidade das O.S., percentuais e registros de origem, versão do plano e calendário utilizado. Metas são obtidas da versão aprovada mais recente no momento da apuração e ficam congeladas no fechamento; alterações posteriores de metas não reescrevem fechamentos anteriores.

Pesos e metas legados permanecem no histórico existente. Pesos não entram na nova média, no gráfico semanal ou no indicador atual de `Curva`. Nenhum percentual de O.S., apontamento ou insumo é alterado ao planejar ou apurar.

## Indicadores, calendário e tendência

Os indicadores referem-se à última semana encerrada, ainda que esteja aguardando apuração. Diferença = realizado − programado, em pontos percentuais. Valor positivo indica adiantado; negativo, atrasado. Sem programado ou realizado, não há classificação.

Dias operacionais consideram os dias da semana e as exceções configurados no calendário selecionado para o setor (ou padrão). Realizados: dias até o fim da última semana encerrada. Restantes: dias posteriores até o encerramento operacional. Se as datas do calendário divergirem da entressafra, a tela informa ambos e conta apenas sua interseção, sem ajustar os cadastros.

O gráfico separa:

- **Programado:** azul-escuro; metas cadastradas e, para semanas consolidadas, o valor preservado na versão do fechamento.
- **Realizado:** verde; somente posições consolidadas. Períodos ainda não apurados ficam vazios.
- **Tendência:** laranja tracejado, identificada como estimativa. Exige duas posições consolidadas com realizado. Ritmo diário = `(último realizado − penúltimo realizado) / dias entre as referências`. Projeção = `último realizado + ritmo × dias futuros`, limitada a 0–100%. Nenhuma projeção é gravada como execução real.

## Comando e futuro agendamento

Substitua os IDs pelos cadastros reais. A prévia não grava dados:

```powershell
php bin/cake.php curva_semanal --usuario ID_ADMIN --entressafra ID_ENTRESSAFRA --simular
php bin/cake.php curva_semanal --usuario ID_ADMIN --entressafra ID_ENTRESSAFRA
```

O comando processa todas as semanas encerradas ainda não consolidadas, permitindo recuperar execuções perdidas. São opcionais `--setor ID_SETOR` e `--referencia AAAA-MM-DD` (terça-feira). Repetir a mesma referência é idempotente, sem criar revisão ou auditoria duplicada. Correções retroativas são feitas pela interface administrativa, com justificativa; o comando não corrige versões existentes silenciosamente.

Uma futura tarefa pode chamar esse comando às terças-feiras após 00:00 no fuso `America/Sao_Paulo`, usando um ADMIN_PCM ativo e o contexto explícito da entressafra. **Nenhuma tarefa foi instalada no servidor.** A consulta da tela também não cria fechamentos automaticamente.

## Estrutura e migration

Migration aditiva: `20261028100000_CurvaSemanalTercas.php`, aplicada ao PostgreSQL local `pcm_controle_entressafra`. As duas migrations anteriores que já estavam pendentes foram preservadas como pendentes; a aplicação desta migration utilizou `--source` temporário e `--no-lock`, seguida de limpeza do cache de schema.

Reutiliza `evolucao_versoes` e seus gatilhos imutáveis. Acrescenta `area_curva_id`, `referencia_semanal` e `revisao_semanal`; novos tipos `curva_meta` e `curva_sem`. Um índice único protege cada revisão da semana/setor/entressafra. A trava transacional da entressafra impede apurações concorrentes de duplicarem o fechamento. Justificativas, IDs de versão anterior e composição são registrados em auditoria.

Em `calendarios_operacionais`, acrescenta somente `dias_semana` e `dias_excluidos`, opcionais e inicialmente vazios. Não altera datas ou pressupõe jornadas. Não cria novas tabelas, remove registros ou altera o histórico anterior. O rollback destrutivo é bloqueado.

## Verificação

```powershell
$env:CURVA_SEMANAL_PREVIEW='1'
php vendor/phpunit/phpunit/phpunit tests/TestCase/Service/CurvaSemanalTest.php tests/TestCase/Service/CurvaReorganizacaoTest.php tests/TestCase/Service/CalendariosOrdensTest.php tests/TestCase/Service/EvolucaoTest.php
php tools/verify_curva_semanal_postgres.php
php tools/verify_curva_semanal_http.php
powershell -ExecutionPolicy Bypass -File tools/test_apontamentos_batch_browser.ps1 -Preview curva-semanal-preview.html -TestScript test_curva_semanal_browser.js
```

Regressão: **78 testes, 685 asserções**. Conferência final do serviço/HTTP/comando: **11 testes, 73 asserções**. Chrome: **51 verificações**, em desktop, tablet e celular. PostgreSQL: média simples, idempotência, unicidade e gatilhos de imutabilidade, com escritas de teste revertidas. HTTP local: curva e administração disponíveis, com hashes de sete tabelas preservados. Sem acesso ao TOTVS.

## Arquivos da entrega

- `config/Migrations/20261028100000_CurvaSemanalTercas.php`
- `config/routes.php`
- `src/Service/CurvaSemanal.php`
- `src/Service/Curva.php`
- `src/Controller/EvolucaoController.php`
- `src/Command/CurvaSemanalCommand.php`
- `templates/Evolucao/acompanhamento.php`
- `templates/Evolucao/programacao_semanal.php`
- `templates/element/curva_semanal.php`
- `tests/TestCase/Service/CurvaSemanalTest.php`
- `tools/verify_curva_semanal_postgres.php`
- `tools/verify_curva_semanal_http.php`
- `tools/test_curva_semanal_browser.js`
- `docs/CURVA_S_SEMANAL.md`
- `README.md`
