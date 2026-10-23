# Apresentação das Ordens de Serviço

A pesquisa e o resumo da O.S. exibem número com zeros à esquerda, descrição real e setor principal da Curva S do caderno. Sem caderno, o rótulo passa a ser **Equipe executante (sem caderno)**, evitando confundir a equipe TOTVS com o setor de contabilização.

A descrição específica cadastrada em `descricao_atividade` tem prioridade. Quando não existe, utiliza-se `descricao_totvs` da consulta ou do snapshot TOTVS. A consulta autorizada obtém esse texto de `STJ010.TJ_OBSERVA`, somente por SELECT. Se a fonte não tiver descrição, aparece **Descrição não informada**; o nome genérico do serviço não é usado para inventar uma atividade. Esta mudança não preenche retroativamente descrições no banco.

Filial e centro de custo foram ocultados na apresentação. As identidades, os campos internos das respostas, os filtros de autorização e a restrição à filial 01 continuam preservados. Os nomes de equipes com prefixo técnico são tratados apenas para exibição. A visualização de auditoria oculta os campos técnicos sem modificar os registros armazenados.

Não houve migration, limpeza, vínculo real de teste ou mudança no cálculo de percentuais e Curva S. Os fluxos de confirmação, transferência, autenticação e CSRF foram mantidos.

## Arquivos principais

- `src/Service/OsApresentacao.php`: descrição, setor principal, equipe sem caderno, opções e auditorias para apresentação. Suporta entidades e registros com associações em arrays.
- `src/Integration/Totvs/OrderQueries.php` e `Orders.php`: leitura e normalização da descrição disponível no TOTVS.
- `src/Service/OperacaoRapida.php`, `OrderAutocomplete.php`, `BulkLinks.php` e `Curva.php`: dados necessários à apresentação, preservando as regras de negócio.
- `src/Controller/ApontamentosController.php`, `OperacaoController.php`, `CadastrosController.php` e `UsuariosController.php`: resumo selecionado e nomes nas opções de equipes.
- `templates/element/os_identificacao.php` e `order_autocomplete.php`: identificação compartilhada e resumo selecionado.
- `templates/element/entries_table.php` e `operation_filters.php`: listagem e filtros compartilhados.
- Templates de `Atividades`, `Apontamentos`, `Organizacao`, `VinculosLote`, `FilaPcm`, `Evolucao` e `Cadastros`: retirada de filial visível e identificação consistente das O.S.
- `webroot/js/order-autocomplete.js` e `bulk-links.js`: resultados de pesquisa, seleção e prévias sem metadados técnicos visíveis.
- `tests/TestCase/Controller/CadernosPrincipalTest.php`: cobertura de descrição, setor principal, dados internos preservados, HTML e associações em arrays.
- `tests/TestCase/Controller/EtapaDoisTest.php` e `PeriodosNavegacaoTest.php`: expectativas das mensagens de interface atualizadas.
- `tools/test_autocomplete_js.js` e `build_js_verification.php`: testes de interface, incluindo a vinculação antiga por serviço.
- `tools/verify_os_apresentacao_http.php`: conferência HTTP local com comparação das tabelas antes e depois.

## Validação realizada

- PHPUnit completo: **237 testes, 1.847 assertions**, todos aprovados. Base isolada de testes.
- JavaScript executado no Edge: pesquisa, teclado, seleção, limpeza, concorrência de respostas, porcentagem, evolução, continuação de listas e confirmação de vínculos em lote aprovados.
- HTTP no sistema local: **7 telas**, **1 resultado de consulta real ao TOTVS** e hashes das **21 tabelas** do PostgreSQL idênticos antes e depois. Nenhuma gravação de teste na base operacional.
- HTML renderizado conferido; resumo selecionado também inspecionado visualmente.

Sistema local: `http://localhost:8766`. Para conferir, abra **Lançar porcentagem** ou **Novo apontamento**, pesquise uma O.S. e selecione o resultado. A identificação usa os dados disponíveis, sem inventar descrições ausentes.
