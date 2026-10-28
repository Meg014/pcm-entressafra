# Integração da Curva S com a grade semanal

Os acumulados vêm de `evolucao_registros`, associados à data efetiva em
`evolucao_semanas`. Para cada O.S. e data de corte, vale a última data efetiva
até o corte e sua revisão mais recente. Os valores de semanas diferentes nunca
são somados. Uma semana vazia mantém o último acumulado; apenas a ausência de
qualquer registro anterior resulta em zero.

`Curva` calcula os indicadores atuais e a média geral ou do setor principal:
soma dos acumulados das O.S. únicas / quantidade de O.S. Cada O.S. tem peso igual.
`OrderProgress` usa a data atual, limitada ao fim da entressafra, também para os
percentuais informativos e filtros da fila PCM.

`CurvaSemanal::report` calcula a posição de cada semana encerrada com os registros
atualmente disponíveis. Correções retroativas aparecem na próxima consulta,
inclusive nas semanas vazias seguintes, até outro acumulado explícito. Semanas
futuras permanecem sem realizado. A tendência usa a mesma média móvel existente.

Fechamentos em `evolucao_versoes` não são modificados. Havendo fechamento, a
composição de O.S. e o programado publicado são preservados; o realizado da
visualização é recalculado sobre essa composição. `realizado_publicado` conserva
o valor publicado para comparação e `revisao_pendente` identifica divergências.
Uma revisão formal do fechamento continua sendo explícita e auditada. Fechamentos
legados sem composição preservam o realizado publicado, pois não é seguro
reconstituí-lo. Sem fechamento, usa-se o setor principal atual e somente O.S.
criadas até o corte. Nenhum fechamento é criado pela leitura do gráfico.

A análise histórica (`Evolucao::report`) já consulta a mesma fonte semanal,
inclusive nos agrupamentos por equipe executante e caderno. Mantém os pesos
aprovados: soma(peso × acumulado) / soma(pesos), dentro do escopo consultado.
Sem pesos completos não inventa resultado ponderado; a média simples permanece
disponível separadamente. O dashboard inicial não calcula percentuais de evolução.

A programação automática, os calendários, a precisão interna e os registros
existentes não são alterados. Não há migrations nem escrita no TOTVS.
