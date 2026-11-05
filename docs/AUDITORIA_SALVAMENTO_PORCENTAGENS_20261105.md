# Auditoria do salvamento de porcentagens — 05/11/2026

Escopo: `/lancar-porcentagem`, grade semanal, envio JSON, persistência e leitura. A Curva S, o TOTVS e os demais módulos não foram modificados nesta auditoria.

## Falhas encontradas

- Um rascunho restaurado mantinha inicialmente sua revisão antiga, mas uma nova edição da célula substituía a base, a identidade do registro e a revisão pelos valores do novo GET. Uma alteração concorrente podia, assim, ser sobrescrita sem conflito. A revisão original agora acompanha todas as edições até o envio. O teste JavaScript reproduz a sequência carregar 10/revisão 1, digitar 20, outro usuário salvar 30/revisão 2, recarregar e digitar 40. O envio continua baseado em 10/revisão 1 e é rejeitado como conflito.
- Exceções de banco escapavam como erro genérico da aplicação, frequentemente sem JSON legível pelo frontend. Agora retornam HTTP 503, uma mensagem clara e registro no log do servidor. Detalhes SQL não são enviados à usuária.
- Falha ao limpar sessionStorage depois de uma gravação confirmada podia apresentar erro de salvamento. A limpeza local agora é independente do resultado da transação.
- O GET da grade não definia explicitamente `no-store`. A resposta agora impede armazenamento em cache, para que a recarga consulte os registros persistidos.

Não há evidência suficiente para atribuir a reclamação específica a uma dessas falhas: não foram fornecidos usuária, O.S., semana e horário do incidente. As falhas acima são reproduzíveis; não se afirma que os dados da reclamação tenham sido apagados.

## Garantias verificadas

- Todas as células pendentes do rascunho ativo, inclusive de outras páginas e buscas, compõem um único JSON; 0 é diferente de vazio. O rascunho é separado por usuário, entressafra e setor e permanece nesta aba. Ao trocar de setor, deve-se voltar ao setor original para salvar seu rascunho; essa regra está explícita na tela.
- Backend valida cada O.S., semana iniciada, percentual, permissão e revisão. O filtro e a paginação não limitam as páginas incluídas no salvamento.
- A gravação, a auditoria e o recibo idempotente pertencem à mesma transação. Cada registro inserido é relido para confirmar percentual, ID e revisão. O retorno ao controller ocorre após o COMMIT; só então é gerada a mensagem de sucesso, com quantidade efetivamente gravada.
- Um conflito invalida o lote inteiro. No PostgreSQL são mantidos os bloqueios de O.S. e de reenvio existentes. A usuária pode comparar o rascunho com o valor carregado do banco e descartá-lo explicitamente por célula; nenhuma revisão é aceita silenciosamente.
- Correções inserem novas revisões e auditorias; os valores anteriores permanecem. Semanas anteriores são gravadas e recuperadas pela semana de referência, independentemente da data do salvamento.
- Falhas de rede/banco preservam o rascunho e permitem repetir a mesma chave, sem duplicar registros já confirmados. O frontend exige recibo com quantidade e lista de células compatíveis antes de limpar o rascunho e recarregar.

## Testes executados

- `php vendor/phpunit/phpunit/phpunit tests/TestCase/Service/EvolucaoSemanalTest.php`: 18 testes, 108 asserções. Inclui gravação, edição, auditoria, GET após POST, 0%, semanas anteriores, paginação real com mais de 50 O.S., busca, conflito entre usuários e falha na segunda gravação do lote, sem sucesso falso nem gravação parcial.
- `node tools/test_porcentagens_lote_js.js`: testes do frontend com DOM/rede/armazenamento simulados; envio completo, paginação/filtros, restauração, revisão obsoleta, resolução explícita, erro de banco/rede e recibo inválido.
- `php tools/verify_porcentagens_postgres.php`: PostgreSQL real com tabelas e sequências temporárias da conexão; gravação com COMMIT real, leitura de 0% e 50%, edição por outra usuária, rejeição de revisão obsoleta, reenvio idempotente, falha de INSERT e falha diferida no COMMIT. Hashes das tabelas públicas conferidos antes e depois, sem alterações.
- Sintaxe PHP e `git diff --check` aprovados.

Os testes de concorrência reproduzem duas usuárias enviando a mesma revisão em sequência; não constituem teste de carga com requisições simultâneas. Os testes JavaScript usam simulações e não substituem um teste visual no navegador.

Implantação: publicar controller, serviço, template e JavaScript atualizados. A URL do JavaScript já usa `filemtime` para invalidar o cache do arquivo. Não há migration nem procedimento de alteração dos dados.
