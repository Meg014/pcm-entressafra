# Etapa 3 — Evolução física semanal e Controle PCM / Curva S

## Acesso local

Servidor existente: `http://localhost:8766`.

- `/evolucao`: lançamento acumulado em lote, 50 O.S. por página; histórico ao clicar no número.
- `/evolucao/historico/{id}`: revisões e responsáveis da atividade autorizada.
- `/evolucao/configuracao`: calendário, pesos, importação Excel, metas e versões.
- `/controle-pcm`: indicadores, Curva S, consolidação por setor/caderno e histórico.
- `/evolucao/modelo/pesos` e `/evolucao/modelo/metas`: modelos Excel da entressafra selecionada.

Para iniciar o servidor, se necessário:

```powershell
php bin/cake.php server -H 127.0.0.1 -p 8766
```

## Preparação operacional

1. Conferir as datas da entressafra em seu cadastro.
2. Como PCM/ADMIN_PCM, abrir Configuração e selecionar a entressafra. Conferir o calendário sugerido (segundas-feiras e limites parciais) e confirmar suas referências. Pode configurar uma primeira referência e acrescentar semanas parciais. Referências já confirmadas são preservadas para não invalidar lançamentos ou linhas de base; não são apagadas automaticamente.
3. Em Evolução das O.S., filtrar setor/caderno e semana. Preencher somente os percentuais a registrar, inclusive zero explícito. Campos vazios não alteram O.S. O botão confirma a quantidade. Sem lançamento, o último acumulado é mantido e a semana fica pendente. O servidor rejeita execução em referência futura.
4. Para corrigir uma semana já lançada ou reduzir o acumulado, solicitar PCM/ADMIN_PCM e informar justificativa. A correção cria nova revisão. Uma inserção retroativa não pode superar um valor oficial posterior: conferir e corrigir primeiro as semanas posteriores, com justificativa, se necessário.
5. Baixar o modelo de pesos; preencher os valores conferidos da entressafra anterior. O identificador é **atividade_id**, não o número da O.S.; o comentário da célula identifica O.S., filial, setor e caderno. Não alterar esses IDs.
6. Importar `.xlsx` com aba **Dados**, duas colunas `atividade_id`, `peso`. Até 10.000 linhas, arquivo até 5 MB (também sujeito aos limites PHP do ambiente), sem fórmulas. Conferir a prévia: importar não salva nem aprova. Pode editar um peso ou vários na mesma tabela; a pesquisa local filtra setor, caderno e O.S.
7. Salvar versão de rascunho com nome. Somente ADMIN_PCM pode marcar “aprovar e congelar”; exige pesos positivos para todas as atividades da entressafra. Pesos ausentes permanecem pendentes; nunca recebem um peso igual automático. A soma é exibida e usada como denominador da normalização.
8. Metas: usar modelo `referencia` (texto AAAA-MM-DD), `percentual`. Preencher todas as semanas, valores acumulados não decrescentes entre 0 e 100, terminando em 100. O botão de sugestão linear apenas preenche uma proposta para revisão. Salvar e aprovar explicitamente como ADMIN_PCM.
9. Reprogramar ou revisar pesos criando outra versão; as anteriores permanecem. No Controle PCM pode escolher versões antigas e um corte de data/hora de registro (São Paulo) para reproduzir os dados conhecidos naquele momento. Sem corte, mostra a última revisão oficial de cada semana.
10. Conferir indicadores e valores do gráfico na tabela acessível. O.S. fechadas no TOTVS continuam vinculadas e podem ter seu avanço físico conferido, independentemente da situação de abertura da O.S.

O lançamento físico não consulta nem escreve no TOTVS. Não altera apontamentos de horas nem encerra O.S.

## Cálculos e escopo

Percentuais usam escala 0..100, duas casas decimais. Realizado = soma(peso × percentual acumulado) / soma(pesos). Os pesos pertencem às atividades, com chaves estrangeiras; cada O.S. entra uma vez, pelo caderno e setor executante original. Associações adicionais de setores ao caderno não multiplicam o peso.

Cada agrupamento usa o denominador de suas próprias atividades; o consolidado da entressafra é calculado novamente pelas O.S. únicas. Não soma percentuais de grupos. Uma nova O.S. sem peso torna a realização ponderada do escopo pendente até nova versão aprovada; o lançamento percentual continua permitido.

A programação oficial é da entressafra completa. Nos filtros por setor/caderno/O.S., o realizado do recorte é exibido, mas a meta geral não é atribuída automaticamente ao recorte. Assim, programado/desvio do recorte ficam pendentes, sem inventar metas setoriais. Os indicadores individuais e a consolidação por grupos continuam disponíveis.

Tendência: estimativa pela taxa de avanço dos últimos registros semanais observados (até quatro referências), limitada a 0..100. Requer pelo menos duas referências com atualização real e pesos aprovados. Sem dados suficientes não é desenhada. Não altera programação oficial.

## Permissões e integridade

- RESPONSAVEL_SETOR: evolução/histórico apenas das atividades de seus setores executantes; não configura calendário, pesos ou metas, nem retifica lançamentos oficiais.
- PCM: consulta geral, configuração, rascunhos e correções justificadas.
- ADMIN_PCM: também aprova pesos e programação.

Lotes transacionais (até 200 alterações por requisição), chave idempotente por usuário, revisão esperada obrigatória e bloqueio da entressafra no PostgreSQL evitam sobrescrita silenciosa. Falha em qualquer linha reverte o lote inteiro. O histórico usa revisões append-only; triggers PostgreSQL rejeitam UPDATE/DELETE em registros, versões, pesos, metas e recibos de lote. Dados e versões não possuem exclusão automática.

## Migrations e arquivos

Aplicadas no banco exclusivo `pcm_controle_entressafra`:

- `20261022090000_CreateEvolucaoFisica`: semanas, lotes idempotentes, revisões de percentual e versões.
- `20261022090100_ProtectEvolucaoVersions`: pesos/metas com chaves estrangeiras e proteção do histórico.

```powershell
php bin/cake.php migrations status
php bin/cake.php migrations migrate
```

Criados: `src/Service/Evolucao.php`, `EvolucaoExcel.php`; `src/Controller/EvolucaoController.php`; seis modelos `src/Model/Table/Evolucao*Table.php`; quatro telas `templates/Evolucao/`; `templates/element/evolucao_filters.php`; `webroot/js/evolucao.js`; `tests/TestCase/Service/EvolucaoTest.php`; `tools/review_time_entries.php`, `verify_evolucao_postgres.php`, `verify_evolucao_http.php`, `test_evolucao_js.js`; este documento e as duas migrations.

Alterados: `config/routes.php`, `templates/layout/pcm.php`, `composer.json`/`composer.lock` (PhpSpreadsheet 5.10), testes existentes `EtapaUmTest`, `EtapaDoisTest`, `BulkLinksTest` para limpeza das novas dependências em SQLite e menu da Etapa 3. Nenhum serviço TOTVS foi alterado.

## Verificação

```powershell
php vendor/bin/phpunit --no-progress
php tools/review_time_entries.php
php tools/verify_evolucao_postgres.php
php tools/verify_evolucao_http.php
```

PHPUnit usa SQLite isolado. A verificação PostgreSQL cria fixtures dentro de transação e termina em rollback, incluindo versões aprovadas e testes de bloqueio/imutabilidade. O teste HTTP utiliza o servidor local e remove seus usuários temporários; somente consulta as novas telas. Nenhum percentual, peso ou meta fictício fica no banco operacional.

Revisão operacional: 3 apontamentos existentes, nenhuma sobreposição, exclusão PostgreSQL preservada. O apontamento **#25** possui **1.801 minutos (30h01)** e deve ter suas datas conferidas pelo PCM. Não foi corrigido automaticamente. O Controle PCM mostra o aviso e o link autorizado para conferência.

Resultado da validação: **146 testes / 901 asserções** no PHPUnit; **11 verificações** de eventos JavaScript (lote, zero explícito, limpeza, cancelamento de confirmação, preservação de outras linhas e aviso de alterações pendentes); testes PostgreSQL e HTTP aprovados; sintaxe PHP de 117 arquivos aprovada; Composer sem avisos de vulnerabilidades. As duas migrations constam como aplicadas. A verificação PostgreSQL também comparou hashes dos cadernos, atividades, apontamentos e auditorias antes/depois do rollback, sem divergências. O PHP emite um aviso já existente de carregamento duplicado de OpenSSL; não foi alterada a configuração global do ambiente.

Pendências para liberação operacional: confirmar calendário, importar/conferir pesos reais, aprovar pesos e metas e conferir #25. A aparência final e o uso em celular devem ser conferidos no navegador; testes HTTP e testes de eventos JavaScript não substituem essa inspeção visual.

## Ajuste de apresentação do acumulado — 22/10/2026

A tabela pergunta “Quanto do serviço está concluído agora? (%)”, com campo numérico, última porcentagem registrada e diferença calculada durante a digitação. O valor enviado continua sendo o total acumulado, para uma ou várias O.S. A diferença não é somada novamente ao total nem enviada como dado confiável ao backend.

Exemplo validado: acumulados 20, 30, 55, 80, 100 produzem diferenças de 20, 10, 25, 25, 20 p.p. Repetir o acumulado na referência seguinte confirma a verificação com diferença zero e remove a pendência; deixar vazio não cria registro, conserva o último valor e mantém a pendência. Reduções continuam restritas ao PCM, com motivo e auditoria. A Curva S usa os acumulados e os pesos aprovados.

Alterados somente a tela `templates/Evolucao/index.php`, seu script `webroot/js/evolucao.js`, os testes `tools/test_evolucao_js.js` e `tests/TestCase/Service/EvolucaoTest.php` e este documento. Sem migration ou alteração dos registros existentes. Validação: **148 testes / 924 asserções**, **13 verificações JavaScript**, sintaxe do template e HTTP autenticado nos três perfis aprovados.

## Atualização semanal opcional — 22/10/2026

Não há obrigação de atualizar todas as O.S. na segunda-feira. Registre somente informações reais. O lote grava exclusivamente as linhas preenchidas ou confirmadas; as demais não recebem registros artificiais. Uma O.S. sem histórico vale 0%; sem nova medição, conserva o último acumulado, por quantas semanas forem necessárias. Confirmar o mesmo acumulado registra a conferência com variação zero.

O indicador **Sem atualização na semana** é informativo, independente das categorias sem início (0%), em andamento (maior que 0% e menor que 100%) e fisicamente concluída (100%). Uma atividade pode estar concluída e, ao mesmo tempo, sem atualização naquela semana. Esse indicador não bloqueia os demais lançamentos nem a Curva S: com pesos aprovados, todas as O.S. do escopo entram no cálculo com seu acumulado conhecido, inclusive zero. A situação do TOTVS e a passagem do tempo não geram execução física.

A lógica existente do serviço já atendia a essas regras; foram esclarecidos os textos, o indicador neutro e o filtro nas telas de evolução/controle, e acrescentados testes para semanas inteiras sem atualização, O.S. nunca iniciadas, confirmação sem avanço, gravação HTTP parcial e preservação da conclusão sem atualização. Sem alteração de schema, dados existentes ou outros módulos.

Validação deste ajuste: **151 testes / 954 asserções aprovados**, com banco de testes isolado, e sintaxe PHP das três views alteradas aprovada.
