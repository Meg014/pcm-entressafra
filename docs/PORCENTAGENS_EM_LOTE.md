# Lançamento de porcentagens em lote

A rota principal continua sendo `/lancar-porcentagem`, agora exclusivamente em lote. Existe apenas uma entrada de porcentagens no menu. O acesso antigo `/evolucao` redireciona para essa tela; o POST antigo não grava percentuais. O formulário individual foi substituído e seus POSTs antigos são rejeitados sem alterar o histórico.

## Fluxo

Escolha **Setor da Curva S**, pesquise opcionalmente pelo número da O.S., altere os totais necessários e clique em **SALVAR PORCENTAGENS**. Os únicos filtros são setor principal e número. A tabela contém quatro colunas: número, descrição específica, bem/equipamento e total concluído.

A consulta utiliza `cadernos.area_curva_id` e a entressafra da sessão, incluindo as diferentes equipes executantes dos cadernos desse setor. Não existe filtro de equipe executante na tela. As autorizações existentes por O.S./equipe continuam aplicadas por `Access::activityScope`, sem conceder novas permissões a responsáveis. Administradores veem todas as equipes autorizadas pelo seu perfil.

Só aparecem O.S. vinculadas a cadernos da entressafra selecionada. Os números são tratados como texto e mantêm os zeros. A descrição específica local tem prioridade, seguida da descrição real do snapshot. Quando falta descrição armazenada, a página tenta uma consulta em bloco à integração TOTVS existente; uma indisponibilidade não bloqueia o lançamento local. Não há substituição da atividade pelo nome genérico do serviço. Descrições longas podem ser expandidas.

## Paginação e rascunhos

- A tabela tem 50 O.S. por página; todo o setor está acessível pelas páginas e pela pesquisa.
- As alterações ficam em `sessionStorage` desta aba, separadas por usuário, entressafra e setor principal. O rascunho mantém valores entre páginas e pesquisas do mesmo setor.
- Nenhum percentual é enviado ao servidor durante a digitação. Enter no campo de percentual avança para o próximo campo, sem salvar implicitamente.
- O botão envia todas as alterações pendentes do setor, incluindo outras páginas, em um único JSON. Não há seleção de O.S. nem confirmação de quantidade.
- Navegação para fora da tela com alterações pendentes solicita o aviso do navegador. A navegação entre páginas do mesmo lote preserva o rascunho. Se o navegador não puder armazená-lo, a navegação interna é bloqueada até salvar.
- Durante o envio, os campos ficam bloqueados para evitar que uma alteração digitada depois do envio seja perdida. Falhas mantêm os valores pendentes para conferência e nova tentativa.
- Após sucesso, o rascunho é limpo e a mesma URL é recarregada, mantendo setor, pesquisa e página e exibindo os valores efetivamente persistidos e a quantidade atualizada.

## Integridade

O total digitado deve estar entre 0 e 100, com até duas casas decimais. O serviço compara com o valor apresentado inicialmente e ignora os valores mantidos. Assim, não cria registros de 0% ou confirmações semanais artificiais. O histórico e os cálculos acumulados permanecem em `Evolucao`.

A referência semanal é calculada automaticamente por `OperacaoRapida::reference`. Valores, revisão, referência, vínculo ao caderno, setor principal e permissões são validados novamente no salvamento. Uma alteração concorrente ou mudança de referência exige conferência; não sobrescreve o histórico silenciosamente.

O lote completo é validado antes de gravar, dentro de uma transação com bloqueio da entressafra no PostgreSQL. A gravação reutiliza `Evolucao::saveBatch` em blocos de até 200 alterações, mantendo auditoria. Até 10.000 alterações cabem em um envio. Se qualquer bloco falhar, toda a transação é revertida. Uma chave com hash do conteúdo protege contra reenvio duplicado.

Reduções e histórico posterior são bloqueados nesta tela inclusive para administradores. A correção administrativa permanece separada em `/evolucao/correcoes`, exclusiva para ADMIN_PCM/PCM, acessível pelo histórico da O.S. e com as validações e justificativas existentes. Não há justificativa nos lançamentos normais.

Não houve migration, alteração nos apontamentos de horas, vínculos ou configurações da Curva S. A eventual consulta TOTVS usa exclusivamente o SELECT em bloco já autorizado.

## Arquivos

- `src/Service/PorcentagensLote.php`: setor principal, paginação, descrições, validação, transação e idempotência do conjunto.
- `src/Controller/OperacionalController.php`: único formulário normal de porcentagens, resposta JSON e referência automática.
- `src/Controller/EvolucaoController.php`: redirecionamento antigo e correção administrativa separada.
- `templates/Operacional/porcentagem.php`, `webroot/js/porcentagens-lote.js` e `webroot/css/porcentagens-lote.css`: tabela compacta, rascunhos e envio único.
- `templates/layout/pcm.php`: retirada do segundo caminho de lançamento.
- `templates/Evolucao/index.php` e `historico.php`, `config/routes.php`: identificação e acesso das correções separadas.
- `tests/TestCase/Controller/PorcentagensLoteTest.php`: testes do novo fluxo, permissões, concorrência, acumulados, inalterados e lote de 250.
- Testes existentes de Cadernos, menus, navegação, calendários, evolução e operação rápida: adequados ao encerramento do formulário individual e à separação das correções, preservando a cobertura de histórico e permissões.
- `tools/test_porcentagens_lote_js.js`, `build_js_verification.php`: testes JavaScript de rascunho entre páginas, envio único, erro, recuperação, validação de campos em outras páginas e saída da tela.
- `tools/verify_porcentagens_lote_http.php`: verificação de leitura do HTML real e hashes das tabelas antes/depois.

## Verificação local

Suíte completa: **254 testes e 2.091 assertions**, todos aprovados. O conjunto JavaScript executado no Edge também passou, incluindo a prevenção de salvamento implícito por Enter.

Os testes de gravação foram realizados exclusivamente na base SQLite isolada. Cobrem 260 O.S. no mesmo setor, com três equipes, seis páginas e gravação de 250 O.S.; acumulados e atualização imediata do relatório da Curva S; valores mantidos; redução, valor inválido e concorrência; permissões e vínculo removido; salvamento HTTP e bloqueio do formulário individual.

A verificação HTTP local conferiu duas páginas do setor com **328 O.S.**, quatro colunas e os campos esperados. Os hashes das **23 tabelas** permaneceram idênticos. Nenhum percentual real foi lançado e não houve gravação no TOTVS. O HTML foi inspecionado visualmente e os testes JavaScript foram executados no Edge.
