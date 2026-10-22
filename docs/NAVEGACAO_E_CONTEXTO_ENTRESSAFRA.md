# Navegação administrativa e contexto de entressafra

Esta entrega reorganiza o fluxo de trabalho. As fórmulas da Curva S, médias, pesos, histórico semanal e trava global de horários permanecem as existentes.

## Como conferir

1. Entre com ADMIN_PCM. O login abre **Escolher entressafra**, em `/escolher-entressafra`. Escolha o período e clique em **Entrar na entressafra**. PCM também usa o contexto administrativo de período.
2. Navegue por Controle PCM, Cadernos, Setores, atividades e apontamentos. O período permanece na sessão e aparece no cabeçalho com suas datas. Não é preciso selecioná-lo novamente nas páginas. Use **Trocar entressafra** para acessar outro período.
3. No menu do usuário, abra **Setores**. A lista principal mostra os setores/áreas de apresentação da curva. Em **Editar datas e cadernos**, confira início, término e as associações com cadernos da entressafra selecionada.
4. Em **Cadernos**, abra um cadastro e use o botão destacado **Vincular O.S. por lista**. Cole os números, clique em **Conferir O.S.**, selecione os resultados e use **Confirmar vínculos**. Nenhum seletor de equipe executante, serviço, centro de custo ou equipamento é exigido nesse fluxo.
5. Abra **Controle PCM / Curva S**. O período vem da sessão; escolha apenas a área de apresentação quando necessário. Indicadores, gráfico e relação de dez O.S. por página continuam disponíveis sem aprovação semanal de calendário ou pesos.
6. Use **Trocar entressafra** e escolha uma encerrada. Consultas e históricos permanecem disponíveis; novos lançamentos e alterações operacionais são bloqueados no servidor e na interface.
7. Entre com uma conta Computador. Ela usa automaticamente a única entressafra operacional ativa e não oferece troca. Setores e funções continuam limitados às permissões da conta.

Criar uma nova entressafra leva à tela de escolha, para que sua entrada nesse contexto seja explícita. Entressafras cadastradas, incluindo encerradas, aparecem na seleção; nenhuma nova entressafra operacional foi criada permanentemente nesta entrega.

## Menu administrativo

O menu ADMIN_PCM contém somente:

- Entressafras.
- Setores.
- Cadernos.
- Usuários e permissões.
- Todas as atividades / O.S.

As funções anteriores continuam acessíveis:

| Função secundária | Acesso atual |
| --- | --- |
| Vinculação por serviço | Cadernos → Ferramentas avançadas |
| Evolução em lote | Lançar porcentagem → Lançamento em lote |
| Prazos e áreas da fábrica | Setores → Configurações avançadas |
| Classificação principal da curva | Cadastro do caderno ou suas referências no cadastro do setor |
| Consulta retroativa e versões | Controle PCM → Administração → Análise e versões históricas |
| Equipes executantes e mapeamento TOTVS | Setores → Configurações avançadas |

As rotas `/vinculos-lote`, `/evolucao`, `/prazos-entressafra`, `/organizacao-curva`, `/evolucao/analise` e os cadastros antigos de equipes em `/setores/view/{id}` e `/setores/edit/{id}` foram preservados. `/setores?equipes=1` abre a lista das equipes executantes. O cadastro da área de apresentação usa `/setores/curva/{id}`, com o identificador de `areas_fabrica`.

## Datas e cadernos no setor

O setor exibido na lista principal é a **área de apresentação da Curva S**, independente da equipe executante `TJ_CODAREA`.

As datas são armazenadas na configuração administrativa já existente de `calendarios_operacionais`, por entressafra e área. Não há um prazo compartilhado automaticamente entre todos os anos de um mesmo setor.

Para 2026/2027, na ausência de configuração específica, os valores de término apresentados para conferência são os informados pela gestão: **Moenda 10/04/2027**, demais áreas **26/03/2027**. Datas já configuradas têm prioridade. O início vem somente de um registro existente ou do que o PCM informar; não é copiado do início geral para preencher um campo vazio.

Ao salvar um início explicitamente informado/conferido, a rotina administrativa de calendário já existente continua sendo utilizada. Essa configuração permanece independente da permissão para lançar horas ou percentuais. Nenhuma fórmula nova foi implementada.

O prazo é cadastrado por área de apresentação. A tela não atribui a data da Moenda à equipe MECANI, ELETRI ou CALDEI e não altera automaticamente a localização física das O.S.

O novo relacionamento `cadernos_areas` permite vários cadernos por área e várias áreas por caderno, sempre na mesma entressafra. As associações existentes entre cadernos e equipes executantes em `cadernos_setores` foram mantidas.

**Associação administrativa não altera a classificação principal da curva.** A classificação principal continua sendo `atividades.area_curva_id`, quando definida, ou a herança de `cadernos.area_curva_id`. Uma O.S. continua contabilizada uma única vez no consolidado. Quando houver múltiplas associações, o PCM pode abrir **Classificação principal** no caderno para conferir sua definição, usando a prévia e auditoria existentes. Remover uma associação que ainda é principal exige resolver antes essa classificação, sem transferências implícitas.

## Contexto e validação no servidor

A sessão guarda `Periodo.id` e o usuário proprietário da escolha. Novo login limpa a escolha anterior; sair encerra a sessão. A troca de período também descarta a prévia administrativa de classificação anterior.

Cada requisição autenticada valida a conta, resolve o contexto e confere identificadores de entressafra, caderno, atividade, calendário e versões. Alterar query strings, campos ocultos, IDs em URLs ou dados do formulário não permite acessar ou gravar em outra entressafra. As rotas antigas passam pelas mesmas validações.

Tabelas operacionais com entressafra recebem escopo de leitura e validação de gravação. Consultas SQL dos relatórios/evolução também conferem o período. O contexto em memória é limpo ao terminar a requisição, inclusive quando há erro ou redirecionamento, para não contaminar outra sessão ou execução.

Entressafras encerradas são somente leitura para seus dados operacionais. Cadastros globais de usuários/permissões e a criação de uma nova entressafra continuam sendo administração global. Nomes das áreas e mapeamentos das equipes TOTVS também são referências compartilhadas; **datas, cadernos, vínculos, históricos e execução** usam o período escolhido.

As contas Computador ignoram uma escolha administrativa antiga na sessão e resolvem a ativa a cada requisição. Se não existir exatamente uma ativa, o servidor informa conflito e não permite operar com um período arbitrário.

A consulta de conflitos de horários continua deliberadamente global, incluindo todo o histórico ativo e outras entressafras do mesmo funcionário. O contexto limita as telas e o destino da gravação, mas não esconde conflitos históricos. A constraint PostgreSQL de sobreposição foi mantida.

## Migração e preservação

Migration aplicada: **20261023110000_CadernosAreasApresentacao**.

Ela cria somente `cadernos_areas`, com unicidade caderno/área e chave estrangeira composta para garantir que o caderno e a associação pertençam à mesma entressafra. Inicializa essa associação apenas onde já havia uma classificação principal explícita de caderno, sem inferir vínculos pelo nome.

Não altera datas existentes, pesos, metas, apontamentos, percentuais, equipes executantes, classificações principais ou auditorias anteriores. Não apaga registros nem modifica o TOTVS. O manifesto de integridade confirmou todos os registros e colunas anteriores em 17 tabelas após a migração.

## Testes executados

- Suíte completa: **225 testes aprovados**, incluindo os cenários anteriores de vínculos por lista, cálculos, contagem única, permissões e históricos.
- Dez testes específicos de navegação/contexto: **122 asserções**, cobrindo escolha, persistência, troca, CSRF, ID cruzado em URL/query/formulário, encerradas, Computador na ativa, escopo ORM/SQL, datas e relacionamentos muitos-para-muitos.
- HTTP real: escolha por POST e CSRF, navegação sem reinformar período, troca, encerradas, falsificação de período/ID, setores/datas, rotas antigas e Computador. Fixtures removidas e hashes de **19 tabelas**, incluindo usuários e a nova associação, preservados.
- Verificações HTTP anteriores de apontamentos, evolução, modelos Excel e Controle PCM nos três perfis aprovadas.
- Verificação PostgreSQL/TOTVS em rollback aprovada: lista parametrizada real, identidade única, equipes diferentes na Moenda e resultados das curvas anteriores preservados.
- Seleção e edição da Moenda renderizadas e inspecionadas no navegador. Capturas em `tmp/periodo-selecao.png`, `tmp/periodo-setores.png` e `tmp/periodo-setor-moenda.png`.

```powershell
php bin/cake.php migrations status
php vendor/phpunit/phpunit/phpunit --no-progress
php vendor/phpunit/phpunit/phpunit tests/TestCase/Controller/PeriodosNavegacaoTest.php --no-progress
php tools/verify_periodo_http.php --preview
php tools/verify_curva_integridade.php
php tools/verify_curva_reorganizacao.php
php tools/verify_operacao_http.php
php tools/verify_evolucao_http.php
php tools/verify_curva_http.php
```

O teste HTTP cria apenas usuários e metadados próprios de teste, sem execução fictícia oficial; remove-os ao terminar e compara os dados anteriores. Os testes de integração com TOTVS fazem somente SELECT.
