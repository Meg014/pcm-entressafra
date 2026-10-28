# Operação simplificada e fila PCM — 22/10/2026

**Atualização de 28/10/2026:** computadores operacionais agora lançam em todos os setores sem vínculo usuário/setor e consultam os próprios apontamentos e insumos. Consulte [Acesso e concorrência operacional](ACESSO_E_CONCORRENCIA_OPERACIONAL.md) para regras atuais, diagnóstico, hospedagem e testes. As observações de implantação abaixo registram o estado histórico de 22/10.

## Telas

Servidor local atual: `http://localhost:8766`.

- `/apontamentos/add`: novo apontamento sem seletores de entressafra, setor ou caderno. Pesquisa O.S. vinculadas no PostgreSQL, limitadas à entressafra ativa e aos setores autorizados; funcionários continuam consultados diretamente no TOTVS. O resumo mostra serviço, Bem e descrição do caderno, quando disponível.
- `/lancar-porcentagem`: tela principal de evolução com pesquisa de O.S., último acumulado, percentual numérico e referência automática. A data vem da última referência disponível até hoje no calendário da entressafra, no fuso São Paulo. Não utiliza slider nem exige observação de rotina.
- `/apontamentos`: histórico de apontamentos autorizado por setor.
- `/evolucao/historico/{atividade_id}`: histórico físico autorizado. A tela simples oferece o link da O.S. selecionada.
- `/fila-pcm`: primeira tela após login PCM/ADMIN_PCM; somente O.S. que aguardam lançamento manual no TOTVS.
- `/fila-pcm/os/{atividade_id}`: histórico completo e confirmação dos apontamentos selecionados.
- `/atividades`: consulta administrativa completa, acessível no menu do usuário e pela fila. Inclui quantidade recebida, horas válidas, horas pendentes/tratadas, último recebimento e último percentual físico.
- `/evolucao`: lançamento em lote secundário, preservado no ambiente PCM e para os perfis legados já autorizados.
- `/controle-pcm`: indicadores e Curva S; sem pesos aprovados, a proporção de O.S. concluídas é identificada como **não ponderada**. Não é usada uma média simples como Curva S oficial. As contagens por setor/caderno continuam disponíveis.

Após salvar na tela simples, os campos ficam prontos para outro lançamento, com mensagem de confirmação. O lançamento em lote original continua preservado.

## Preparação necessária no banco atual

A inspeção encontrou **zero entressafras ativas**, **zero contas marcadas como computador operacional** e **3 apontamentos aguardando tratamento**. As configurações existentes não foram alteradas automaticamente.

1. Como ADMIN_PCM/PCM, editar a entressafra correta e marcá-la **ativa** após a conferência do PCM. Enquanto não houver entressafra ativa, a pesquisa simples não retorna O.S.; isso evita escolher silenciosamente uma entressafra em planejamento ou encerrada.
2. Confirmar o calendário em `/evolucao/configuracao`. As semanas não são criadas silenciosamente durante um lançamento. As O.S. e cadernos devem estar vinculados corretamente e os setores executantes identificados.
3. Como ADMIN_PCM, abrir Usuários e permissões. Criar ou editar a conta denominada **Computador 1**, **Computador 2** etc.; selecionar o perfil interno `RESPONSAVEL_SETOR`, marcar **Computador operacional** e indicar seus setores autorizados. Definir a senha diretamente pela interface. Nenhuma conta ou senha operacional foi criada automaticamente.
4. Testar uma O.S. do setor autorizado, o funcionário e um intervalo real. Nenhum peso é exigido para apontar horas ou informar o acumulado.

## Contas e compatibilidade

Foram reaproveitados os perfis internos existentes:

- `ADMIN_PCM`: ambiente PCM administrativo completo, incluindo usuários e permissões.
- `PCM`: gestão PCM, fila, conferência, correções, configurações já autorizadas. As restrições administrativas já existentes — como administração de usuários e aprovação de pesos/metas exclusivas do ADMIN_PCM — foram preservadas.
- `RESPONSAVEL_SETOR` + marcação **Computador operacional**: apenas Novo Apontamento, Lançar Porcentagem e históricos autorizados. O backend bloqueia páginas administrativas, fila, confirmações e configuração, inclusive por URL direta. Após login vai para Novo Apontamento.
- Contas legadas não marcadas como computador mantêm suas permissões anteriores. Não houve conversão ou exclusão de contas.

A marcação só é válida com perfil `RESPONSAVEL_SETOR` e exige setores autorizados pelo serviço de usuários. O PostgreSQL também restringe a combinação de perfil/marcação. A identificação da conta permanece nos apontamentos, percentuais e auditorias.

## Porcentagem e referências

O valor digitado é o **total acumulado** de 0 a 100. A diferença é calculada automaticamente. O.S. sem histórico continuam em 0%; semanas sem lançamento mantêm o último acumulado. Confirmar o mesmo valor registra conferência com avanço zero. Não é obrigatório atualizar todas as O.S.

O servidor revalida a atividade, a entressafra ativa, o setor autorizado, a referência e a revisão esperada. Não confia nos metadados do navegador. Na tela simples, apenas PCM/ADMIN_PCM escolhem referência retroativa; retificações e reduções continuam exigindo justificativa e auditoria. A opção de lote permite conferência detalhada do calendário. O.S. posteriormente encerradas no TOTVS permanecem com seu histórico e evolução física.

## Fila e confirmação

A fila agrupa por atividade/O.S., sem fundir os apontamentos. Ordena pelo `created` do apontamento pendente mais antigo, não pela data em que o serviço foi executado. Considera `pendente` e `conferido` como ainda aguardando lançamento manual; exclui cancelados e `lancado`.

1. Abrir **Ver apontamentos** na fila.
2. Lançar manualmente no TOTVS os itens efetivamente conferidos.
3. Selecionar somente esses itens e a operação **Marcar selecionados como lançados no TOTVS**.
4. Marcar a confirmação explícita e confirmar. Até 200 apontamentos por operação.

A operação é transacional: valida todos os IDs, a O.S., os status e as revisões. Registra conta PCM, instante, IDs, status anterior/novo e auditoria individual e do lote. Outra edição ou confirmação simultânea invalida a revisão esperada e exige atualizar a página; não sobrescreve silenciosamente.

Itens tratados aparecem em cinza, deixam a fila e permanecem no histórico. Um novo apontamento da mesma O.S. volta à fila independentemente dos anteriores, que permanecem tratados. Selecionar novamente um item já tratado para confirmar é rejeitado. Para corrigir uma confirmação indevida, o PCM usa **Reabrir confirmação**, obrigatoriamente com justificativa. Depois de reaberto, pode corrigir o apontamento com o fluxo já existente e novamente auditado.

Os registros já marcados `lancado` antes desta migration mantêm esse status. Os novos campos de confirmação ficam vazios para esses registros legados; não foram inventados autor ou data. Suas auditorias anteriores permanecem disponíveis.

“Lançado no TOTVS” é uma confirmação interna do PCM, **não** o encerramento da O.S. Nenhum destes fluxos escreve no SQL Server.

## Evolução do banco e arquivos

Migration aplicada: `20261022120000_OperacaoSimplificada`.

- `usuarios.computador_operacional`, padrão falso, preserva os acessos legados.
- `apontamentos.revisao_pcm`, `confirmado_por`, `confirmado_em`, chave estrangeira e índice para a fila.
- Trigger PostgreSQL bloqueia alteração dos dados do apontamento enquanto seu status for `lancado`; é necessário reabrir a confirmação antes de corrigir. A exclusão global por sobreposição de horários permanece ativa.

Sem recriação de tabelas, exclusão de apontamentos ou alteração de status dos dados existentes.

Criados: serviços `OperacaoRapida` e `FilaPcm`, controllers `Operacional` e `FilaPcm`, templates `Operacional/porcentagem`, `FilaPcm/index`, `FilaPcm/view`, elemento `activity_metrics`, script `porcentagem-rapida.js`, teste `OperacaoSimplificadaTest`, ferramentas `verify_operacao_http.php`, `verify_operacao_state.php`, `test_porcentagem_rapida_js.js`, esta documentação e a migration.

Adaptados: rotas, autenticação/destino de login, autorização central de computador, serviço de usuários/formulário/listagem, `TimeEntries`, telas/controllers de apontamentos e atividades, autocomplete de O.S., menu, CSS local, indicadores PCM, testes de menu/fluxo legado e ferramenta de verificação PostgreSQL. A implementação da consulta SQL Server permanece inalterada e somente leitura.

## Verificações

```powershell
php bin/cake.php migrations status
php vendor/bin/phpunit --no-progress
php tools/verify_operacao_state.php
php tools/verify_operacao_http.php
php tools/verify_evolucao_postgres.php
php tools/verify_employee_autocomplete.php
```

Os testes automatizados usam SQLite isolado. O teste PostgreSQL termina em rollback e compara hashes dos cadernos, atividades, apontamentos e auditorias antes/depois. O teste HTTP cria apenas usuários temporários identificados e os remove ao terminar. Os apontamentos de teste já existentes no banco operacional não são apagados. O teste real de funcionários usa exclusivamente SELECT e não imprime informações pessoais.

Os testes JavaScript usam DOM simulado para seleção, limpeza, teclado, ausência de filtros, debounce e cálculo do acumulado; a inspeção visual final em computador/celular deve ser feita no navegador.

Resultado final: **165 testes / 1.052 asserções aprovados**; sintaxe de **125 arquivos PHP** aprovada; **8 verificações JavaScript** da tela simples aprovadas e cenários dos dois autocompletes validados com e sem filtros visuais. HTTP real, PostgreSQL (incluindo trigger contra edição de confirmado), migrations e consulta real de funcionários no SQL Server aprovados. Hashes e contagens dos dados anteriores preservados na verificação em rollback. A migration consta como aplicada. Nenhum percentual ou apontamento fictício foi mantido.

## Data e horário por teclado — 22/10/2026

Novo Apontamento e edição usam três campos de texto: **Data do serviço (DD/MM/AAAA)**, **Hora inicial (HH:MM)** e **Hora final (HH:MM)**. As máscaras aceitam digitação e colagem: `22102026` → `22/10/2026`, `0730` → `07:30`. Tab continua com a navegação normal e a duração é exibida automaticamente.

**Termina no dia seguinte** fica desmarcado em um novo formulário. Ao editar, reflete o intervalo já armazenado. Somente essa opção faz o servidor calcular a data final como o dia seguinte; uma hora final menor não ativa a virada automaticamente. Datas reais, horas de 00:00 a 23:59 e duração positiva são novamente validadas no backend, mesmo sem JavaScript. Valores inválidos/conflitantes ficam no formulário para correção.

O PostgreSQL continua recebendo `inicio` e `fim` completos em UTC, com interpretação dos campos no fuso São Paulo. O novo formato prevalece sobre eventuais valores antigos de início/fim enviados pelo navegador. A interface de serviço antiga foi preservada para chamadas existentes. A trava global, exclusão PostgreSQL, exibição de todos os conflitos, autorização e proteção dos apontamentos confirmados permanecem inalteradas. Sem migration ou alteração de registros existentes/TOTVS.

Arquivos deste ajuste: `templates/Apontamentos/form.php`, `webroot/js/apontamentos.js`, `src/Service/TimeEntries.php`, testes `tests/TestCase/Controller/EtapaDoisTest.php`, `tests/TestCase/Service/TimeEntryInputTest.php`, `tools/test_apontamento_masks_js.js` e esta documentação. Resultado: **183 testes / 1.114 asserções**, **17 verificações JavaScript** e sintaxe PHP aprovados. Cobertura inclui máscaras, digitação incremental, colagem inteira/parcial, Tab, datas e horas inválidas, ano bissexto, edição, conservação dos valores após erro, virada de mês/ano, conflitos entre O.S./setores e intervalos consecutivos/em dias diferentes.
