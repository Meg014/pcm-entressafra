# PCM_ControleEntressafra

Navegação atual: escolha de entressafra na entrada, contexto de sessão, datas e cadernos em Setores e menu administrativo simplificado. Veja [como conferir](docs/NAVEGACAO_E_CONTEXTO_ENTRESSAFRA.md).

Regra atual da Curva S: área principal pelo caderno, equipe executante independente, vínculo por lista e demonstração isolada. Veja [CURVA_CADERNOS_E_LISTAS.md](docs/CURVA_CADERNOS_E_LISTAS.md).

Operação por setor, cadernos opcionais e evolução sem confirmação de calendário: veja [como validar as telas e o banco](docs/SETORES_CADERNOS_CALENDARIO.md).

## Etapa 2 — situação atual

**Cadernos e setores:** relacionamento muitos-para-muitos, com identificação
do setor executante por O.S. e permissões independentes das demais associações
do caderno. Veja [estrutura, migration e testes](docs/CADERNOS_MULTISETOR.md).

**Vinculação em lote:** PCM e administradores podem acessar
`/vinculos-lote` pelo botão em Atividades / O.S. Serviços dinâmicos, prévia,
seleção entre páginas, confirmação de quantidade, processamento retomável e
auditoria estão disponíveis. Veja [como usar e validar](docs/VINCULOS_LOTE.md).

As pesquisas de Novo Apontamento consultam diretamente o TOTVS. Funcionários
são pesquisados por nome sem diferenciar maiúsculas/minúsculas ou por matrícula,
utilizando apenas RA_MAT, RA_NOME, RA_SITFOLH e RA_CODFUNC. Somente NORMAL
com matrícula inequívoca pode ser selecionado; duplicidades são bloqueadas
individualmente, sem impedir os demais funcionários.

O.S. são pesquisadas por área/filial dos setores autorizados, com os filtros
operacionais L/N, origem em 2026 e serviço ENTRESSAFRA. Resultados sem vínculo
aparecem com orientação ao PCM e não permitem apontamentos. Setores/cadernos
e vínculos devem ser configurados explicitamente pela interface.

Consulte [diagnóstico, mapeamentos reais e validação no navegador](docs/CONSULTAS_DIRETAS_APONTAMENTOS.md).
As seis áreas confirmadas utilizam filial 01. Não houve cadastro operacional
automático, escrita no TOTVS ou alteração de outros sistemas.

Autenticação, usuários/setores, atividades, apontamentos e auditoria estão
implementados. A trava global de horários permanece no PostgreSQL.
Percentuais e Curva S não fazem parte desta entrega.

Leia [docs/ETAPA2.md](docs/ETAPA2.md) para criar o primeiro administrador.
Etapa 1: cadastros de entressafras, setores e cadernos, com dashboard inicial.
CakePHP 5.4, PHP 8.2+, PostgreSQL e Bootstrap 5.3.3 local.

## Executar localmente

No PowerShell, a partir desta pasta:

```powershell
Set-Location C:\PCM\Sistemas\PCM_ControleEntressafra
composer install
php tools/validate_database.php
php bin/cake.php migrations status --connection default
php bin/cake.php server -H 127.0.0.1 -p 8766
```

Abra http://localhost:8766. A porta 8765 foi preservada para outro sistema PCM.
O servidor de desenvolvimento é interrompido com Ctrl+C.

O banco já foi criado pelo administrador e as quatro migrations desta etapa
já foram aplicadas. Para uma instalação nova, siga docs/BANCO_DE_DADOS.md.
Configure .env usando .env.example; nunca versione credenciais.
config/app_local.php deve existir (copie de config/app_local.example.php
se necessário). DB_NAME só aceita pcm_controle_entressafra.

Para aplicar migrations pendentes somente no banco exclusivo:

```powershell
php bin/cake.php migrations migrate --connection default
```

## Uso inicial

1. Acesse Entressafras e cadastre 2026/2027 com o período real.
2. Cadastre os setores reais da fábrica.
3. Cadastre os cadernos, vinculando cada um à entressafra e ao setor.
4. Use o seletor do dashboard para consultar o período e a quantidade de cadernos.
5. Edite o status da entressafra quando passar de Planejamento para Ativa ou Encerrada.

Nenhum registro de demonstração foi inserido permanentemente.

## Testes

```powershell
php vendor/phpunit/phpunit/phpunit
php tools/verify_postgres.php
composer validate --strict
```

PHPUnit usa SQLite isolado em tmp/entressafra_tests.sqlite. A verificação
PostgreSQL faz escritas temporárias e reverte todas em uma transação; execute
somente no ambiente local de validação. Não executa alterações de schema.

## Arquivos principais

- config/Migrations/20261020150000_CreateEntressafras.php
- config/Migrations/20261020150100_CreateSetores.php
- config/Migrations/20261020150200_CreateCadernos.php
- config/Migrations/20261020150300_AddPostgresIntegrityChecks.php
- src/Model/Table/{Cadastro,Entressafras,Setores,Cadernos}Table.php
- src/Model/Entity/{Entressafra,Setor,Caderno}.php
- src/Controller/{Cadastros,Entressafras,Setores,Cadernos,Dashboard}Controller.php
- templates/Cadastros/{index,form,view}.php
- templates/Dashboard/index.php e templates/layout/pcm.php
- webroot/css/pcm.css e webroot/css/bootstrap.min.css
- tests/TestCase/Controller/EtapaUmTest.php
- tools/{validate_database,verify_postgres}.php
- config/routes.php, config/bootstrap.php e config/app_local.example.php
- .env.example e .env local ignorado pelo Git

Arquivos ajustados do esqueleto: AppController (layout), elementos Flash
(acessibilidade) e .gitignore. O teste da página padrão CakePHP foi substituído
pelos testes dos fluxos reais. Nenhum projeto externo foi alterado.

Veja docs/ARQUITETURA.md para regras e limites das evoluções futuras e
docs/VALIDACAO_ETAPA1.md para resultados e roteiro de validação manual.
Importação Excel, atividades/O.S., TOTVS, programação, Curva S, tendência e
dashboard gerencial não estão implementados nesta etapa.
