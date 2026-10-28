# Curva S automática — modelo histórico

## Apresentação no Controle PCM

Percentuais nos rótulos, tooltips, indicadores e histórico (inclusive simulação) são exibidos como inteiros, com arredondamento apenas na formatação. Por exemplo, 50,74% aparece como 51%. Os valores internos e as coordenadas do gráfico mantêm toda a precisão. Diferenças e variações em pontos percentuais continuam calculadas a partir dos valores originais e exibidas com duas casas decimais; por isso a diferença pode não coincidir com a subtração dos percentuais já arredondados na tela. O modelo histórico e a interpolação permanecem inalterados.

O quadro **Curva S — Setor** reúne total de dias operacionais, barra proporcional verde (corridos) e azul (restantes), indicadores do fechamento, gráfico e histórico de cinco colunas. A barra usa `ProgramacaoAutomatica::position` e o mesmo calendário do programado, até a data atual de Brasília. Fechamentos e diferenças continuam com o corte semanal original; a barra não recalcula o histórico.

Todas as semanas têm número e intervalo com ano no eixo, incluindo semanas parciais e futuras. Calendários longos permitem rolagem horizontal dentro do gráfico. Percentuais semanais usam os valores existentes, com posicionamento visual para evitar sobreposição e detalhes nos pontos. A tabela mostra **Aguardando apuração** quando não há fechamento. Explicações ficam em **Como funciona o cálculo**; avisos de calendário são preservados discretamente.

Não há novas migrations nem mudanças nas fórmulas, na apuração, nos calendários ou no TOTVS. A apresentação está em `templates/element/curva_semanal.php`, `webroot/css/curva-semanal.css` e `webroot/js/curva-semanal-layout.js`; `EvolucaoController::controle` fornece apenas a posição diária para a barra.

Validação visual: `tools/test_curva_semanal_browser.js` usa a prévia isolada do teste HTTP; `tools/test_curva_semanal_long_browser.js` verifica o calendário longo e a virada de ano na prévia de consulta real. Ambos usam `tools/test_apontamentos_batch_browser.ps1`, em 1440, 820 e 390 pixels. As consultas HTTP e a verificação PostgreSQL conferem a preservação de sete tabelas.

## Simulação visual da ELÉTRICA

ADMIN_PCM tem o botão **Simular evolução** no Controle PCM. O acesso usa `?simular=eletrica` e identifica o setor ELÉTRICA na entressafra selecionada, sem ID fixo. Outros perfis recebem HTTP 403 mesmo por URL direta; um setor incompatível recebe HTTP 400. O recurso requer calendário operacional confirmado.

`CurvaSimulacao` transforma uma cópia do relatório em memória, sem conexões, gravações ou sessão. Mantém programado, semanas, datas e barra de dias reais. Gera realizado crescente, limitado a 0–100%, alternando posições acima e abaixo do programado. A quantidade de valores acompanha as semanas reais. Tabela, indicadores, legenda, título da página e gráfico identificam **SIMULAÇÃO — DADOS FICTÍCIOS**. A relação de O.S. e a evolução atual continuam reais.

A tendência reaplica `CurvaSemanal::trend` sem alterar a função: para cada semana, projeta uma semana à frente usando somente as semanas simuladas anteriores. Surge na quarta semana, quando já existem três apurações simuladas. Não utiliza os valores futuros para calcular as projeções anteriores. Em calendários com até três semanas não há tendência suficiente.

**Voltar aos dados reais** remove o parâmetro e consulta novamente o relatório PostgreSQL. Nenhum estado é guardado entre usuários ou páginas; filtros e navegação normal saem da simulação. A resposta simulada usa `Cache-Control: no-store, private`. Não foi criado exportador; capturas e impressão do gráfico mantêm sua identificação de dados fictícios. Não há migrations nem alterações no TOTVS, fechamentos, percentuais ou auditorias.

Testes: `CurvaSimulacaoTest`, teste HTTP de isolamento em `CurvaSemanalTest`, `tools/test_curva_simulacao_browser.js` e consulta local em `tools/verify_curva_semanal_http.php`. Incluem calendários de durações diferentes, comparação com a média móvel original, retorno aos dados reais, HTTP 403, preservação de sete tabelas e desktop/tablet/celular.

## Acesso e regras de uso

Abra `/controle-pcm` e selecione o setor principal. A linha **Programado automático — modelo histórico** é uma referência estimada, não um cronograma aprovado. Não depende de metas dos gestores, peso ou quantidade de O.S., nem dos resultados realizados.

ADMIN_PCM acessa **Calendário e apuração semanal** (`/curva-s/programacao`) para consultar os valores calculados, as regras do calendário, apurar semanas e consultar versões. Os campos de metas semanais foram retirados. A mesma rota foi mantida por compatibilidade; POSTs de metas não geram versões. Metas legadas continuam consultáveis, sem participação no novo cálculo.

## Fórmula do programado

O modelo fornecido pelo usuário é representado pelos 18 pontos:

```text
x = i/17, i de 0 a 17
y = [0, 1, 2, 3, 4, 5, 10, 20, 31, 43, 56, 69, 79, 84, 90, 95, 97, 100]
```

Para cada setor, sejam **D** o total de dias operacionais do seu período e **d** os dias operacionais decorridos até o fim da data de corte. Calcula-se `u = d/D`, limitado a `[0,1]`. A posição inicial, antes de decorrer qualquer dia, é `u=0`; a posição final é `u=1`.

Interpola-se linearmente entre os dois pontos vizinhos do modelo:

```text
t = 17 × u
i = floor(t)
f = t − i
Programado(u) = y[i] + (y[i+1] − y[i]) × f
```

Nos extremos retorna-se exatamente 0% e 100%. Como todos os valores `y` são crescentes, cada segmento é monotônico e não ultrapassa os limites. A forma de S é preservada, sem copiar os mesmos 17 percentuais para setores com durações diferentes. Modelo identificado nos fechamentos como `historico-17-linear-v1`.

O calendário do setor tem prioridade; na ausência dele, usa-se o calendário padrão existente. As datas efetivas são a interseção do período do calendário e da entressafra, mantendo o critério de isolamento já utilizado. Divergências são exibidas; nenhuma data é alterada automaticamente.

Dias da semana e datas excluídas já cadastrados são respeitados. **Sem restrição de dias da semana cadastrada, o calendário é contínuo:** todos os dias entre início e término, descontadas as exceções. Essa regra é identificada na tela e no fechamento; não pressupõe segunda a sexta e não grava uma nova jornada no cadastro. Os calendários locais atualmente possuem datas confirmadas, mas não possuem restrição semanal.

Sem calendário confirmado ou sem dias operacionais válidos, o programado e os indicadores de dias ficam indisponíveis, com a causa exibida. Não se inventa um período. Não há novo cadastro de dias.

São mostrados total de dias operacionais, decorridos até a última semana encerrada e restantes. Todos usam a mesma lista de datas que alimenta o modelo. O gráfico inclui o instante inicial a 0%, todas as datas até o término e 100% no final; feriados e dias não operacionais mantêm o acumulado constante. Não existe arredondamento intermediário; apenas a apresentação usa duas casas decimais.

## Realizado e fechamento semanal

O realizado continua sendo a média simples dos últimos acumulados válidos das O.S. únicas do setor principal. O.S. sem lançamento conta como zero. Lista vazia não gera uma divisão por zero. Quantidades de serviços e pesos não participam do cálculo do programado ou do realizado.

Reutiliza o fechamento existente de terça-feira, considerando terça a segunda no fuso `America/Sao_Paulo`. Na análise de 27/10/2026, a referência é a posição de 26/10/2026 às 23:59:59 em Brasília, ou 27/10/2026 02:59:59 UTC.

A apuração inicial considera tanto a referência efetiva quanto o horário de criação do registro até o corte. Mantém a seleção e o congelamento de O.S. implementados anteriormente. Não recalcula o passado a partir do percentual atual. Não são exigidos novos lançamentos.

Cada novo fechamento registra o programado automático, o realizado, a lista e os registros de origem, datas e quantidade de O.S., além da versão do modelo, pontos de interpolação, calendário, regras de dias, progresso normalizado e dias operacionais usados. Os cálculos internos de novas apurações mantêm a precisão disponível, arredondando somente na exibição.

Repetir uma apuração é idempotente. Correção administrativa exige a versão vigente e uma justificativa, criando uma nova versão e preservando a anterior. A trava de entressafra, índice único e gatilhos imutáveis existentes continuam em uso.

**Linha atual e histórico são separados:** a linha azul representa o modelo estimado com o calendário atual durante todo o período, inclusive semanas futuras. A tabela e os indicadores das semanas consolidadas usam o programado gravado no fechamento. Assim, mudanças no calendário não reescrevem posições anteriores; fechamentos manuais legados são identificados como tais. A administração também permite consultar a referência automática de todas as semanas e a composição das versões antigas.

O realizado verde só aparece nas posições consolidadas já encerradas; semanas futuras nunca recebem projeções como realizado. A evolução semanal é a diferença em pontos percentuais contra a posição apurada na terça-feira imediatamente anterior; sem essa posição anterior, permanece vazia. Diferença de acompanhamento é `realizado − programado do fechamento`, em p.p., com situação adiantado, dentro do programado ou atrasado. Sem comparação válida, não há classificação.

## Tendência

Não foi fornecida uma fórmula de tendência da planilha; aplica-se a alternativa solicitada: média móvel das últimas variações semanais.

- Exige no mínimo três apurações com realizado, isto é, duas variações.
- Usa as três variações mais recentes, quando disponíveis (até quatro posições consolidadas).
- Cada variação é normalizada para sete dias: `v = (realizado atual − realizado anterior) × 7 / dias entre referências`.
- O ritmo é a média aritmética dessas variações.
- Para uma data futura: `tendência = último realizado + ritmo médio × dias futuros / 7`, limitada a 0%–100%.

Não há arredondamento intermediário. A linha laranja é tracejada e identificada como estimativa; não é gravada como realizado e não retroalimenta o programado. Uma ou duas apurações não são suficientes para gerar essa projeção.

## Migrations, dados e execução

**Não foi necessária nova migration para a programação automática.** São reutilizadas as colunas e versões da migration anterior `20261028100000_CurvaSemanalTercas.php`, já aplicada ao ambiente local. Os metadados do modelo são gravados no JSON do fechamento existente. Nenhum cadastro de O.S., apontamento, insumo, lançamento, snapshot histórico ou conexão TOTVS foi modificado por esta mudança. O novo fluxo não consulta o TOTVS.

O comando existente permanece disponível, incluindo a opção de simulação. Substitua os IDs pelos cadastros reais:

```powershell
php bin/cake.php curva_semanal --usuario ID_ADMIN --entressafra ID_ENTRESSAFRA --simular
php bin/cake.php curva_semanal --usuario ID_ADMIN --entressafra ID_ENTRESSAFRA
```

Omitir setor/referência permite recuperar as semanas encerradas ainda não apuradas; `--setor` e `--referencia` restringem a execução. Nenhuma tarefa foi instalada automaticamente no servidor. A geração da referência azul é automática na consulta e não depende do agendamento dos fechamentos.

## Testes

```powershell
$env:CURVA_SEMANAL_PREVIEW='1'
php vendor/phpunit/phpunit/phpunit tests/TestCase/Service/ProgramacaoAutomaticaTest.php tests/TestCase/Service/CurvaSemanalTest.php tests/TestCase/Service/CurvaReorganizacaoTest.php tests/TestCase/Service/CalendariosOrdensTest.php tests/TestCase/Service/EvolucaoTest.php
php tools/verify_curva_semanal_postgres.php
php tools/verify_curva_semanal_http.php
powershell -ExecutionPolicy Bypass -File tools/test_apontamentos_batch_browser.ps1 -Preview curva-semanal-preview.html -TestScript test_curva_semanal_browser.js
```

Foram aprovados 88 testes na regressão do módulo e 63 verificações Chrome em desktop, tablet e celular. Cobertura: nós históricos, monotonicidade, limites, precisão, setores curtos/longos, semanas parciais, exceções, regras contínuas/restritas, extremos 0%/100%, ausência de evolução, média de O.S. em 0%/50%/100%, atualizações históricas, média móvel, idempotência, alteração posterior de calendário, preservação de fechamento legado e ausência de formulário de metas. Verificações HTTP/PostgreSQL comparam hashes de sete tabelas para comprovar preservação dos dados; as escritas de teste do PostgreSQL são revertidas.

## Arquivos desta alteração

- `src/Service/ProgramacaoAutomatica.php` (novo cálculo e normalização do calendário)
- `src/Service/CurvaSemanal.php` (integração, snapshots, dias e média móvel)
- `src/Controller/EvolucaoController.php` (retirada do POST de metas)
- `templates/element/curva_semanal.php` (gráfico, indicadores e histórico)
- `templates/Evolucao/programacao_semanal.php` (consulta automática e administração existente)
- `templates/Evolucao/acompanhamento.php` (nome da ação administrativa)
- `tests/TestCase/Service/ProgramacaoAutomaticaTest.php`
- `tests/TestCase/Service/CurvaSemanalTest.php`
- `tools/test_curva_semanal_browser.js`
- `tools/verify_curva_semanal_postgres.php`
- `tools/verify_curva_semanal_http.php`
- `docs/CURVA_S_AUTOMATICA.md`
- `docs/CURVA_S_SEMANAL.md`
- `README.md`
