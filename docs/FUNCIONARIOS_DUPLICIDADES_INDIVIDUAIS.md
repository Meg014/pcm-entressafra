# Matrículas duplicadas — bloqueio individual

## Comportamento atual

As duas pendências conhecidas da família 010 não bloqueiam mais os demais
funcionários. A pesquisa usa somente RA_MAT, RA_NOME, RA_CODFUNC e RA_SITFOLH
de DADOSP12.dbo.SRA010, com parâmetros, debounce e limite já existentes.

Para cada sugestão, o SQL conta todos os registros NORMAL daquela matrícula
na fonte, independentemente do nome pesquisado ou do limite de sugestões.
Um segundo registro com outro nome, fora dos resultados ou além da página
continua tornando a matrícula ambígua. O relatório global de pendências não
participa mais da liberação de funcionários sem duplicidade.

- NORMAL com matrícula não vazia e exatamente um registro elegível: selecionável.
- Matrícula com mais de um registro NORMAL: seleção bloqueada, sem chave liberada.
- FÉRIAS e demais situações: continuam bloqueadas pela política cadastral.
- Matrícula vazia ou sem identificação confirmada: bloqueada individualmente.

Aviso exibido na própria sugestão e na mensagem da pesquisa:

> Esta matrícula possui mais de um registro no TOTVS. Solicite a conferência do PCM.

Registros duplicados não são unidos e não recebem identificadores inventados.
Uma linha não pode ser escolhida arbitrariamente entre duas correspondências.

## Confirmação no salvamento

O serviço refaz SELECT parametrizado pela matrícula, somente entre os registros
NORMAL, com TOP (2). Exige exatamente uma correspondência. Duas correspondências
geram o aviso de duplicidade; nenhuma correspondência impede o apontamento.
Assim, uma matrícula que se tornou duplicada depois da seleção é recusada.

A matrícula permanece string, preservando zeros à esquerda. A chave de conflito
continua sendo a matrícula confirmada; os dados do formulário não substituem
a consulta do backend.

A validação global e a restrição PostgreSQL de exclusão de intervalos continuam
intactas, entre todos os setores e O.S. Intervalos consecutivos são permitidos.
O histórico anterior não é reescrito. A proteção contra mistura com chaves
históricas incompatíveis, quando existentes, permanece conforme o relatório
de compatibilidade anterior.

## Lista para o PCM

Abra **Apontamentos → Pendências de identificação de funcionários**:

`http://localhost:8766/funcionarios/pendencias`

Somente PCM e ADMIN_PCM podem acessar. A lista mostra matrícula, quantidade
de registros NORMAL e motivo da pendência. Não mostra nomes, função ou outros
dados pessoais que não sejam necessários à conferência. Não há botões para
editar o TOTVS, escolher um registro duplicado ou juntar cadastros.

A lista mostra até 50 grupos e avisa se houver mais. Esse limite não afeta a
checagem individual, que considera toda a fonte para a matrícula consultada.
A resposta não permite cache. Em caso de falha de conexão, a tela informa
indisponibilidade em vez de afirmar ausência de pendências.

## Verificações reais

Na conexão SQL Server utilizada pela aplicação foram confirmados:

- Pesquisa real por nome e matrícula, com resultados limitados e sem expor
  dados pessoais nos relatórios.
- Confirmação de um funcionário NORMAL com matrícula única mesmo com as
  duas duplicidades ainda presentes na fonte.
- Recusa individual de cada matrícula duplicada, tanto na pesquisa quanto
  na confirmação do backend.

O teste real não inseriu apontamentos com dados de funcionários. Gravações e
conflitos foram verificados com mocks no banco de testes e com fixtures próprias
no teste de concorrência PostgreSQL, removidas ao final.

## Testes automatizados

Resultado: **63 testes, 396 assertions aprovados**. O teste de concorrência
PostgreSQL com dois processos independentes também passou, e Composer
validate --strict foi aprovado.

Testes cobrem consulta mista com matrícula única, duplicadas e FÉRIAS; alerta
individual; recusa de seleção arbitrária; nova duplicidade após seleção;
salvamento de funcionário único apesar de outras pendências; conflito entre
setores/O.S.; intervalos consecutivos; e restrição de acesso à lista do PCM.
Os testes anteriores de autenticação, permissões, snapshots, cancelamento e
auditoria permanecem na suíte.

Ferramentas atualizadas:

```powershell
php tools/verify_employee_autocomplete.php
php tools/verify_employee_uniqueness.php
php vendor/phpunit/phpunit/phpunit
```

O verificador de pendências pode retornar código 1 ao encontrar casos para
conferência. Isso não representa bloqueio global dos outros funcionários.

## Arquivos principais

- src/Integration/Totvs/Queries.php: contagem por matrícula na busca.
- src/Integration/Totvs/Employees.php: elegibilidade e confirmação individual.
- src/Controller/FuncionariosController.php e templates/Funcionarios/pendencias.php.
- src/Controller/ApontamentosController.php: aviso de pendências nos resultados.
- webroot/js/employee-autocomplete.js: bloqueio e aviso por sugestão.
- config/routes.php, link em Apontamentos e testes correspondentes.

Não houve alteração de schema, regravação de registros existentes, escrita
no TOTVS ou mudança em outro projeto. Porcentagens e Curva S não foram implementadas.
Validação visual do fluxo JavaScript no navegador permanece manual.
