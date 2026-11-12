# Exclusão individual de apontamentos — correção de JSON e PostgreSQL

Verificação realizada em 12/11/2026.

## Causa confirmada

O gatilho PostgreSQL `trg_protect_confirmed_entry`, criado pela migration
`20261022120000_OperacaoSimplificada`, chama `pcm_protect_confirmed_entry()`.
Quando `OLD.controle_pcm = 'lancado'`, a função original trata a alteração de
`cancelado` como uma correção proibida e lança a exceção:

> Reabra a confirmação pelo PCM antes de corrigir o apontamento.

A definição instalada no banco local foi conferida por leitura de
`pg_get_functiondef`, sem alterar apontamentos reais. A falha foi reproduzida
no endpoint com as migrations completas em um schema descartável PostgreSQL.
O verificador anterior usava `CREATE TABLE ... LIKE`, que não copia gatilhos.

O formulário também usava POST na própria action `view`. Ela retornava JSON
somente em alguns caminhos; exceções de banco, CSRF, permissões e redirecionamentos
de login podiam produzir HTML. O frontend chamava `response.json()` sem validar
o tipo da resposta, gerando `Unexpected token '<'`.

## Alteração

O modal agora usa `POST /fila-pcm/os/{id}/excluir-apontamento`, action `cancelEntry`.
O middleware específico desse caminho converte erros anteriores à action,
incluindo CSRF e autenticação, em JSON. Outros módulos mantêm o comportamento
anterior. O backend confirma o estado persistido após a transação antes de
responder com `success: true`, `cancelado: true` e o ID do apontamento.

| Resultado | HTTP |
|---|---:|
| Cancelamento confirmado no banco | 200 |
| Sessão expirada | 401 |
| CSRF inválido, perfil ou entressafra sem acesso | 403 |
| O.S., apontamento ou rota não encontrados | 404 |
| Método diferente de POST | 405 |
| Revisão desatualizada, registro cancelado ou contexto ausente | 409 |
| Motivo, confirmação ou ciência TOTVS ausentes | 422 |
| Erro interno ou de banco | 500 |

O frontend valida status, Content-Type e conteúdo antes de mostrar sucesso,
trata HTML/JSON inválido com mensagem compreensível e impede envios simultâneos.
Justificativa, ADMIN_PCM, ciência TOTVS, CSRF, exclusão lógica e auditoria
transacional continuam obrigatórios. A confirmação anterior permanece intacta.
Nenhuma operação de escrita no TOTVS foi adicionada.

## Migration necessária

`20261112130000_AllowConfirmedEntryCancellation` substitui a função do gatilho.
Permite a transição de ativo para cancelado por ADMIN_PCM, com incremento da
revisão e preservação do status, usuário e data da confirmação TOTVS. Continua
bloqueando alterações nos dados originais de apontamentos confirmados.

A migration foi aplicada e revertida somente no schema de teste. **Não foi
aplicada ao banco real: sua aplicação é necessária na atualização do ambiente.**
Ela altera a função do gatilho, sem atualizar ou apagar registros.

## Testes

- `php vendor/bin/phpunit tests/TestCase/Controller/FilaPcmUnificadaTest.php --colors=never`:
  33 testes, 585 asserções, um caso específico PostgreSQL ignorado no SQLite.
- `php tools/verify_cancelamento_api_postgres.php`:
  seis testes da API, 119 asserções, com todas as migrations e gatilhos ativos.
  Inclui pendente, confirmado, motivo/ciência obrigatórios, duplicidade,
  CSRF, sessão expirada, permissão, IDs inválidos, erro de banco com rollback,
  proteção contra edição e reprodução do gatilho antigo com rollback da migration.
- `node tools/test_cancel_entry_js.js`: 16 cenários JavaScript, incluindo HTML
  inesperado, JSON inválido, erros HTTP, ID/estado divergentes, atualização da
  lista, falha após persistência e envio simultâneo.
- Sintaxe PHP/JavaScript e `git diff --check` verificados.

O verificador PostgreSQL cria um schema aleatório, valida a conexão antes de
qualquer limpeza de fixtures e remove somente esse schema ao terminar. Os
checksums dos dados públicos de usuários, entressafras, O.S., apontamentos,
insumos e auditorias permaneceram iguais. Nenhum apontamento real foi excluído.
Não foi realizada validação visual em navegador.

## Arquivos alterados ou adicionados

- `config/routes.php`
- `src/Application.php`
- `src/Middleware/CancelamentoApontamentoJsonMiddleware.php`
- `src/Controller/FilaPcmController.php`
- `src/Service/TimeEntries.php`
- `templates/FilaPcm/view.php`
- `templates/element/cancel_entry_confirmation.php`
- `webroot/js/cancel-entry.js`
- `config/Migrations/20261112130000_AllowConfirmedEntryCancellation.php`
- `tests/TestCase/Controller/FilaPcmUnificadaTest.php`
- `tools/verify_cancelamento_api_postgres.php`
- `tools/test_cancel_entry_js.js`
- Este relatório.
