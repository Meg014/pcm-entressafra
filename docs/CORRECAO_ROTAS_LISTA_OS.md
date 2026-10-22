# Correção da paginação da vinculação por lista

## Causa e efeito

A rota registrada é `/cadernos/{id}/vincular-lista`, com controlador `Organizacao`, ação `lista` e argumento posicional obrigatório `{id}`. Não existe uma rota genérica de Organizacao que aceite a ação sem o caderno. As rotas restritas existentes foram preservadas.

O elemento compartilhado `simple_pagination.php` usava apenas `['?' => ...]`. O CakePHP recuperava controlador e ação, mas não o argumento posicional do caderno. Com mais de dez itens, gerar Próxima/Anterior causava `MissingRouteException`. O número do período injetado na query pela sessão não substitui o ID da rota.

O processamento da conferência é transacional e ocorre antes do redirecionamento. Por isso o lote podia ter sido preparado no banco, mas o GET seguinte falhava ao renderizar a paginação, impedindo o usuário e o JavaScript de prosseguir pela tela. Na verificação real, as três prévias recentes de 45 O.S. do caderno 23 já estavam `pronto`, cursor 45, com 44 itens `mesmo` e um `nao_encontrada`.

## Correção

A paginação agora informa controlador, ação e todos os argumentos posicionais da requisição, preservando a query, incluindo token, período e filtros. A página de destino substitui somente `pagina`. Isso também preserva os IDs dos históricos que reutilizam o elemento. Nenhuma rota genérica foi adicionada.

Os formulários de continuação usam o endereço atual, com token e caderno, e redirecionam explicitamente para a rota real. O JavaScript existente envia esse formulário com `requestSubmit()`, preservando CSRF; não havia endpoint AJAX alternativo incorreto. A continuação automática permanece ativa em lotes em preparação/processamento; após falha fica disponível a retomada manual. Um sinalizador impede a corrida entre clique manual, clique repetido e temporizador automático.

A tela mostra progresso de conferência pelo cursor persistido, totais por categoria e progresso de gravação dos itens selecionados. Consultas continuam em blocos de até 100 e gravações confirmadas em blocos de até 20. Os locks PostgreSQL e a revalidação de cada item foram preservados.

A lista original é armazenada para novas prévias; prévias antigas mantêm seus números normalizados já persistidos. O campo conserva o conteúdo após erro de validação. A prévia tem uma área somente leitura com os números, inclusive zeros à esquerda. Tokens inválidos geram mensagem recuperável, sem erro 500. A tela inicial oferece as prévias recentes da própria conta e do próprio caderno, sem reiniciá-las.

## Retomar os 45 números

1. Acessar com a mesma conta PCM que criou a prévia e selecionar a entressafra 2026/2027.
2. Abrir http://localhost:8766/cadernos/23/vincular-lista.
3. Em **Retomar prévias deste caderno**, abrir a prévia mais recente de **45 O.S. — pronto**. Também funciona atualizar a URL original que contém `?token=...`.
4. Navegar pelas cinco páginas; não é necessário colar novamente ou iniciar outro lote. Se algum lote estiver em preparação, a continuação automática retoma do cursor salvo.

As consultas reais aos 45 números encontraram 44 no TOTVS filial 01; os 44 já estavam vinculados ao próprio CALDEIRA. `062200` não foi encontrada nessa filial. O valor permanece exatamente como enviado; não se determinou qual seria o número pretendido, que exige conferir a fonte original. Não houve correção automática ou confirmação de vínculo.

## Arquivos

- `templates/element/simple_pagination.php`: preservação dos argumentos obrigatórios e query.
- `src/Controller/OrganizacaoController.php`: recuperação de token inválido e acesso às prévias salvas.
- `src/Service/ListLinks.php`: preservação do texto original e contagem de progresso dos itens selecionados.
- `templates/Organizacao/lista.php`: progresso, lista preservada e retomada.
- `webroot/js/lista-os.js`: submissão única e continuação automática/manual.
- `tests/TestCase/Service/CurvaReorganizacaoTest.php`: regressões com HTML, URLs, tokens e trabalhadores intercalados.
- `tools/verify_lista_routes_http.php`: navegação HTTP na prévia real, consulta aos 45 números, teste de bloqueio PostgreSQL e hashes de integridade.
- `tools/test_lista_os_js.js` e `tools/build_js_verification.php`: teste no navegador da continuação e proteção contra duplo clique.
- Este documento.

## Testes

Suíte completa aprovada: **230 testes, 1.817 asserções**, com relatório em `tmp/lista-rotas-junit.xml`.

O teste de regressão cria listas isoladas de 1, 10, 45 e 105 números com fonte simulada. Valida o HTML de todas as páginas, segue os parâmetros gerados, verifica 10 registros por página, token em links e formulários, contexto da sessão, reload sem reiniciar, lista original e ausência de vínculo antes da confirmação. Outros testes cobrem token inválido/inexistente, erro de entrada com texto preservado, seleção/quantidade/transferência explícitas, duplicidades e retomada após falha de fonte.

Uma confirmação repetida e dois trabalhadores com execuções intercaladas foram testados com fonte simulada e atividades apenas na base isolada de testes: dez vínculos, dez auditorias individuais e uma confirmação, sem duplicidade. Na base real, duas conexões PostgreSQL testaram o mesmo `SELECT ... FOR UPDATE` usado pelo serviço: a segunda aguardou e atingiu o timeout enquanto o lock estava retido, e conseguiu acesso após liberação; ambas as transações foram revertidas sem escrita.

O verificador HTTP real abriu a prévia salva de 45 números, percorreu e seguiu os links das cinco páginas, verificou retomada/reload e comparou hashes de 19 tabelas antes/depois. Nenhum registro operacional foi criado, alterado ou excluído. As consultas ao TOTVS foram somente leitura. O harness JavaScript executado no Edge passou para continuação automática, pausa após falha, clique manual antes do temporizador e submissões repetidas.
