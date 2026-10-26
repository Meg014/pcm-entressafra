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