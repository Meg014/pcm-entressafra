# Novo Apontamento — pesquisa dinâmica de O.S. e funcionários

## Implementação

Em `/apontamentos/add`, a pesquisa de O.S. usa um único campo por número ou
descrição. A busca consulta somente atividades já vinculadas a cadernos no
PostgreSQL, limitadas aos setores autorizados e aos filtros de entressafra,
setor e caderno. Cadernos inativos não aparecem. A resposta tem no máximo
20 sugestões e indica quando é necessário refinar a busca.

As sugestões mostram número/filial, serviço, bem, setor, caderno e situação
armazenada na última consulta TOTVS. Selecionar preenche o ID interno da
atividade e exibe resumo. Não há atualização automática da situação TOTVS
nesta busca; ela usa o snapshot da atividade cadastrada.

O.S. sem resultado não é vinculada automaticamente. A mensagem orienta que,
se ela existir no TOTVS, seja vinculada antes pelo cadastro Atividades / O.S.
A busca não afirma a existência de uma O.S. externa que não foi consultada.

O autocomplete de funcionários consulta o serviço SRA010 já implementado,
com confirmação de filial/matrícula/vínculo e situação no backend. Não utiliza
RA_NOMECMP. O estado das permissões reais está descrito abaixo.

Ambos utilizam debounce de 300 ms, mínimo de 2 caracteres, requisições
canceláveis, proteção contra respostas antigas, estados de carregamento,
ausência de resultado e erro. Permitem clique, setas/Enter, Esc e botão
Limpar. A limpeza afeta apenas a seleção correspondente. Alterar os filtros
limpa a O.S.; funcionário, descrição e horários são preservados.

Os seletores redundantes de O.S. e os botões de pesquisa não fazem mais parte
do formulário. O layout e os demais campos foram mantidos.

## Segurança

- GET `/apontamentos/ordens` e `/apontamentos/funcionarios` exigem autenticação.
- O.S. são filtradas por setor no servidor, antes de limitar e retornar resultados.
- Consultas ORM e SQL usam parâmetros; não concatenam o texto pesquisado.
- JSON não pode ser armazenado em cache; textos das sugestões usam textContent.
- O backend refaz a leitura da atividade/caderno, verifica autorização e
  consistência dos filtros, e recusa IDs inexistentes ou texto livre.
- A seleção de funcionário é confirmada novamente no TOTVS pelo vínculo completo.
- O bloqueio global de horários e a restrição PostgreSQL continuam ativos.
- Não há escrita no TOTVS, alteração de outro projeto ou migration nesta mudança.

## Validação

- **56 testes, 348 assertions aprovados.**
- Novos testes de pesquisa de O.S. por número/descrição, filtro de caderno,
  isolamento por setor, termo inválido, autenticação, ausência de resultados,
  limite/paginação, caderno inativo, SQL Injection e IDs/filtros adulterados.
- Mantidos os testes de funcionários por nome/matrícula, homônimos, zeros à
  esquerda, situação cadastral, falha de consulta e identificação confirmada.
- Testes existentes de edição, cancelamento, auditoria e conflitos globais aprovados.
- Teste PostgreSQL com dois processos independentes aprovado: uma gravação
  aceita e outra bloqueada. Fixtures criadas pelo próprio teste foram removidas.
- Sintaxe PHP dos arquivos principais e Composer validate --strict aprovados.

## Pendências reais

A credencial da aplicação foi novamente testada. RA_NOME, RA_MAT, RA_CODFUNC
e RA_SITFOLH estão autorizados. **RA_FILIAL, R_E_C_N_O_ e D_E_L_E_T_ ainda
retornam SELECT negado (SQL Server 230)**. Assim, o autocomplete de funcionários
mostra erro de indisponibilidade em produção e não permite salvar identidade
incompleta. É necessário resolver essas três permissões para a operação real.

O serviço de vínculo de novas O.S. no TOTVS mantém sua pendência de validação
anterior; a pesquisa local de atividades cadastradas é independente dele.

Não havia ferramenta de automação de navegador disponível nesta execução.
Seleção por mouse/teclado, limpeza, preservação visual dos outros campos e
responsividade foram implementadas, mas precisam de validação manual no
navegador. Os testes automatizados cobrem backend e renderização das telas,
sem alegar execução dos cliques JavaScript.

Roteiro manual: abrir Novo Apontamento com login, digitar parte de uma O.S.
cadastrada, escolher uma sugestão, alterar filtros, testar Limpar e verificar
que horários/descrição/funcionário não se perdem. Após corrigir as permissões
TOTVS, repetir com funcionário NORMAL, homônimos e funcionário em férias.

## Arquivos principais

- `src/Controller/ApontamentosController.php`: endpoint e restauração da O.S.
- `src/Service/TimeEntries.php`: confirmação de ID e filtros ao salvar.
- `config/routes.php`: rota autenticada de busca local de O.S.
- `templates/element/order_autocomplete.php`, `employee_autocomplete.php`.
- `webroot/js/order-autocomplete.js`, `employee-autocomplete.js`, `apontamentos.js`.
- `templates/Apontamentos/form.php` e testes em `EtapaDoisTest.php`.

Porcentagens e Curva S não foram implementadas.
