# Tendência da Curva S

## Comparação anterior à alteração

No gráfico setorial, `CurvaSemanal::trend` usava até quatro posições **exibidas**, exigia pelo menos três e calculava a média simples das velocidades entre elas, normalizadas para sete dias. A posição parcial entrava com o mesmo peso de uma semana completa. Em 2026 a visualização automática agrupa 15 dias, por isso podia usar um histórico diferente do gráfico semanal. Nos gráficos ponderados, `Evolucao::report` usava a inclinação entre a primeira e a última das quatro últimas referências com lançamento. Ambas as fórmulas extrapolavam até os limites de 0% e 100% e admitiam inclinação negativa. As telas PHP desenham os valores em SVG. O JavaScript de layout posiciona os rótulos.

Para comparar as fórmulas, considere cinco medições semanais **ilustrativas** de 7,69%, 7,89%, 8,29%, 9,09% e 9,52%. A última semana avançou 0,43 p.p. Com 3,85% programados, o setor estaria 5,67 p.p. adiantado. A projeção semanal antiga chegava a 10,06% em sete dias (média de 0,40, 0,80 e 0,43). A nova chega a 10,03% (quatro variações com pesos de 1 a 4). O mesmo cálculo com avanços de 1, 2, 3 e 4 p.p. dá 3,00 p.p./semana nas duas fórmulas. Com avanços de 0, 0, 0 e 4 p.p., a antiga dá 1,33 e a nova 1,60. O programado e a diferença em relação a ele não participam do cálculo.

## Caldeiraria em 23/11/2026

Consulta somente leitura na base configurada, entressafra 43, área 8. O calendário dá 3,85% programado. Das 54 O.S. da área existentes até o fim do dia em Brasília, nenhuma tinha lançamento de percentual disponível. A reconstrução por referência semanal e data de gravação deu 0% em 26/10 e em 02, 09, 16 e 23/11. Sobre esses pontos, a regra antiga projetava 0% tanto no modo semanal quanto no automático. A nova informa histórico insuficiente. A base local ainda não tem a coluna de tipo de planejamento usada pelo relatório atual. Os valores foram conferidos por consulta agregada às tabelas existentes, sem aplicar migrations. Os aproximadamente 10% realizados, +5,67 p.p. de diferença e +0,43 p.p. semanais informados para o ambiente operacional não puderam ser reproduzidos nessa base.

## Regra de projeção

A curva setorial usa as posições acumuladas do mesmo conjunto de O.S. no corte e em até quatro intervalos anteriores de sete dias, independentemente do agrupamento visual. A semana efetiva do percentual e o instante em que sua revisão foi gravada limitam o que pode entrar na leitura. Semanas sem mudança depois do início das medições contam com avanço zero. A série ponderada mantém seus pesos oficiais por O.S. e usa as posições semanais do próprio recorte.

Com pelo menos duas variações, a média pondera os intervalos em ordem cronológica, de 1 para o mais antigo até 4 para o mais recente. Variações negativas por correção reduzem a velocidade média, cujo piso é zero. A projeção parte do valor e da data do último realizado, avança proporcionalmente aos dias transcorridos e para em 100%. Uma posição já concluída permanece em 100%. As demais séries e os registros históricos são apenas lidos pelo cálculo.
