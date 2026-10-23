# O.S. sem Caderno

Rota: `/os-sem-caderno`. A opção fica no menu do usuário, em **Cadastros administrativos**, exclusivamente para `ADMIN_PCM`. A entressafra vem da sessão; conta, período e token são verificados em todas as operações.

## Uso

1. Clique em **Consultar O.S. no TOTVS**. Os blocos de até 200 O.S. são consultados automaticamente. Se houver falha, clique em **Continuar processamento**. O token, o cursor e a consulta permanecem salvos.
2. Pesquise por número ou filtre serviço, equipe e situação. A tabela tem dez registros por página e mostra o total filtrado e a quantidade selecionada em todas as páginas.
3. Marque uma ou várias O.S. A seleção é salva sem recarregar a página. O checkbox do cabeçalho marca a página; **Selecionar todos os resultados filtrados** inclui as outras páginas e preserva a seleção anterior. **Limpar seleção** limpa todas as páginas. Sem JavaScript, **Salvar seleção da página** continua disponível.
4. Escolha o caderno e clique em **Vincular selecionadas**. Confira quantidade e destino e clique em **Confirmar vínculos**.
5. O processamento segue automaticamente, com revalidação. A tela permanece em **O.S. sem Caderno**, com resultado e pendências. Os vínculos bem-sucedidos desaparecem da lista. Para nova seleção após concluir o lote, utilize **Atualizar consulta no TOTVS**.

Uma falha ao salvar a seleção mantém as marcações na página e permite tentar novamente. A navegação aguarda a seleção ser salva. Alterar a seleção invalida o resumo anterior; uma confirmação antiga não autoriza uma nova seleção.

## Identificação e integridade

- A consulta usa `STJ010.TJ_ORDEM`, família 010, identificação interna da filial 01 e os mesmos joins e critérios de `OrderQueries::OPEN`/`Orders::available` já utilizados. Os critérios atuais são origem em 2026, situação L, término N e serviço identificado inequivocamente com ENTRESSAFRA na descrição. Não foi criada uma lista limitada aos códigos de serviço mais recentes: códigos antigos válidos entram pelos mesmos critérios reais.
- Os filtros de serviço e equipe são montados a partir da população retornada pelo TOTVS. O filtro de situação respeita a população elegível atual; não amplia a consulta para ordens encerradas ou manutenção genérica.
- A comparação com o PostgreSQL utiliza entressafra, número e identificação interna da filial. Uma atividade sem caderno continua pendente; um vínculo em outra entressafra não a oculta. Um vínculo existente não é removido nem descontado dos históricos quando a situação TOTVS muda.
- A descrição local específica tem prioridade sobre `TJ_OBSERVA`. A ausência de descrição é informada explicitamente. Descrições longas podem ser expandidas na tabela. Filial e centro de custo não aparecem na interface.
- A consulta e a seleção ficam no servidor em `pendencias_consultas` e `pendencias_ordens`; somente a página atual é enviada ao navegador. A paginação no TOTVS usa cursor, sem limite artificial de 50 resultados.
- A confirmação divide seleções maiores em lotes de até 2.000 números e reutiliza `ListLinks`: preparação em blocos de 100, revalidação e gravação em blocos de 20, transações, bloqueios e auditoria existentes.
- A confirmação tem identificador próprio e é idempotente. Antes de confirmar, uma O.S. que já tenha sido vinculada exige revisão do resumo. Se o vínculo mudar depois da confirmação, a revalidação impede transferência automática ou duplicação. Itens indisponíveis e conflitos aparecem no resultado, com links para o detalhamento dos lotes.
- O setor de apresentação vem do caderno; a equipe original permanece preservada. O fluxo simplificado existente em Cadernos não foi alterado.
- A integração continua usando o driver de leitura com allowlist de SQL exato. A única consulta adicionada à allowlist é um SELECT parametrizado. Autenticação e CSRF continuam obrigatórios.

## Arquivos

- `config/Migrations/20261024100000_OsSemCaderno.php`: cria exclusivamente as duas tabelas de consulta/seleção, com índices e unicidade por consulta/número.
- `src/Model/Table/PendenciasConsultasTable.php` e `PendenciasOrdensTable.php`: tabelas auxiliares.
- `src/Integration/Totvs/PendingOrders.php` e `Queries.php`: consulta em blocos e allowlist.
- `src/Service/OsSemCaderno.php`: contexto, consulta, filtros, seleção, resumo, confirmação e processamento.
- `src/Controller/OsSemCadernoController.php`: operações HTML/JSON e contexto da sessão.
- `config/routes.php` e `templates/layout/pcm.php`: rota e menu administrativo.
- `templates/OsSemCaderno/index.php` e `webroot/js/os-sem-caderno.js`: tela, seleção e continuação automática.
- `tests/TestCase/Controller/OsSemCadernoTest.php`: testes do módulo em base SQLite isolada.
- `tests/TestCase/Controller/EtapaDoisTest.php` e `PeriodosNavegacaoTest.php`: expectativas do novo menu, mantendo as diferenças entre perfis.
- `tools/test_os_sem_caderno_js.js` e `build_js_verification.php`: verificações JavaScript, incluindo alterações rápidas, contador, confirmação de gravação da seleção, CSRF, continuação, falha e retomada.
- `tools/verify_os_sem_caderno.php`: comparação de hashes dos dados existentes e validação real da consulta e das páginas HTTP. O modo `baseline` registra hashes antes da instalação; a execução normal cria apenas uma consulta de pendências, sem selecionar nem vincular O.S.

## Validação local

Suíte PHPUnit completa: **248 testes, 2.047 assertions, todos aprovados**. O conjunto JavaScript no Edge também passou, incluindo contador atualizado sem recarregar, marcações rápidas serializadas, CSRF, continuação automática, falha e retomada.

A migration foi aplicada somente no PostgreSQL local `pcm_controle_entressafra`. Os dados das 20 tabelas existentes, excluindo o registro de migrations, foram comparados antes e depois e permaneceram idênticos. Apenas as novas tabelas auxiliares receberam consultas de conferência.

A consulta real retornou **867 O.S. elegíveis**, das quais **241 sem caderno**, em **25 páginas**, na conferência realizada em 23/10/2026. As páginas HTTP 1, 2 e 3 foram verificadas com sessão administrativa e a tela foi inspecionada visualmente. Esses totais são uma fotografia da consulta e podem mudar com novas operações.

Os testes cobrem listas de 1, 10, 45 e 2.250 O.S., seleção em 225 páginas, divisão em múltiplos lotes, filtros, descrição local prioritária, atividades sem caderno, vínculos de outro período, manutenção não elegível, confirmação, processamento, conflitos, confirmações concorrentes intercaladas, duplicidade, indisponibilidade e retomada, permissões e token de outra conta/período. Os testes de vínculo utilizam exclusivamente a base isolada. Nenhum vínculo real foi executado na base operacional.
