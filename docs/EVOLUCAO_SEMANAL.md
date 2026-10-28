# Planilha semanal de evolução das O.S.

## Uso

Em `/lancar-porcentagem`, pesquise uma O.S. ou selecione o setor da Curva S. A grade apresenta O.S., descrição, equipamento, último acumulado salvo e uma coluna para cada semana iniciada da entressafra. Os períodos são os mesmos de `CurvaSemanal::weeks`: terça a segunda, análise na terça, incluindo primeira/última semanas parciais e viradas de mês/ano. Uma coluna nova aparece automaticamente quando seu período começa, no horário de Brasília. Não há cadastro manual de semanas.

Percentuais são acumulados e não são somados entre colunas. Vazio significa ausência de lançamento, enquanto 0 é um registro explícito. Sem atualização, o cálculo mantém o último percentual anterior; a grade permanece vazia nessa semana, sem inserir uma cópia artificial. **Mais recente** mostra o último acumulado salvo da O.S., sem incluir o rascunho ainda não enviado.

Digite os valores e use **Salvar porcentagens**. Apenas células alteradas são enviadas, inclusive de outras páginas do mesmo setor. Alterações pendentes são destacadas e guardadas no `sessionStorage`, separado por usuário, entressafra e filtro. Uma falha de validação, sessão ou concorrência mantém o rascunho. Enter move para a próxima célula. Os filtros e a paginação de 50 O.S. continuam disponíveis.

Reduzir um acumulado já salvo, registrar um valor menor que o anterior ou aumentar uma semana acima de um registro posterior exige **Correção excepcional**, confirmação e justificativa naquela célula. Contas operacionais podem corrigir suas O.S. autorizadas, inclusive semanas anteriores, sem receber acesso administrativo. Ao existir conflito de edição, confira o valor salvo no título da célula; voltar ao valor salvo descarta a alteração local dessa célula, permitindo redigitá-la com a revisão atual após recarregar.

Limpar uma célula ainda não salva descarta o rascunho. Limpar uma célula que já possui registro não apaga seu histórico: o backend solicita um percentual corrigido. Não foi criado um recurso de exclusão ou cancelamento silencioso de percentuais.

## Armazenamento e compatibilidade

Não há nova tabela, coluna ou migration. São reutilizadas `evolucao_semanas`, `evolucao_registros`, `evolucao_lotes` e `auditorias`.

`EvolucaoSemanal` monta a grade sobre o histórico existente. Uma célula reúne as referências legadas que caem dentro de seu período e apresenta o último acumulado válido desse intervalo. Ao corrigir uma célula existente, preserva a referência anterior e insere outra revisão; não atualiza nem remove o registro antigo. Uma célula nova usa o início da semana como referência efetiva. Assim o valor já fica visível no acumulado atual durante a semana aberta, e continua sendo selecionado corretamente no fechamento de segunda-feira. A identidade pública da célula é a O.S. mais o fim do período, não o ID de um registro.

Cada revisão guarda percentual, usuário, horário UTC e justificativa. A auditoria também registra O.S., semana, fim do período, estado anterior e confirmação excepcional. Os serviços legados `Evolucao` e `PorcentagensLote` são preservados; a tela usa o serviço semanal novo.

A seleção de O.S. considera a área principal diretamente na atividade ou herdada do caderno, mantendo permissões do setor executante e contexto da entressafra. Caderno não é obrigatório quando a atividade já tem área principal. As descrições reutilizam a apresentação existente e, quando necessário, a consulta em lote somente leitura ao TOTVS. Salvar percentuais não abre uma conexão TOTVS.

## Transações e concorrência

O backend revalida todas as células, o contexto e as permissões. Recebe ID do registro original, revisão e valor original de cada célula. Uma edição baseada em estado antigo gera conflito explícito, com identificação de O.S. e semana, antes de qualquer gravação definitiva.

O lote inteiro é atômico no PostgreSQL. A entressafra recebe bloqueio compartilhado, para impedir fechamento do período durante o envio, e somente as O.S. editadas recebem bloqueios exclusivos em ordem estável. O.S. diferentes continuam independentes. Células da mesma O.S. são verificadas juntas para preservar a validação entre semanas. A criação inicial de uma referência semanal compartilhada usa `ON CONFLICT`, sem duplicidade. Não há um bloqueio exclusivo global da entressafra como no lançador antigo.

O token de envio, o hash do conteúdo e o resultado reutilizam `evolucao_lotes`. Reenviar a mesma solicitação retorna o resultado anterior; reenviar uma célula sem mudança não cria revisão. Qualquer erro desfaz o lote inteiro.

## Curva S e revisão histórica

O fechamento existente continua selecionando o último acumulado válido até o fim de cada semana. O.S. sem lançamento anterior entram com 0 no cálculo, e o realizado continua sendo a média simples das O.S. únicas do setor. Não foram alterados o modelo programado, interpolação, tendência ou calendário operacional.

Uma correção retroativa não modifica um fechamento salvo. O relatório compara os registros atuais ao registro identificado em cada O.S. da versão congelada. Quando houver diferença, mostra **Lançamentos revistos · revisão do fechamento pendente**. O realizado publicado permanece igual.

ADMIN_PCM usa o fluxo existente em **Calendário e apuração semanal**: informa a versão atual e uma justificativa para **Registrar nova versão da apuração**. A composição anterior permanece consultável. Após a revisão, o aviso pendente desaparece e o histórico indica o número da revisão do fechamento. Nenhuma revisão é executada automaticamente pelas funcionárias ou por um GET.

## Validação

```powershell
$env:EVOLUCAO_SEMANAL_PREVIEW='1'
C:\xampp\php\php.exe vendor/phpunit/phpunit/phpunit tests/TestCase/Service/EvolucaoSemanalTest.php tests/TestCase/Controller/PorcentagensLoteTest.php tests/TestCase/Service/CurvaSemanalTest.php tests/TestCase/Service/CurvaSimulacaoTest.php tests/TestCase/Service/ProgramacaoAutomaticaTest.php

powershell -ExecutionPolicy Bypass -File tools/test_apontamentos_batch_browser.ps1 -Preview evolucao-semanal-preview.html -TestScript test_evolucao_semanal_browser.js

$env:PCM_RUN_OPERATIONAL_TEST='1'
C:\xampp\php\php.exe tools/verify_operational_concurrency.php
powershell -ExecutionPolicy Bypass -File tools/test_apontamentos_batch_browser.ps1 -Preview evolucao-semanal-long-preview.html -TestScript test_evolucao_semanal_long_browser.js
```

Os testes cobrem semanas dinâmicas e parciais, virada de ano, múltiplas semanas/O.S., 100%, vazio versus zero, carregamento de acumulado anterior, correções e justificativas, compatibilidade legada, idempotência, validação atômica, revisão concorrente, filtros e integração com os fechamentos. As prévias e os valores de teste são isolados. O teste PostgreSQL usa schema temporário e confere a preservação de nove tabelas reais; seu gateway não usa o TOTVS real. O navegador verifica larguras de 1440, 820 e 390 pixels.

Resultado da validação: 45 testes automatizados e 5.184 assertions aprovados; 90 verificações de navegador (grade curta e longa, nas três larguras); 36 verificações de processos/sessões independentes no PostgreSQL/Apache. A consulta HTTP das duas páginas reais do setor com 328 O.S. preservou integralmente as 24 tabelas públicas, sem criar lançamentos reais. Após o ajuste final da classificação de correções na auditoria, os três testes específicos de gravação/revisão/redução também passaram.

## Arquivos desta reformulação

- Novo `src/Service/EvolucaoSemanal.php`: grade, validação, gravação, revisões e concorrência.
- `src/Controller/OperacionalController.php`: encaminhamento da tela e mensagem com quantidade de células.
- `templates/Operacional/porcentagem.php`, `webroot/css/porcentagens-lote.css`, `webroot/js/porcentagens-lote.js`: planilha, cabeçalhos/colunas fixas, células, rascunho e envio único.
- `src/Service/CurvaSemanal.php`, `templates/element/curva_semanal.php`, `templates/Evolucao/programacao_semanal.php`: identificação de revisão pendente e fluxo administrativo já existente.
- `src/Service/CurvaSimulacao.php`: mantém os avisos históricos fora dos dados simulados.
- Novo `tests/TestCase/Service/EvolucaoSemanalTest.php`; atualização de `tests/TestCase/Controller/PorcentagensLoteTest.php`.
- `tools/operational_concurrency_worker.php`, `tools/verify_operational_concurrency.php`: cenários simultâneos da grade em PostgreSQL.
- `tools/verify_porcentagens_lote_http.php`: consulta das páginas reais e comparação de todas as tabelas públicas antes/depois.
- Novos `tools/test_evolucao_semanal_browser.js`, `tools/test_evolucao_semanal_long_browser.js` e este documento.

**Migrations necessárias: nenhuma.** Nenhuma migration foi executada nesta reformulação.
