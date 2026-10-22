# Funcionários — autocomplete e confirmação no backend

Implementação e validação em 21/10/2026, somente no PCM_ControleEntressafra.

## Resultado da conexão real da aplicação

SELECTs explícitos confirmaram acesso a **RA_MAT, RA_NOME, RA_CODFUNC e
RA_SITFOLH** em dbo.SRA010. As pesquisas diagnósticas por parte do nome e
matrícula foram executadas com parâmetros e limites, utilizando esses quatro
campos. Nenhum registro pessoal ou credencial foi impresso ou salvo em relatório.

As negativas SQL Server 230 permanecem em:

- **RA_FILIAL**: filial do vínculo;
- **R_E_C_N_O_**: identificador do registro/vínculo;
- **D_E_L_E_T_**: filtro de exclusão lógica.

A consulta operacional completa foi tentada pela credencial da aplicação e
continua bloqueada por essas permissões. O acesso autorizado no SSMS aos
quatro campos não concede automaticamente acesso aos identificadores acima.

**O autocomplete está implementado, mas não retorna funcionários selecionáveis
no ambiente real até essas três permissões serem resolvidas.** Ele exibe uma
mensagem de indisponibilidade, sem permitir apontamento com nome livre ou com
filial presumida. Não se contornou nenhuma permissão. A família é somente 010.

RA_NOMECMP não aparece em nenhuma consulta executável do projeto. RA_NOME é
o nome cadastral de exibição, limitado a 30 caracteres pelo esquema real; não
é possível garantir um nome completo maior do que o armazenado nesse campo.

## Comportamento implementado

Na tela `/apontamentos/add`, o campo Funcionário pesquisa nome ou matrícula
com espera de 250 ms, aborta buscas anteriores, ignora respostas antigas e
permite selecionar por clique ou setas/Enter. Mostra até 20 resultados e avisa
quando é necessário refinar o termo. A resposta não pode ser armazenada em cache.

Cada resultado apresenta nome, matrícula, filial e situação. Na seleção são
preenchidos nome, matrícula, filial, código da função, situação e a chave interna.
Alterar o texto limpa a seleção. Homônimos são diferenciados por matrícula e
filial; matrículas continuam strings para preservar zeros à esquerda.

O endpoint GET `/apontamentos/funcionarios?q=...` exige sessão autenticada,
valida o termo (2 a 60 caracteres), retorna JSON e usa consultas parametrizadas
com TOP (21), exibindo 20 resultados. Caracteres especiais de LIKE são tratados
como texto. SQL fora da allowlist e outras famílias são rejeitados.

O nome completo da referência visual solicitada é aproximado pelo nome
disponível em RA_NOME; o código não consulta RA_NOMECMP. A implementação do
autocomplete de Bens do PCM-Solicitacao-OS foi apenas lida como referência de
interação, sem modificações naquele projeto.

## Identificação e proteção do apontamento

`Employees` gera uma chave canônica contendo origem SRA010, filial, matrícula
e identificador de registro. O backend decodifica e valida a chave, refaz o
SELECT exato por filial/matrícula/registro, exclui cadastros apagados logicamente
e exige exatamente uma correspondência. Uma matrícula digitada sem seleção,
chave de outra família, chave adulterada ou cadastro inexistente é rejeitado.

Dados de exibição enviados pelo formulário não são usados como cadastro.
Nome, matrícula, função, situação e identificadores persistidos vêm da consulta
confirmada no servidor. A função e a situação ficam preservadas no snapshot
existente do PostgreSQL; não foi necessária migration.

Situações:

- Em branco: NORMAL, elegível para novo apontamento.
- F: FÉRIAS, exibido mas bloqueado até definição do PCM.
- Qualquer outro código: exibido sem interpretação presumida e bloqueado.

A situação em branco foi observada na amostra real limitada a 200 registros.
F não foi observado nessa amostra; sua apresentação e bloqueio foram testados
com mock conforme a regra solicitada pelo PCM. Não se inferiu que todos os
registros reais estejam na mesma situação, nem que códigos diferentes sejam ativos.

A verificação global de horários continua usando a chave inequívoca. Os
apontamentos existentes não foram modificados. Toda gravação operacional
continua exclusivamente no PostgreSQL. O SQL Server recebeu somente SELECTs.

## Testes

- PHPUnit: **52 testes, 312 assertions aprovados**.
- Nome e matrícula, função e zeros à esquerda preservados, homônimos com
  chaves distintas, confirmação por filial/matrícula/registro, limite de resultados.
- NORMAL/FÉRIAS/outros códigos, bloqueio no serviço de apontamento mesmo se
  o frontend enviar `ativo=true`, matrícula inventada e vínculo ambíguo.
- Injeção SQL e curingas, autenticação do endpoint, termos inválidos, JSON
  sem cache e mensagem de erro sem detalhes pessoais ou de conexão.
- Integração do novo mapeamento com o bloqueio global entre O.S./setores.
- Teste real PostgreSQL com dois processos independentes novamente aprovado:
  uma gravação aceita, outra bloqueada; fixtures próprias removidas ao final.
- No SQL Server real: SELECT dos quatro campos e pesquisas diagnósticas
  aprovados; pesquisa operacional completa bloqueada pelas permissões acima.

A interação JavaScript em navegador (clique, teclado e preenchimento visual)
ainda precisa de validação manual: não havia ferramenta de automação de
navegador disponível nesta execução. Testes de domínio e renderização das telas
passaram. A validação visual ponta a ponta depende também das permissões reais.

## Arquivos principais

- src/Integration/Totvs/Employees.php: pesquisa, chave, situação e confirmação.
- src/Integration/Totvs/Queries.php: SELECTs explícitos e allowlist atualizada.
- src/Integration/Totvs/Gateway.php: consulta de funcionários integrada.
- src/Controller/ApontamentosController.php: endpoint JSON autenticado.
- templates/element/employee_autocomplete.php e webroot/js/employee-autocomplete.js.
- src/Service/TimeEntries.php: bloqueio por situação confirmada no servidor.
- tests/TestCase/Integration/Totvs/EmployeesTest.php e testes de controller.

O serviço de O.S. continua com a pendência de validação descrita nos relatórios
anteriores; esta alteração foi restrita à consulta de funcionários. Não houve
implementação de Curva S, percentuais semanais ou escrita no TOTVS.
