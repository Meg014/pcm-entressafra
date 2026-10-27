# Central PCM: horas e insumos por O.S.

## Utilização

**Apontamentos recebidos** (`/fila-pcm`) é a central de recebimento do PCM. A fila agrupa por O.S. e apresenta contagens independentes de apontamentos e de insumos pendentes. Uma O.S. aparece quando qualquer uma das categorias tem pendência. Os totais de horas não incluem quantidades de materiais. A ordem de recebimento considera o registro pendente mais antigo entre ambas as categorias.

**Ver registros** abre `/fila-pcm/os/{id}`. O cabeçalho mostra número da O.S., setor da Curva S, descrição, equipamento e percentual atual de conclusão. A página contém:

1. **Apontamentos de horas**: seleção múltipla, funcionários, datas, início/fim, horas, serviço e situação. ADMIN_PCM pode abrir **Editar hora** nos apontamentos ainda não confirmados; validações e auditoria existentes continuam ativas. Apontamentos confirmados exigem reabertura antes de alterar horas.
2. **Insumos utilizados**: seleção múltipla, funcionário, data, material, quantidade e situação.
3. **Histórico de recebimento e tratamento**: eventos individuais de recebimento, confirmação, correção e reabertura, com usuário, data, motivo, antes e depois.

Cada categoria tem formulário, seleção, consentimento e botão próprios. Confirmar horas não altera insumos; confirmar insumos não altera horas. A reabertura exige justificativa. Os dados confirmados permanecem consultáveis na mesma página; quando não há pendências em nenhuma categoria, a O.S. sai da fila, mas continua acessível por seu endereço e pelo cadastro de atividades. A listagem de atividades permanece acessível no topo da fila.

Horas, insumos e histórico têm paginação independente. Campos de revisão bloqueiam confirmação sobre uma versão desatualizada. Cada lote de tratamento é transacional: um registro inválido impede a alteração dos demais selecionados daquela categoria. O sistema registra a confirmação do trabalho manual, sem escrever no TOTVS.

O cadastro em planilha continua em `/insumos/add`. Insumos novos entram automaticamente na fila como pendentes. A consulta `/insumos` permanece disponível como consulta adicional; o tratamento acontece na central por O.S.

## Permissões e isolamento

A fila, os detalhes e as confirmações são exclusivos de ADMIN_PCM e PCM. Funcionários operacionais continuam com o cadastro e a consulta de insumos nos setores autorizados. O contexto da entressafra da sessão é conferido na leitura e na gravação. Seleções de outra O.S. são recusadas e uma entressafra encerrada permanece somente para consulta. Nenhum vínculo com O.S., registro de horas, insumo, quantidade ou identidade é reescrito por uma confirmação.

## Filtros da fila

`/fila-pcm` possui filtros GET por Setor e Evolução da O.S., com botões Filtrar e Limpar filtros. Os padrões são Todos os setores e Todas as porcentagens. A seleção de setor acompanha a coluna exibida: classificação principal do caderno; para O.S. sem caderno, a equipe executante aparece como opção identificada com “sem caderno”. As opções respeitam a entressafra selecionada.

As faixas são 0% (inclui ausência de evolução), em andamento (maior que zero e menor que 100) e 100% — Concluídas. A consulta usa `OrderProgress::status`, a mesma fonte da evolução já exibida na fila, antes de contar e paginar os resultados. A situação de encerramento no TOTVS não define a conclusão. O.S. com 100% continuam aparecendo enquanto houver pendências de horas ou insumos.

Os parâmetros `setor` e `evolucao` permanecem nos links da paginação; uma nova consulta ou limpeza retorna à primeira página. Filtros inválidos são recusados. Nenhuma alteração de schema ou gravação de evolução, apontamentos, insumos ou TOTVS é realizada.

Validação dos filtros: 12 testes da fila e 175 asserções aprovados; 47 verificações Chrome entre desktop, tablet e celular; 32 combinações de filtros e totais conferidas no PostgreSQL local em transação somente leitura.

```powershell
$env:FILA_FILTER_PREVIEW='1'
php vendor/phpunit/phpunit/phpunit tests/TestCase/Controller/FilaPcmUnificadaTest.php
powershell -ExecutionPolicy Bypass -File tools/test_apontamentos_batch_browser.ps1 -Preview fila-filtros-preview.html -TestScript test_fila_filters_browser.js
```

## Descrição real da O.S.

O Novo apontamento lê `STJ010.TJ_OBSERVA AS descricao_totvs` pelas consultas `OrderQueries`, com conversão de encoding em `Orders::mapRow`. A fila e o detalhe usavam apenas a descrição complementar local e o snapshot do vínculo; os vínculos antigos das O.S. 005316, 005525 e 005897 não continham esse campo, causando “Descrição não informada”.

`OrderDescriptions` agora completa a apresentação da página com `ListOrders`, reutilizando `ListOrderQueries::numbers`, a conexão `protheus` existente e o mesmo mapeamento do autocomplete. Os números permanecem strings com zeros à esquerda, agrupados por filial em blocos de até 100. Somente as O.S. da página são consultadas; identidades ausentes, divergentes ou ambíguas são descartadas. A consulta histórica inclui O.S. encerradas.

A descrição complementar local mantém prioridade e não exige consulta remota. Sem uma descrição remota válida, permanece a apresentação local/snapshot existente. Uma falha do TOTVS não bloqueia a consulta dos apontamentos e insumos. A fila e “Ver registros” recebem a mesma apresentação, sem alterar colunas, equipamento, setor, evolução ou confirmações. Nenhum vínculo ou snapshot é atualizado pela consulta; não há migration.

Conferência real em modo leitura: 005316 retornou **MANUTENÇÃO CORRETIVA ILUMINAÇÃO**, 005525 retornou **DESMONTAGEM E MONTAGEM CORRENTE E TALISCAS DA ESTEIRA METALICA** e 005897 retornou **DESMONTAGEM 4 T**. A fila e os três detalhes foram validados por HTTP no servidor local; hashes das tabelas de atividades, apontamentos, insumos, evolução e auditorias permaneceram iguais.

Testes automatizados cobrem consulta em lote, filtragem antes do enriquecimento, complemento local, snapshots históricos, ausência, ambiguidade, filial divergente, resposta vazia e indisponibilidade do TOTVS, além das permissões e dos filtros existentes.

```powershell
php vendor/phpunit/phpunit/phpunit tests/TestCase/Controller/FilaPcmUnificadaTest.php tests/TestCase/Integration/Totvs/ListOrdersTest.php tests/TestCase/Integration/Totvs/OrdersTest.php tests/TestCase/Integration/Totvs/QueriesTest.php
```

## Migration aditiva

`20261026130000_AddControlePcmInsumos.php` adiciona:

- Controle PCM, revisão, usuário/data de confirmação e usuário/data de alteração em `insumos`.
- `insumo_id` opcional em `auditorias`, com índice e chave estrangeira.
- Ligação das auditorias antigas de recebimento ao insumo identificado no JSON já existente, sem substituir o conteúdo histórico.

Insumos antigos começam pendentes, com revisão zero. A migration não apaga registros ou colunas. A reversão automática é bloqueada para preservar as confirmações e os históricos. A migration anterior de criação de insumos precisa estar aplicada antes desta.

## Implantação manual em produção

Não foi adicionada execução de migrations ao startup da aplicação, às requisições HTTP ou a um deploy automático. Em produção, um responsável deve executar explicitamente os passos abaixo após backup e validação do ambiente correto:

```powershell
# Na pasta do projeto e com a configuração do ambiente de destino já conferida:
php tools/validate_database.php
php bin/cake.php migrations status --connection default
php bin/cake.php migrations migrate --connection default --target 20261026130000
php bin/cake.php schema_cache clear --connection default
```

A conexão default deve continuar apontando exclusivamente para `pcm_controle_entressafra`. Se o status listar outras migrations pendentes, revisá-las antes do comando de migração. Limpar o cache de metadados é necessário para o ORM reconhecer os novos campos. Não executar rollback automático; em caso de falha de implantação, manter os dados e corrigir a aplicação/migration por procedimento controlado.

## Testes

```powershell
php vendor/phpunit/phpunit/phpunit
php vendor/phpunit/phpunit/phpunit tests/TestCase/Controller/FilaPcmUnificadaTest.php tests/TestCase/Service/InsumosControleMigrationTest.php
$env:FILA_PREVIEW='1'
php vendor/phpunit/phpunit/phpunit --filter testUnifiedHttpPageHasIndependentFormsAndAdminOnlyEditHour
powershell -ExecutionPolicy Bypass -File tools/test_apontamentos_batch_browser.ps1 -Preview fila-unificada-preview.html -TestScript test_fila_pcm_browser.js
```

PHPUnit usa SQLite isolado; executar suas instâncias sequencialmente. A migration também é conferida com registros legados e auditorias em SQLite em memória, verificando preservação dos valores e o vínculo de histórico. Os testes de navegador usam uma prévia fictícia autenticada, sem confirmar registros no banco operacional.
## Resultado desta entrega

- Regressão completa: 275 testes e 2.272 asserções aprovados.
- Conferência final da central e da migration legada: 7 testes e 99 asserções aprovados.
- Chrome: 54 verificações aprovadas, distribuídas entre computador, tablet e celular.
- Sintaxe PHP e `git diff --check`: aprovados.
- Migration aplicada somente ao ambiente local verificado: servidor `127.0.0.1`, banco `pcm_controle_entressafra`. Consulta da fila também validada no PostgreSQL local, sem criar registros ou confirmar lançamentos operacionais.
- Nenhuma migration executada em produção. Implantação de produção permanece manual pelo roteiro acima.

Arquivos principais: `src/Service/FilaPcm.php`, `src/Controller/FilaPcmController.php`, `templates/FilaPcm/{index,view}.php`, elementos `pcm_confirmation.php` e `pcm_section_pagination.php`, `src/Service/Access.php`, `src/Service/Insumos.php` e a migration `AddControlePcmInsumos`. Os testes e o bootstrap de testes foram atualizados para o novo vínculo de auditoria e para leitura dos metadados após migrations.
