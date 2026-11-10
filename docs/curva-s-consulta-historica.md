# Curva S: acompanhamento e corte histórico

Os lançamentos permanecem semanais. A exibição automática usa períodos consecutivos de 15 dias em 2026, interrompidos em 31/12, e semanas de terça a segunda a partir de 01/01/2027. Primeiro e último períodos podem ser parciais. O acumulado de cada ponto é consultado no fim do período, limitado à data consultada; percentuais não são somados. A evolução do indicador compara a posição consultada com sete dias antes, independentemente da exibição.

O calendário confirmado do setor, limitado à entressafra, define os extremos do gráfico. Sem calendário confirmado, a advertência existente solicita sua configuração e mantém o período da entressafra como referência. Um setor futuro informa sua data prevista; nenhum realizado futuro é exibido.

## Histórico e migrations

Não é necessária migration para reconstruir os percentuais: `evolucao_registros` já preserva as revisões, o vínculo com `evolucao_semanas.referencia`, o número da revisão e `created` em UTC. `Evolucao::saveBatch()` insere uma nova revisão e audita cada alteração, em vez de substituir registros anteriores. A consulta filtra simultaneamente referência e persistência, com corte inclusivo às 23:59:59 de America/Sao_Paulo convertido para UTC. Uma revisão persistida na terça-feira não altera o corte da segunda-feira, mesmo com referência retroativa. Fechamentos publicados não são regravados nem usados como substituto desse histórico na consulta com corte.

Limitação: classificação principal e tipo de planejamento das O.S., vínculo com caderno e calendário operacional são os cadastros atuais. O conjunto de O.S. é limitado à criação até o corte, mas mudanças posteriores de classificação ou calendário podem alterar o escopo ou o programado. A tela informa essa limitação. Registros importados sem a revisão original ou com timestamps incorretos não permitem recuperar conhecimento que nunca foi registrado.

Para reproduzir integralmente apresentações após alterações nesses cadastros, o ajuste necessário é versionar também os calendários e o escopo das O.S. com vigência e persistência, ou criar apresentações imutáveis contendo esses dados e os IDs das revisões consideradas. Isso exige uma migration adicional e não recupera retroativamente estados ausentes. Esta implementação não inventa versões nem modifica registros existentes.

Os filtros `corte` e `exibicao` ficam na URL. A atualização existente a cada 30 segundos consulta essa mesma URL e substitui a Curva S, preservando os filtros aplicados. A relação de O.S. e seu resumo externo permanecem explicitamente identificados como atuais.

## Arquivos alterados

- `src/Service/CurvaSemanal.php`: períodos de exibição, limites do setor, corte e indicadores.
- `src/Controller/EvolucaoController.php`: leitura e validação dos filtros.
- `templates/element/curva_semanal.php`: controles, avisos e períodos no gráfico/tabela.
- `templates/Evolucao/acompanhamento.php`: manutenção dos filtros ao trocar o setor e identificação dos dados atuais.
- `webroot/css/curva-semanal.css`: aparência dos controles.
- `tests/TestCase/Service/CurvaSemanalTest.php`: novos cenários e adequação das expectativas para períodos parciais e limites do setor.
- `tools/test_curva_filtros_refresh.html` e `tools/test_curva_filtros_refresh.js`: teste de atualização no navegador.
- `docs/curva-s-consulta-historica.md`: regras, validação e limitações.

Nenhuma migration criada ou necessária para esta implementação.

## Validação

Resultado: 20 testes da Curva S aprovados, com 194 asserções, na execução final. Os outros 80 testes da regressão ampliada de Curva/Evolução também passaram. A execução ampliada identificou um campo obrigatório ausente em um novo registro de teste; o fixture foi corrigido e os 20 testes do módulo foram executados novamente. Chrome headless aprovou a atualização com filtros. Sintaxe PHP e `git diff --check` sem erros.

- `php vendor/phpunit/phpunit/phpunit tests/TestCase/Service --filter 'Curva|Evolucao|Planejamento' --no-progress`
- Abrir `tools/test_curva_filtros_refresh.html?area_id=7&corte=2026-11-09&exibicao=automatica` no Chrome (também validado em modo headless).
- Verificação de sintaxe PHP e `git diff --check`.

Os testes de serviço cobrem períodos consecutivos, mudança de ano, corte em 09/11/2026, revisões posteriores, limite de meia-noite em Brasília, período parcial, modo dinâmico, início em 10/01/2027, setor futuro, calendário concluído, exclusão de corretivas, equivalência dos acumulados, filtros HTTP e preservação dos fechamentos. O teste JavaScript usa o código real de atualização com respostas simuladas para verificar intervalo, URL, rolagem e isolamento da simulação.
