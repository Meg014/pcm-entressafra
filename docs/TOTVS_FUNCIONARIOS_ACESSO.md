# Funcionários TOTVS: alternativa com RA_NOME

Validação executada em 21/10/2026, exclusivamente na família 010, com SELECTs
limitados e sem exibir registros pessoais ou credenciais.

## Referência PCM-Solicitacao-OS

A busca direcionada nos fontes não encontrou consultas SRA010/RA_NOME nem
um serviço de pesquisa de funcionários no TOTVS. `UsersController` e
`UsersTable` trabalham com usuários locais, nome, login e setor. O projeto
foi consultado somente para leitura; nenhum arquivo foi alterado.

## SELECTs efetivamente testados

Cada coluna foi consultada individualmente com TOP (1), sem SELECT asterisco.
Os valores retornados foram descartados, sem impressão no terminal.

| Coluna em dbo.SRA010 | Resultado |
| --- | --- |
| RA_NOME | SELECT autorizado |
| RA_MAT | SELECT autorizado |
| RA_SITFOLH | SELECT autorizado |
| RA_FILIAL | SELECT negado, erro SQL Server 230 |
| R_E_C_N_O_ | SELECT negado, erro SQL Server 230 |
| D_E_L_E_T_ | SELECT negado, erro SQL Server 230 |
| RA_DEMISSA | SELECT negado, erro SQL Server 230 |
| RA_CC | SELECT negado, erro SQL Server 230 |
| RA_DEPTO | SELECT negado, erro SQL Server 230 |

As pesquisas reais por nome (LIKE parametrizado, caracteres especiais
escapados) e por matrícula (igualdade parametrizada) retornaram resultados,
com limite de cinco linhas. A amostra foi utilizada somente em memória e
nenhum dado pessoal foi salvo em relatórios, código ou PostgreSQL.

RA_NOME pode ser adotado como nome de exibição, com o limite de 30 caracteres
confirmado no catálogo. Não é nome completo garantido. Nenhum teste acessou
RA_NOMECMP novamente; não é necessário solicitar essa coluna para o nome de
exibição proposto pelo usuário.

## Por que a pesquisa operacional continua bloqueada

Nome e matrícula não bastam para identificar um vínculo com segurança. Mesmo
na família 010, não se deve presumir o valor da filial. Sem D_E_L_E_T_ não é
possível excluir corretamente os cadastros apagados logicamente. Sem o
identificador do registro, ainda não foi validada uma chave estável de vínculo.

As consultas diagnósticas são separadas do Gateway operacional: não alimentam
seletores, não permitem gravar apontamentos e não substituem a confirmação de
identidade no servidor. Não há tentativa de contornar permissões.

## Solicitação mínima à TI

Para o serviço planejado, solicitar SELECT adicional apenas nestas colunas:

- `dbo.SRA010.RA_FILIAL`: identificação da filial.
- `dbo.SRA010.R_E_C_N_O_`: identificação do registro/vínculo.
- `dbo.SRA010.D_E_L_E_T_`: exclusão de registros apagados logicamente.

RA_NOME, RA_MAT e RA_SITFOLH já estão autorizados. Não solicitar acesso amplo
à tabela, escrita, dados pessoais adicionais ou RA_NOMECMP para esta alternativa.

Se forem necessários data de demissão, centro de custo e departamento no
snapshot, as permissões opcionais serão SELECT em RA_DEMISSA, RA_CC e RA_DEPTO.
Até sua autorização, não presumir esses valores. A interpretação de situações
e a unicidade/estabilidade da chave serão validadas antes de habilitar o Gateway.

## Utilitários e testes

Suíte executada: **38 testes, 246 assertions, todos aprovados**, incluindo
allowlist, bloqueio de SQL adicional e consultas diagnósticas com colunas
explícitas. As duas pesquisas também foram verificadas no SQL Server real.

```powershell
php tools/validate_employee_access.php
php tools/verify_employee_search.php
php vendor/phpunit/phpunit/phpunit
```

O primeiro retorna código 1 enquanto houver colunas sem acesso e informa
somente nome de coluna e resultado. O segundo confirma as duas pesquisas
diagnósticas, sem dados pessoais. Toda SQL está em uma allowlist exata;
identificadores arbitrários, SQL adicional e outras famílias são rejeitados.

Nenhuma alteração de permissões ou gravação foi executada no SQL Server.
O Gateway continua bloqueado; a única persistência operacional prevista é no
PostgreSQL exclusivo. Curva S e percentuais semanais não foram implementados.
