# Etapa 2 — operação, segurança e integração

**Cadernos multissetoriais:** a associação com setores agora é muitos-para-muitos.
Cada O.S. mantém seu próprio setor executante e área original TOTVS; permissões
e apontamentos são validados por atividade. Coluna antiga e históricos preservados.
Veja [CADERNOS_MULTISETOR.md](CADERNOS_MULTISETOR.md) para migration e validação.

**Acessos reorganizados:** cadastros e Sair estão no menu aberto ao clicar no
nome do usuário. Vincular O.S. em Lote está nesse menu para PCM/ADMIN_PCM e em
destaque na página Atividades / O.S. Rota: `/vinculos-lote`.
Veja [ACESSOS_MENU.md](ACESSOS_MENU.md) para permissões e validação no navegador.

**Filtro atual de funcionários:** o autocomplete retorna somente NORMAL
(RA_SITFOLH em branco) e FÉRIAS (F). Férias continuam bloqueadas para apontamento;
D e quaisquer outros códigos são excluídos no SQL e novamente no serviço backend.
A situação NORMAL e a unicidade da matrícula são reconfirmadas ao salvar.

**Vincular O.S. em Lote:** disponível para PCM e administradores em
`/vinculos-lote`, com serviços do TOTVS, prévia sem gravação, seleção entre
páginas e confirmação explícita. Consulte [VINCULOS_LOTE.md](VINCULOS_LOTE.md).

Atualização de 21/10/2026: consulte [consultas diretas no Novo Apontamento](CONSULTAS_DIRETAS_APONTAMENTOS.md)
para a causa da busca vazia por Maria, a correção de maiúsculas/minúsculas,
a validação HTTP real, os mapeamentos área/filial e o procedimento no navegador.
Funcionários utilizam somente os quatro campos autorizados; não é necessário
cadastrá-los no PostgreSQL. Apenas matrículas ambíguas e situações não NORMAL
são bloqueadas. O.S. sem vínculo são mostradas e não podem receber apontamentos.

As notas de configuração inicial abaixo devem ser lidas junto à atualização
acima, que substitui as antigas pendências de acesso a colunas de funcionários.

## Estado da entrega

Autenticação, usuários/setores, atividades, apontamentos, consulta PCM,
auditoria e exclusão de intervalos concorrentes estão implementados.
A integração de consulta com TOTVS foi validada com a credencial da aplicação.
Funcionários e O.S. são consultados diretamente; as autorizações, situações
cadastrais e vínculos ao caderno continuam obrigatórios. Mocks são usados
somente nos testes automatizados, além de consultas reais de validação.

Não foram copiadas credenciais nem alterados arquivos do projeto de referência.
As consultas reais de validação executam exclusivamente SELECT.

## Conexão TOTVS: campos a preencher

O `.env` local e `.env.example` receberam:

| Variável | Situação inicial |
| --- | --- |
| PROTHEUS_DB_HOST | Preencher servidor |
| PROTHEUS_DB_PORT | 1433; ajustar se necessário |
| PROTHEUS_DB_DATABASE | Preencher banco |
| PROTHEUS_DB_USERNAME | Preencher conta própria somente leitura |
| PROTHEUS_DB_PASSWORD | Preencher senha própria, sem enviá-la no chat |
| PROTHEUS_DB_ENCRYPT | true |
| PROTHEUS_DB_TRUST_SERVER_CERTIFICATE | false |

A estrutura técnica foi conferida no `config/app.php` e no driver
`ProtheusReadOnly` do PCM-Solicitacao-OS: driver SQL Server separado,
Encrypt/TrustServerCertificate, login timeout de 5 segundos, query timeout,
sem persistência, sem log SQL e sem cache de metadados.
Não copie automaticamente configurações de confiança de certificado de outros
ambientes. Ajustes devem corresponder ao certificado do servidor utilizado.

Após a configuração e confirmação pelo usuário, a inspeção preparada é:

```powershell
php tools/inspect_totvs.php
php tools/inspect_totvs.php dbo NOME_REAL_DA_TABELA
```

O primeiro comando consulta até 500 tabelas do catálogo; o segundo consulta
até 500 colunas da tabela informada, com schema e tabela como parâmetros.
O driver aceita somente esses dois SELECTs exatos. Execução direta e SQL
fora da lista são bloqueados. Falhas são exibidas sem detalhes de conexão.
A credencial SQL Server deve ter somente SELECT; não usar conta administrativa.

### Tabelas encontradas apenas na referência

- `STJ010`: TJ_ORDEM, TJ_FILIAL, TJ_CODBEM, TJ_SITUACA, TJ_SERVICO,
  TJ_CCUSTO, TJ_CODAREA, TJ_OBSERVA e D_E_L_E_T_.
- `ST9010`: T9_CODBEM, T9_FILIAL, T9_NOME e D_E_L_E_T_.
- `ST4010`: T4_SERVICO, T4_FILIAL, T4_NOME e D_E_L_E_T_.

Essas informações são evidência do código da referência, **não validação do
esquema deste ambiente**. Ainda não são usadas em consultas pelo novo projeto.
Tabela de funcionários, matrícula, filial, vínculo, situação e eventual centro
de custo ainda não foram identificados. Nenhum nome foi presumido.

O contrato `GatewayInterface` exige chaves inequívocas de O.S. e funcionário,
confirmadas novamente no servidor ao salvar. O adaptador atual falha de forma
explícita. Só depois da validação devem ser adicionados SELECTs parametrizados,
com limites e identificação completa, à allowlist e ao adaptador.

## Banco PostgreSQL

Migrations aplicadas somente em `pcm_controle_entressafra`:

- `20261021120000_CreateOperacaoEntressafra`: usuarios, usuarios_setores,
  atividades, apontamentos e auditorias, com índices e FKs RESTRICT.
- `20261021120100_ProtectApontamentos`: extensão confiável btree_gist no banco
  exclusivo, CHECKs e restrição `ex_apontamento_funcionario_horario`.

Atividades guardam a chave completa da O.S., número, filial, descrição, bem,
situação consultada, snapshot, usuário e timestamps. Entressafra e setor são
obtidos pelo caderno, sem duplicação dessas chaves. Um caderno que já possui
atividades não pode mudar de entressafra/setor pela aplicação.

Uma chave de O.S. só pode ter um vínculo. Se já estiver em outro caderno,
o sistema bloqueia e solicita revisão do PCM. A definição de um procedimento
para múltiplos vínculos intencionais permanece pendente de validação funcional.

Apontamentos possuem identidade completa do funcionário (chave, nome,
matrícula, filial e vínculo), atividade, datas/horas UTC, minutos calculados,
descrição, cancelamento, controle PCM, autores e timestamps. Snapshot preserva
funcionário, O.S., filial, serviço, bem, setor, caderno e entressafra.

## Regra global de horários

O backend verifica `inicio_existente < fim_novo AND fim_existente > inicio_novo`,
por chave inequívoca de funcionário, em todos os setores e O.S. Somente ativos
participam. Na edição, exclui o próprio ID. Todos os conflitos são exibidos com
nome/matrícula, data/horários, O.S., setor, caderno e serviço. O formulário
preserva os valores para correção.

O PostgreSQL aplica EXCLUDE USING gist sobre `funcionario_chave =` e
`tsrange(inicio, fim, '[)') &&`, com predicado `NOT cancelado`. A proteção vale
também em gravações concorrentes; não depende do frontend. Intervalos
consecutivos são permitidos. CHECKs exigem fim posterior ao início e duração
positiva consistente com os minutos. O erro concorrente 23P01 é traduzido para
o mesmo aviso de conflito depois do rollback.

Início e fim são datas completas em America/Sao_Paulo, convertidas para UTC.
São aceitos serviços que atravessam a meia-noite. A precisão é de minutos.
SQLite cobre regras de aplicação; a exclusão concorrente é testada no PostgreSQL.

## Perfis e política inicial de correções

- ADMIN_PCM: cadastros, usuários e seus setores, todas as atividades/apontamentos.
- PCM: cadastros operacionais, consulta global, correção, cancelamento e controle PCM.
- RESPONSAVEL_SETOR: consulta dos setores autorizados, vínculo de O.S. e novos
  apontamentos apenas nesses setores. Não administra cadastros ou usuários.

**Decisão conservadora para validação:** responsáveis não corrigem nem cancelam
apontamentos até definição explícita da política de autorização. PCM/admin
informam motivo obrigatório. Não há exclusão física pela interface.
Cancelamento e confirmações são auditados e transacionais. Correções preservam
identidades históricas de O.S./funcionário e retornam o controle PCM a Pendente.
Para corrigir uma identidade errada, cancelar e criar um apontamento confirmado.
Essa política também precisa de validação funcional.

Os controles Pendente, Conferido e Lançado manualmente são internos. Não há
qualquer escrita ou procedure no TOTVS. Totais por funcionário e O.S. excluem
cancelados. O histórico mantém antes/depois, autor, data e motivo.

## Primeiro administrador

Nenhum usuário ou senha administrativa foi criado automaticamente. Para criar
a conta real, execute no PowerShell dentro da pasta do projeto:

```powershell
$env:PCM_ADMIN_NAME = Read-Host 'Nome do administrador'
$env:PCM_ADMIN_LOGIN = Read-Host 'Login do administrador'
$adminSecret = Read-Host 'Senha' -AsSecureString
try {
    $env:PCM_ADMIN_PASSWORD = [System.Net.NetworkCredential]::new('', $adminSecret).Password
    php bin/cake.php create_admin
} finally {
    Remove-Item Env:\PCM_ADMIN_NAME, Env:\PCM_ADMIN_LOGIN, Env:\PCM_ADMIN_PASSWORD -ErrorAction SilentlyContinue
    $adminSecret = $null
}
```

O comando só cria o primeiro administrador se ainda não houver um ativo.
Senhas são protegidas com password_hash/password_verify e nunca entram na
auditoria. Login tem bloqueio temporário após cinco falhas, sessão renovada na
entrada, expiração por inatividade de 30 minutos e revalidação do usuário ativo
em cada request. Alterar a senha invalida sessões anteriores. CSRF está ativo;
logout e operações de escrita exigem POST. DebugKit foi desabilitado para não
deixar suas rotas de diagnóstico acessíveis fora do controle de acesso.

Depois do login, abra **Usuários → Cadastrar usuário**, informe nome, login,
senha própria e perfil. Para RESPONSAVEL_SETOR marque os setores autorizados.
Não use senhas de funcionários do TOTVS. Desative contas pela edição, sem
excluir seus históricos. O administrador não pode remover seu próprio acesso.

## Acesso e verificação

```powershell
php bin/cake.php server -H 127.0.0.1 -p 8766
```

- http://localhost:8766/login
- /atividades e /atividades/add
- /apontamentos e /apontamentos/add
- /consulta-pcm (PCM e ADMIN_PCM)
- /usuarios (ADMIN_PCM)

A sessão é obrigatória nas rotas da Etapa 1 e da Etapa 2. O menu respeita o
perfil. Os dados reais de O.S. dependem dos mapeamentos e vínculos autorizados;
funcionários não exigem cadastro local prévio.

Testes locais:

```powershell
php vendor/phpunit/phpunit/phpunit
composer validate --strict
php bin/cake.php migrations status --connection default
```

Concorrência PostgreSQL, somente no ambiente local exclusivo:

```powershell
$env:PCM_RUN_CONCURRENCY_TEST = '1'
try { php tools/verify_concurrency.php }
finally { Remove-Item Env:\PCM_RUN_CONCURRENCY_TEST }
```

Esse teste usa dois processos PHP e o serviço real de apontamentos com mock do
TOTVS. Cria fixtures identificadas, confirma uma gravação e o bloqueio da outra,
e remove somente os registros criados pelo próprio teste no finally. Nenhum
dado preexistente é modificado. Não executar em produção com usuárias operando.

## Arquivos principais

- `src/Integration/Totvs`: contrato e adaptador bloqueado; allowlist de catálogo.
- `src/Database/Driver/ProtheusReadOnly.php`: segunda conexão protegida.
- `src/Service/Access.php`, `Users.php`, `Activities.php`, `TimeEntries.php`,
  `ConflictException.php`: permissões, usuários, vínculos, horas e auditoria.
- Controllers `Auth`, `Usuarios`, `Atividades`, `Apontamentos` e base `Operacao`.
- Tables `Usuarios`, `UsuariosSetores`, `Atividades`, `Apontamentos`, `Auditorias`.
- `src/Command/CreateAdminCommand.php` e templates correspondentes.
- `tools/inspect_totvs.php`, `verify_concurrency.php` e `concurrency_worker.php`.
- `tests/Support/FakeTotvs.php` e `tests/TestCase/Controller/EtapaDoisTest.php`.

Importação Excel permanece apenas como evolução futura: o vínculo de atividade
está centralizado no serviço, que poderá ser chamado pelo importador após a
validação do esquema e das regras de duplicidade. Percentuais, pesos, metas,
Curva S, tendência, exportação gerencial e escrita no TOTVS não foram implementados.
