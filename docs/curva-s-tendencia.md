# Tendência da Curva S

## Fórmula e diagnóstico

A fórmula anterior usava até quatro variações; a versão intermediária ampliou a janela para seis, mas aplicava somente a média ponderada por recência. Com isso, um intervalo recente pequeno podia reduzir toda a velocidade projetada. A fórmula atual combina a velocidade média de todas as variações válidas da janela (70%) com uma média ponderada (30%), usando pesos de 1 à mais antiga até `n` à mais recente e normalizando cada variação pelo número real de dias. A projeção não usa o programado nem a diferença em relação a ele; estar adiantado não acelera a tendência.

Como referência reproduzível para os dados informados da Caldeiraria em 24/11/2026, os valores decimais abaixo são apenas um exemplo compatível com os percentuais exibidos (3%, 6%, 8%, 9%, 9%, 10%) e com a última evolução de 0,43 p.p.; não substituem os valores originais da base:

| Medida | Semana 1 | Semana 2 | Semana 3 | Semana 4 | Semana 5 | Semana 6 |
|---|---:|---:|---:|---:|---:|---:|
| Realizado | 3,20% | 5,70% | 7,80% | 8,80% | 9,09% | 9,52% |
| Variação (p.p.) | — | 2,50 | 2,10 | 1,00 | 0,29 | 0,43 |

Com quatro variações, a fórmula anterior estimaria 0,669 p.p./semana e 10,189% na semana seguinte. A versão intermediária, usando só a média ponderada das cinco variações, estimaria 1,065666... p.p./semana e 10,585666...%. Na fórmula atual, a média histórica das cinco variações é 1,264 p.p./semana e a média ponderada recente é 0,867333...; a combinação estima 1,145 p.p./semana e 10,665% na semana seguinte. Isso reduz o efeito de uma semana fraca sem descartar a evolução recente. Na aplicação, o cálculo usa os valores reais completos, não os percentuais arredondados mostrados na tabela.

## Histórico, corte e projeção

A linha começa na data e no percentual do último realizado e segue a velocidade estimada, limitada a 100%. São necessárias duas variações válidas para desenhar a tendência. A previsão de conclusão é apresentada somente após pelo menos quatro variações válidas e quando a velocidade for positiva; a data é calculada a partir do percentual restante e da velocidade diária estimada.

O histórico usado pelo setor é reconstruído por referência semanal e instante de gravação. Em consultas com data de corte, somente revisões gravadas até 23:59:59 de Brasília nessa data entram no cálculo. Novas medições recalculam a janela e a inclinação, sem alterar programado, realizado, lançamentos ou fechamentos publicados.

As posições são médias simples das O.S. do setor. Em outros recortes ponderados, a série mantém os pesos oficiais existentes; a tendência consome as posições realizadas do próprio recorte. Percentuais negativos de revisão reduzem a velocidade, que tem piso zero.
