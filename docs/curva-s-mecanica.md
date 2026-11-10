# Curvas S da Mecânica

Em Controle PCM / Curva S, MECÂNICA apresenta Redutores, Bombas / Demais e Mecânica Geral. A Geral continua como opção inicial, preservando o consolidado anterior. Outros setores não recebem esse controle.

Somente ADMIN_PCM encontra **Gerenciar O.S. de Redutores**. A tela permite pesquisar número/filial, marcar várias O.S., exibir somente as selecionadas, colar números separados por linha, vírgula ou ponto e vírgula, desmarcar para remover e salvar. O histórico mostra responsável, horário em Brasília e identidades de cada versão.

O administrador controla apenas Redutores. Bombas / Demais é o complemento entre as O.S. programadas da Mecânica na entressafra. Nenhuma descrição ou equipamento do TOTVS determina o grupo. Identidades persistidas incluem entressafra, filial e `os_chave`, preservando o número como texto com zeros à esquerda. Números ambíguos exigem `filial/número` ou seleção da linha correta. Qualquer número inválido impede a alteração inteira.

Cada curva usa a média simples de todas as O.S. do grupo, inclusive aquelas em 0%. A Geral usa todas as programadas; não usa a média não ponderada das duas médias parciais. Corretivas permanecem excluídas. As duas curvas usam o calendário operacional da Mecânica, portanto o Programado automático pode coincidir. Realizado, Diferença, Evolução e Tendência usam o grupo selecionado. Fechamentos publicados da Mecânica Geral não são reutilizados como se fossem fechamentos dos grupos.

## Persistência e histórico

Migration nova: `config/Migrations/20261110120000_CreateMecanicaCurvaVersoes.php`.

Cria somente `mecanica_curva_versoes`, com versões completas da lista, entressafra, setor, usuário, nome do responsável e persistência em UTC. No PostgreSQL, um trigger bloqueia UPDATE e DELETE do histórico. Classificação e auditoria são gravadas na mesma transação. O bloqueio da entressafra e a versão-base rejeitam alterações concorrentes obsoletas. Uma seleção idêntica não cria outra versão.

Com corte, a última versão persistida até 23:59:59 de America/Sao_Paulo define a divisão dos grupos. Alterações posteriores não modificam essa divisão histórica. Antes da primeira versão, Redutores está vazio. Os percentuais continuam considerando referência semanal e persistência de cada revisão.

A limitação anterior sobre alterações de **setor principal, tipo de planejamento, vínculo com caderno e calendário operacional** permanece: esses cadastros não foram versionados por esta alteração. A divisão Redutores / Bombas tem histórico próprio; não resolve alterações posteriores nesses outros cadastros.

Grupo, corte e exibição permanecem na URL usada pela atualização de 30 segundos. A pesquisa das O.S. preserva esses filtros. As quantidades identificadas como “classificação atual” mostram a lista atual; o gráfico com corte usa a divisão histórica.

## Banco local e implantação

A migration foi validada em esquema isolado no PostgreSQL, removido ao final, com conferência do conteúdo de sete tabelas existentes antes e depois. Nenhum cadastro ou lançamento foi alterado no banco da aplicação.

O PostgreSQL local também apresenta estas migrations anteriores pendentes:

- `20261026140000_CreateServicosSugeridos`
- `20261026150000_AddSessaoPermanenteComputador`
- `20261106100000_AddTipoPlanejamento`

A coluna `atividades.tipo_planejamento` ainda está ausente nesse banco e é uma dependência do filtro de programadas já existente. Essas pendências e a migration nova precisam ser aplicadas antes do uso nesse ambiente. Não foram aplicadas automaticamente para evitar mudanças nos outros módulos neste trabalho.

O comando padrão é `php bin/cake.php migrations migrate`. Ele aplica também as migrations anteriores pendentes, que devem integrar a revisão da implantação. A nova migration não modifica registros existentes e bloqueia rollback destrutivo do histórico.

## Arquivos

- `src/Service/CurvaMecanica.php`: classificação, complemento, versões e auditoria.
- `src/Service/Curva.php` e `src/Service/CurvaSemanal.php`: escopo e cálculo dos grupos.
- `src/Controller/EvolucaoController.php` e `config/routes.php`: consulta e administração.
- `templates/Evolucao/redutores.php`, `templates/Evolucao/acompanhamento.php` e `templates/element/curva_semanal.php`: gerenciamento e seleção.
- `webroot/js/mecanica-redutores.js` e `webroot/css/curva-semanal.css`: busca local, seleção integral por JSON e apresentação.
- `tests/TestCase/Service/CurvaSemanalTest.php`: regras, histórico, permissões, identidade e HTTP.
- `tools/verify_mecanica_migration_postgres.php`: migration e imutabilidade no PostgreSQL isolado.
- `tools/test_mecanica_redutores_browser.js` e `tools/test_curva_filtros_refresh.js`: testes de navegador.
- A migration e este documento.

Nenhum arquivo da tela Lançar porcentagem, de seu salvamento ou da integração TOTVS foi modificado.

## Validação

Resultado final: **109 testes aprovados, 1.833 asserções**. Verificação PostgreSQL e testes Chrome headless aprovados, inclusive gerenciamento em tela de 390 px. Sintaxe PHP e `git diff --check` sem erros.

- `php vendor/phpunit/phpunit/phpunit tests/TestCase/Service --filter 'Curva|Evolucao|Planejamento' --no-progress`
- `php tools/verify_mecanica_migration_postgres.php`
- Chrome headless: busca, seleção múltipla, filtro de selecionados, contagens e envio JSON completo, sem truncamento por `max_input_vars`.
- Chrome headless: atualização de 30 segundos com setor, grupo, corte e modo de exibição.
- Sintaxe PHP e `git diff --check`.
