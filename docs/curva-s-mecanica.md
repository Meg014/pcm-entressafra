# Curvas S da Mecânica — lista manual de Bombas

A regra definitiva é: **Bombas** contém somente as O.S. relacionadas manualmente pelo ADMIN_PCM; **Redutores** contém as demais O.S. programadas da Mecânica; **Mecânica — Geral** contém todas as programadas. Os grupos são disjuntos. As opções aparecem nessa ordem, preservando Geral como opção inicial do consolidado.

A ação **Gerenciar O.S. de Bombas** permite pesquisar número/filial, selecionar várias O.S., colar números separados por linha, vírgula ou ponto e vírgula e remover O.S. ao desmarcar. Adicionar em Bombas retira de Redutores; remover devolve a Redutores. Números permanecem como texto, com zeros à esquerda. Ambiguidades exigem filial/número ou seleção da linha correta. Toda a lista é validada antes de gravar, sem salvamento parcial.

Cada grupo mantém a média simples de todas as suas O.S., inclusive as que estão em 0%. A Geral usa todas as O.S., sem calcular a média das médias dos grupos. Corretivas e O.S. de outros setores ou entressafras não são aceitas. As curvas compartilham o calendário operacional da Mecânica e mantêm períodos de 15 dias em 2026, semanas em 2027, corte histórico e atualização de 30 segundos.

## Inspeção dos dados e migration

Antes da alteração, a inspeção do PostgreSQL local confirmou que a tabela `mecanica_curva_versoes` ainda não existia. Portanto, não há listas antigas nesse banco para converter. A implementação anterior, contudo, já existe no código e pode estar aplicada em outros ambientes.

Nova migration: **20261110130000_MecanicaListaManualBombas**. Acrescenta `grupo_manual` na tabela existente, sem criar outra tabela. O padrão `redutores` identifica corretamente as versões antigas; todo novo salvamento informa explicitamente `bombas`. O PostgreSQL restringe os valores aos dois grupos.

A migration não inverte listas, não cria classificações nem altera conteúdo, responsável ou data das versões existentes. O trigger de imutabilidade continua ativo. A validação PostgreSQL aplicou a migration a registros legados em um esquema isolado, conferiu que somente o metadado foi acrescentado e que todos os campos anteriores permaneceram idênticos.

Em instalações novas, são necessárias a migration de criação `20261110120000_CreateMecanicaCurvaVersoes` e a nova migration de identificação da regra. No banco local, continuam pendentes também as migrations anteriores identificadas na implementação original: `CreateServicosSugeridos`, `AddSessaoPermanenteComputador` e `AddTipoPlanejamento`. Não foram aplicadas alterações no esquema da aplicação durante este ajuste. O comando padrão `php bin/cake.php migrations migrate` aplica também essas pendências e deve fazer parte da implantação revisada.

## Compatibilidade com listas antigas

Versões antigas continuam significando **Redutores selecionados**; Bombas é seu complemento. As consultas históricas usam o significado da versão persistida até 23:59:59 de Brasília, sem reinterpretar uma lista antiga como Bombas.

Ao abrir o gerenciamento com uma última versão legada, as O.S. que já pertencem a Bombas (complemento da lista antiga) aparecem pré-selecionadas, com aviso. Abrir a tela não grava nada. Ao salvar, o administrador cria uma nova versão explicitamente de Bombas, preservando os grupos anteriores caso mantenha a seleção. Alterações posteriores seguem exclusivamente a regra nova. Sem nenhuma versão registrada, Bombas começa vazio e todas as programadas ficam em Redutores.

O histórico administrativo identifica cada lista como Bombas ou Redutores (legado). Auditorias antigas permanecem intactas; as novas usam `mecanica_bombas_classificacao` e registram o significado das versões anterior e posterior. O controle de concorrência e a transação entre classificação e auditoria permanecem.

A URL antiga de gerenciamento aceita somente GET e redireciona para a nova. POST de formulário antigo é rejeitado. A tela nova também exige `grupo_manual=bombas`, impedindo que uma seleção antiga seja salva acidentalmente com significado invertido.

A limitação anterior de setor principal, tipo de planejamento, vínculo com caderno e calendário operacional permanece: esses cadastros não têm versionamento por este ajuste. A classificação Bombas/Redutores e os percentuais possuem histórico próprio.

## Arquivos alterados

- `config/Migrations/20261110130000_MecanicaListaManualBombas.php`: metadado da regra e compatibilidade com registros antigos.
- `src/Service/CurvaMecanica.php` e `src/Service/Curva.php`: lista manual de Bombas, complemento e leitura de versões antigas.
- `src/Controller/EvolucaoController.php` e `config/routes.php`: gerenciamento de Bombas e bloqueio de formulários antigos.
- `templates/Evolucao/bombas.php`: renomeado de `redutores.php`, com rótulos e histórico por regra.
- `templates/element/curva_semanal.php`: seleção e explicação das curvas.
- `webroot/js/mecanica-bombas.js`: renomeado de `mecanica-redutores.js`, com busca e seleção de Bombas.
- `tests/TestCase/Service/CurvaSemanalTest.php`: inclusão, remoção, médias, corretivas, histórico, legado e formulários antigos.
- `tools/test_mecanica_bombas_browser.js`: renomeado do teste de Redutores.
- `tools/verify_mecanica_migration_postgres.php`: upgrade com registros antigos e preservação do histórico.
- Este documento.

Nenhum arquivo da tela Lançar porcentagem, de salvamento dos percentuais ou da integração TOTVS foi modificado.

## Validação

Resultado final: **111 testes aprovados e 1.855 asserções**. PostgreSQL isolado aprovado, incluindo upgrade de registros legados sem reinterpretá-los. Chrome headless aprovado para gerenciamento de Bombas em tela de 390 px e atualização com filtros. Sintaxe PHP e `git diff --check` sem erros.

- `php vendor/phpunit/phpunit/phpunit tests/TestCase/Service --filter 'Curva|Evolucao|Planejamento' --no-progress`
- `php tools/verify_mecanica_migration_postgres.php`
- Chrome headless: busca, seleção múltipla, filtro de selecionados, contagens e envio completo em JSON.
- Chrome headless: atualização de 30 segundos preservando setor, grupo, corte e exibição.
- Sintaxe PHP e `git diff --check`.
