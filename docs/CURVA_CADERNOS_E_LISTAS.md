# Curva por área de apresentação, cadernos e listas de O.S.

## Regra de contabilização

A **equipe executante** continua sendo o setor mapeado por `TJ_CODAREA`/filial do TOTVS. Essa informação autoriza as contas operacionais e nunca é substituída pela localização da atividade ou pelo caderno.

A **área principal de apresentação da curva** é uma definição administrativa independente:

1. Uma exceção explícita em `atividades.area_curva_id` tem prioridade.
2. Sem exceção, a O.S. herda `cadernos.area_curva_id`.
3. Sem as duas definições, a O.S. fica nas pendências de classificação, continua recebendo horas/percentuais e entra uma vez no consolidado.

As associações muitos-para-muitos de equipes/cadernos permanecem. Elas não multiplicam a participação de uma O.S. nas curvas. Cada caderno tem uma única área principal de apresentação; cada atividade tem, no máximo, uma exceção. Vários cadernos podem ter a mesma área principal.

O cadastro `areas_fabrica` fornece os nomes de apresentação (Moenda, Destilaria etc.). **A nova classificação não preenche `area_fabrica_id` da O.S. nem atribui prazos físicos.** Os prazos já existentes de 26/03/2027 e 10/04/2027 permanecem configurações de planejamento independentes.

Nenhuma correspondência por nome foi aplicada aos cadastros reais. MOENDA, CALDEIRARIA - MOENDA, TRAT. CALDO, CALDEIRARIA - TRAT. CALDO e DESTILARIA são exemplos para conferência administrativa, não classificações presumidas.

## Vincular uma lista dentro do caderno

1. Abra **Cadernos**, escolha o caderno da entressafra e clique em **Vincular O.S. por lista**. A URL é `/cadernos/{id}/vincular-lista`.
2. Cole números por linha, espaço, vírgula ou ponto e vírgula. O exemplo `005587, 005569, 005587, 005579, 005583` informa 5 entradas, 4 números únicos e 1 repetido. Números menores que seis dígitos são completados à esquerda; zeros informados são preservados.
3. Prepare a prévia. A consulta usa STJ010/ST4010 em blocos parametrizados de até 100 números, com filial 01. Valida origem 2026, situação L, término N, serviço ENTRESSAFRA inequívoco, identidade e mapeamento da equipe. Aceita códigos antigos pelo nome real do serviço. Nenhuma escrita é feita no TOTVS.
4. Confira os resultados: disponível, mesmo caderno, outro caderno, não encontrada, inelegível, ambígua ou erro de revalidação. São mostrados equipe, serviço, bem, descrição administrativa local disponível e caderno atual.
5. **Selecionar todas as elegíveis** abrange todas as páginas e exclui transferências. Também é possível adicionar marcadas de páginas diferentes. A seleção fica no servidor; **Limpar seleção inteira** permite reiniciar a escolha.
6. Para transferir, marque as O.S. que estão em outro caderno e marque explicitamente **Incluir ... outro caderno**. Adicione-as à seleção. A confirmação exige justificativa para essas transferências.
7. Digite a quantidade total selecionada e confirme. O processamento reconsulta o TOTVS em blocos, revalida cada vínculo e grava transacionalmente cada item. Ao retomar a mesma prévia não duplica O.S. nem repete transferências concluídas.

Quando a equipe executante ainda não integra o caderno, a prévia informa que a confirmação também adicionará essa associação administrativa. A associação e o vínculo são auditados na mesma transação; não são removidas equipes existentes nem ampliadas as permissões de apontamento sobre O.S. de outras equipes.

A identidade local permanece única por entressafra/filial/número. O.S. já no mesmo caderno não são recriadas. Vínculos alterados por outra pessoa após a prévia exigem nova conferência. Uma interrupção da fonte preserva o token e permite retomar o processamento.

## Definir Moenda e acompanhar equipes diferentes

No cadastro do caderno, clique em **Definir setor da curva / corrigir classificações** (`/organizacao-curva`).

1. Confira a entressafra e o caderno MOENDA.
2. Selecione a área principal **Moenda**, informe a justificativa e prepare a prévia.
3. Confira o alcance sobre as O.S. e confirme. O.S. com exceção individual mantêm sua própria classificação.
4. Repita para CALDEIRARIA - MOENDA somente depois de conferir que o agrupamento pertence à mesma área de apresentação.
5. Abra `/controle-pcm`, selecione **Moenda** e clique em **Atualizar visão**. O.S. executadas por Mecânica, Elétrica ou Caldeiraria aparecem juntas quando seus cadernos ou exceções principais pertencem à Moenda. Cada uma mantém a equipe executante original.

Correções de exceções individuais também ficam nesta tela administrativa, com seleção, prévia, justificativa, verificação de concorrência e auditoria. A assinatura da prévia inclui o estado dos registros; alterações posteriores exigem nova prévia. A área pode ser deixada pendente, sem bloquear lançamentos. O relatório de pendências não está misturado ao acompanhamento principal.

## Controle PCM simplificado

`/controle-pcm` mostra a entressafra ativa, seletor da área de apresentação, total/sem início/em andamento/em 100%, evolução acumulada, curva semanal e relação de O.S. de **10 registros por página**. A lista apresenta serviço, equipamento, equipe, acumulado e última atualização em São Paulo. Pesquisa e filtros são preservados ao navegar.

Indicadores e gráfico são calculados sobre todas as O.S. do filtro; a paginação limita apenas o detalhamento. São usados os últimos acumulados válidos, mantidos até novo lançamento. O gráfico inclui as referências semanais e a posição atual. Uma atualização não exige que todas as O.S. sejam lançadas na semana.

Sem pesos aprovados, o título é **Evolução física simples — não ponderada**. A média não é apresentada como Curva S ponderada oficial. Sem metas aprovadas aplicáveis ao prazo de cada O.S., aparece **Programação pendente**.

Pesos, metas e reprodução de versões ficam em **Administração**, fora da tela principal. A edição de pesos tem dez O.S. por página: cada salvamento cria uma nova versão, preserva os valores das demais páginas e rejeita uma página desatualizada. Salve antes de navegar. A importação Excel continua abrangendo o escopo completo. A análise histórica administrativa anterior está em `/evolucao/analise`.

Histórico de registros individuais, classificação física administrativa, consultas, cadastros, prévias de vínculo e filas usam paginação no backend. A lista de conflitos de horários é intencionalmente completa, para exibir **todos** os registros conflitantes.

## Lançar porcentagem e calcular o avanço

Em `/lancar-porcentagem`, pesquise e selecione uma O.S., confira serviço/bem/descrição disponível e último acumulado, digite o novo acumulado e salve.

O avanço exibido compara o novo valor com o **último acumulado apresentado**, inclusive quando já houve outro registro na mesma semana: **10% → 80% = +70 pontos percentuais**. Um novo avanço na semana atual preserva o registro anterior como revisão histórica. Reduções e retificações retroativas permanecem restritas ao PCM e exigem justificativa; a revisão enviada impede sobrescrever uma alteração concorrente.

Editar a pesquisa invalida o ID anterior e a revisão. Selecionar outra O.S. cancela respostas pendentes da pesquisa anterior; um erro atrasado de identificação não pode substituir a mensagem de uma seleção já confirmada. O ID local, entressafra ativa e setor autorizado são verificados no backend. Não é necessário caderno, classificação da curva, calendário aprovado, peso ou meta para lançar.

## Demonstração isolada da Elétrica

Abra `/controle-pcm?demonstracao=eletrica` com perfil PCM. A tela apresenta o aviso **DEMONSTRAÇÃO ISOLADA**, as O.S. locais da equipe Elétrica e dois períodos hipotéticos com 30% e 40%. A evolução simples passa de 30% para 40%; os percentuais demonstrativos não são gravados, não substituem execução real e não se tornam ponderação oficial.

Clique em **Encerrar demonstração** para voltar aos dados oficiais. O modo é um cálculo temporário da resposta HTTP, sem tabelas de execução simulada e sem lançamentos em massa. Se não houver O.S. locais da Elétrica, não são inventadas O.S. para preencher o exemplo.

## Migration e integridade

Migration aditiva aplicada: **20261023100000_ClassificacaoPrincipalCurva**. Acrescenta somente `area_curva_id` anulável em `cadernos` e `atividades`, com índices e chaves estrangeiras restritivas. Não infere vínculos, não preenche classificações e não modifica percentuais, pesos, calendário, prazos, apontamentos ou auditorias existentes. As constraints de unicidade da O.S., integridade caderno/equipe e exclusão global de horários continuam aplicadas.

O manifesto `tmp/curva_integridade.json` registrou estrutura e hashes de 17 tabelas antes da migration. A comparação posterior confirmou os registros e todas as colunas anteriores. Sequências podem avançar durante testes em rollback; nenhum registro fictício fica como execução oficial.

## Executar as verificações

```powershell
php bin/cake.php migrations status
php vendor/phpunit/phpunit/phpunit --no-progress
php tools/verify_curva_integridade.php
php tools/verify_curva_reorganizacao.php
php tools/verify_curva_http.php
php tools/verify_operacao_http.php
php tools/verify_evolucao_http.php
php tools/build_js_verification.php
```

Abra `tmp/curva-js-verification.html` no navegador para executar os testes JavaScript. Eles verificam autocomplete com/sem filtros, estado da seleção, resposta atrasada contraditória, avanço 10 → 80, revisão zero e formulários de evolução, sem usar dados operacionais.

`verify_curva_reorganizacao.php` consulta duas O.S. reais por SELECT, uma da Elétrica e outra da Mecânica, e testa em transação revertida a lista, duplicidades, associação ao mesmo caderno de Moenda, média de **45% → 60%**, ausência de pesos e simulação **30% → 40%** sem escrita. Compara hashes de 17 tabelas antes/depois.

`verify_curva_http.php --preview` salva páginas de conferência em `tmp/curva-controle.html`, `tmp/curva-moenda.html` e `tmp/curva-eletrica-demo.html`. Verifica perfis, limite de dez O.S., ausência dos blocos antigos na tela principal e identificação da demonstração. Contas temporárias são removidas.

A concorrência real da trava de horários também foi verificada com dois processos PHP independentes: uma gravação foi aceita, e a concorrente ficou bloqueada até identificar o conflito. Fixtures foram removidas e a integridade dos dados anteriores foi novamente conferida. O serviço de conflitos consulta os intervalos completos de todo o histórico ativo, sem limitar mês, semana, data de cadastro, setor, O.S. ou entressafra.

Resultado final: **215 testes e 1.311 asserções aprovados**, incluindo **18 testes específicos / 119 asserções** da reorganização. Aprovados também os testes JavaScript em navegador (autocomplete, seleção assíncrona, avanço de percentuais e formulário de evolução), sintaxe PHP, HTTP real nos três perfis, PostgreSQL com consultas reais ao TOTVS e rollback, concorrência com dois processos e comparação de integridade das 17 tabelas. As telas principal e de demonstração foram renderizadas e inspecionadas visualmente; capturas em `tmp/curva-controle.png` e `tmp/curva-eletrica-demo.png`.
