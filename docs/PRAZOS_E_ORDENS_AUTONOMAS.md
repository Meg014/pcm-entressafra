# Prazos por área e O.S. sem caderno obrigatório

## Diagnóstico confirmado

Consulta exclusivamente SELECT no TOTVS confirmou a O.S. **005300**, filial **01**, área executante **ELETRI**, serviço antigo **2425EL**, origem **17/09/2026**, situação **L**, término **N**, serviço ENTRESSAFRA inequívoco. Ela já estava no PostgreSQL e no caderno ELÉTRICA. Sua rejeição na pesquisa operacional era causada pela entressafra **2026/2027 em planejamento**, não pela ausência de caderno.

A busca operacional agora consulta o TOTVS por áreas executantes autorizadas. Ao selecionar uma O.S. aberta elegível, um POST autenticado com CSRF revalida a identidade e cria/localiza seu registro local transacionalmente, sem exigir caderno. Mantém família 010, filial 01, origem em 2026, situação L, término N e serviço ENTRESSAFRA, incluindo códigos antigos. Não usa correspondência de centro de custo para inventar classificação física ou caderno.

## Datas oficiais configuradas

Uma única entressafra 2026/2027 foi mantida. O término geral foi ajustado para **10/04/2027**; o prazo padrão foi cadastrado em **26/03/2027**. Calendários físicos iniciais:

| Escopo | Encerramento | Início |
| --- | --- | --- |
| Padrão / demais áreas classificadas | 26/03/2027 | A conferir |
| Moenda | 10/04/2027 | A conferir |
| Tratamento de Caldo | 26/03/2027 | A conferir |
| Destilaria | 26/03/2027 | A conferir |

O início geral já existente foi preservado, mas não foi copiado como início conferido dos calendários. Nenhum peso ou percentual fictício foi criado.

## Separação das classificações

- **Setor executante:** equipe identificada por TJ_CODAREA/filial, usada na autorização e nos apontamentos. Os mapeamentos atuais foram conferidos: MECANI, ELETRI, CALDEI, CALD, DESTIL e TRATA.
- **Área da fábrica:** localização/classificação operacional física, registrada separadamente em cada O.S., para resolver os prazos.
- **Caderno:** agrupamento administrativo opcional, que continua com relacionamento muitos-para-muitos com setores.
- **O.S.:** identidade local por entressafra, filial e número, preservando zeros à esquerda; não se duplica por caderno ou associação de setores.

Moenda foi encontrada como caderno (MOENDA e CALDEIRARIA - MOENDA), não como setor executante. Tratamento de Caldo e Destilaria aparecem tanto como setores quanto como cadernos. O campo antigo `area_fisica` dos cadernos estava vazio. Portanto, **nenhuma O.S. foi classificada silenciosamente por nome do caderno nem toda a Mecânica/Caldeiraria recebeu prazo de Moenda**. A classificação física permanece para conferência do PCM. Se houver confirmação expressa de que um caderno é integralmente de uma área, pode-se classificar suas O.S. em lote pela tela abaixo.

## Configurar e visualizar pela interface

Abra **menu do usuário → Prazos e áreas da fábrica**, ou:

`http://localhost:8766/prazos-entressafra`

1. Escolha a entressafra. Confira primeiro suas datas gerais no cadastro de Entressafras, caso o início geral também precise de correção.
2. Em cada prazo, informe a data inicial realmente conferida, marque **Início conferido pelo PCM** e salve. A data final oficial já está preenchida.
3. O sistema gera segundas-feiras, a referência inicial parcial e a **data efetiva de encerramento parcial**: 26/03/2027 para o padrão/Caldo/Destilaria e 10/04/2027 para Moenda. Preserva referências históricas já confirmadas.
4. Na tabela de classificação, filtre por caderno para facilitar a conferência, selecione somente as O.S. verificadas e escolha a área física. Confirme a operação. A seleção não troca o setor executante.
5. Cadastre outras áreas físicas quando necessário. Áreas classificadas sem sobrescrita própria usam o prazo padrão. **O.S. sem classificação física e sem calendário executante explicitamente validado ficam com o prazo programado pendente**, evitando antecipar Moenda por engano.
6. Use um calendário por **setor executante** somente quando o PCM confirmar que aquele prazo vale para a equipe inteira. Um calendário específico de área física tem prioridade sobre o calendário de equipe.
7. O campo de caderno de destino é opcional e só preenche O.S. ainda sem caderno, compatíveis com entressafra e setor. Não transfere as já vinculadas. Também é possível usar a vinculação individual/em lote existente para classificar posteriormente uma O.S. local sem caderno.

## Programação, Curva S e realização

Em `/evolucao/configuracao`, selecione a entressafra e o **calendário de área/prazo**. O modelo Excel de metas respeita o calendário selecionado. Informe e confira metas acumuladas, finalizando em 100% na data efetiva daquele prazo; ADMIN_PCM aprova e congela uma versão. A linha geral legada não pode ser aprovada como substituta dos novos prazos quando há calendários operacionais configurados.

Não foi criada uma distribuição automática oficial. Sem início conferido ou sem programação aprovada para o respectivo prazo, a programação permanece pendente. As versões anteriores continuam preservadas.

Em `/controle-pcm`, a programação consolidada usa, para cada O.S., a meta de seu prazo/área e os pesos aprovados. Depois do encerramento de um prazo, sua última meta é mantida; a Moenda segue sua própria programação até 10/04. Um teste isolado validou 80% de programação para Moenda em 26/03, 100% para outra área nessa data e 100% para Moenda em 10/04, sem forçar conclusão física.

**Sem pesos aprovados, a Curva S ponderada oficial continua pendente.** As contagens e proporções não ponderadas e o percentual individual continuam disponíveis. Não é usada média simples como curva oficial.

Clique no histórico de uma O.S. (`/evolucao/historico/{id}`) para ver seu prazo, a programação individual da área e os acumulados informados. A programação individual não depende de inventar um peso. As responsáveis continuam informando somente o acumulado; o prazo não aumenta esse valor, não obriga lançamentos e não encerra O.S. no TOTVS. Referências parciais de outras áreas não são tomadas como referência automática da O.S. classificada.

## Operação sem caderno

- `/apontamentos/add`: O.S. diretamente do TOTVS, funcionário diretamente do TOTVS e campos de data/horas já simplificados. Ao salvar, a situação/identidade da O.S. é novamente conferida no fluxo operacional; conflitos globais e proteção dos confirmados permanecem.
- `/lancar-porcentagem`: seleção da O.S. elegível e criação/localização do registro local sem caderno. Histórico, acumulados e controle de revisão mantidos.
- `/atividades` e `/evolucao`: incluem O.S. sem caderno; filtros por setor usam a equipe executante original.
- O.S. já encerradas continuam localmente, com apontamentos, percentuais, pesos e histórico. Não são excluídas nem zeradas por sair da busca de novas O.S. abertas.

**Pendência operacional atual:** a entressafra ainda está em planejamento. Ativá-la deve ser uma decisão explícita do PCM em Entressafras. A operação exige exatamente uma ativa; o diagnóstico não alterou esse status silenciosamente. Os inícios e as classificações físicas ainda precisam de conferência; não foram presumidos.

## Banco, preservação e verificações

Migration aditiva aplicada: **20261023090000_OrdensAutonomasCalendarios**. Acrescenta área física e entressafra direta na atividade, permite caderno nulo, migra a entressafra dos vínculos atuais, preserva cadernos/associações e cria integridade por entressafra/filial/número. Acrescenta `areas_fabrica`, `calendarios_operacionais`, o prazo padrão da entressafra e a referência ao calendário nas versões de metas. Nenhum histórico foi removido; a trava PostgreSQL de sobreposição permanece.

Principais arquivos: `src/Service/Calendarios.php`, `Activities.php`, `OperacaoRapida.php`, `Evolucao.php`, `TimeEntries.php`, `BulkLinks.php`; controllers `Prazos`, `Operacional`, `Operacao`, `Evolucao`, `Apontamentos`; modelos/associações e templates administrativos/operacionais; rotas e autocomplete de O.S. Modelos de metas Excel agora seguem o calendário escolhido.

```powershell
php bin/cake.php migrations status
php vendor/bin/phpunit --no-progress
php tools/diagnose_os_calendar.php
php tools/verify_ordens_prazos.php
php tools/verify_operacao_http.php
```

O teste PostgreSQL cria uma entressafra ativa temporária **dentro de transação revertida**, consulta a 005300 real por SELECT e valida a identificação sem caderno, a unicidade e os prazos. Não deixa ativa a entressafra operacional, não mantém dados fictícios e compara hashes dos dados anteriores antes/depois. Os testes HTTP removem somente seus próprios usuários temporários. A classificação automática dos cadernos de Moenda não foi executada enquanto sua abrangência aguarda confirmação.

Resultado: **192 testes / 1.160 asserções aprovados**, incluindo **9 testes específicos de calendários/O.S. autônomas (44 asserções)**. Validados o encerramento parcial, segundas-feiras, inícios pendentes, áreas com prazos diferentes dentro do mesmo caderno/equipe, metas/consolidação sem antecipar Moenda, operação sem caderno, idempotência, classificação posterior, CSRF, permissões e preservação dos históricos. Também aprovados os cenários JavaScript de autocomplete/identificação, o HTTP real (incluindo POST JSON com CSRF), a consulta real 005300 e os testes PostgreSQL em rollback. Sintaxe PHP e migration verificadas. A inspeção visual final deve ser realizada no navegador.
