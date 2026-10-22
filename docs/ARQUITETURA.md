# Estrutura da Etapa 1

Registro histórico da Etapa 1. A Etapa 2 adicionou autenticação, operação e
auditoria; consulte `ETAPA2.md` para a arquitetura e as pendências atuais.

- `src/Model/Table`: regras, validações e relacionamentos dos cadastros.
- `src/Model/Entity`: campos permitidos para edição; IDs e timestamps não são aceitos por mass assignment.
- `src/Controller/CadastrosController.php`: fluxo compartilhado de listagem e formulário.
- Controllers específicos definem cada cadastro; DashboardController compõe os indicadores.
- `templates/Cadastros`: listagem paginada, formulário e visualização compartilhados.
- `templates/Dashboard`: indicadores e seletor de entressafra.
- `templates/layout/pcm.php`, `webroot/css/pcm.css`: identidade PCM em tons de petróleo, fundo claro e destaque âmbar.
- Bootstrap 5.3.3 distribuído localmente, sem dependência de CDN durante o uso.
- `config/Migrations`: três tabelas e uma migration adicional com CHECKs PostgreSQL.
- `tools`: verificação de conexão e de integridade PostgreSQL.
- `tests`: testes de aplicação em SQLite isolado, mais validação transacional no PostgreSQL real.

## Comportamento

Entressafras possuem vários cadernos; setores possuem vários cadernos;
cadernos pertencem a uma entressafra e um setor obrigatórios. Os vínculos
impedem exclusão dos pais no banco. A interface desta etapa permite cadastro,
edição, consulta e inativação de setores/cadernos, sem exclusão física.

Nomes de setores e entressafras são únicos. Nome de caderno é único dentro
da combinação entressafra/setor. A unicidade segue a comparação padrão do
PostgreSQL, sensível a maiúsculas. Múltiplas entressafras ativas são permitidas;
o dashboard as exibe e seleciona inicialmente a ativa com início mais recente.
Sem ativa, seleciona a entressafra mais recente. A escolha manual vale na URL.

Setores/cadernos inativos continuam acessíveis no histórico e nos seletores.
Datas da entressafra são datas civis. Timestamps são UTC e exibidos em
America/Sao_Paulo. O dashboard distingue total geral de cadernos e total da
entressafra selecionada. Setores são um cadastro global.

## Evoluções reservadas, sem implementação nesta etapa

| Funcionalidade futura | Local previsto | Limite desta etapa |
| --- | --- | --- |
| Importação Excel | src/Service/Importacao | Nenhum parser, upload ou endpoint |
| Atividades e O.S. | Model, migrations e controllers próprios | Nenhuma tabela criada |
| Consulta TOTVS/Protheus | src/Integration/Totvs | Conexão futura separada, somente leitura; sem credenciais atuais |
| Pesos e programação | src/Service/Programacao | Nenhum cálculo ou tabela |
| Curva S e tendência | src/Service/Indicadores | Nenhum cálculo ou gráfico |
| Dashboard gerencial | Controller e templates próprios | Somente indicadores cadastrais disponíveis |

Não há autenticação nesta etapa. O servidor de desenvolvimento escuta somente
em loopback. Publicação e controle de acesso precisarão de escopo próprio.
