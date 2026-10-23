# Cadastro de cadernos por setor principal da Curva S

O cadastro em `/cadernos/add` exige somente **Nome** e **Setor principal da Curva S**. A entressafra vem da sessão e o caderno novo fica ativo. Não há seleção obrigatória de equipe executante, área física ou outros campos. O mesmo setor principal pode receber vários cadernos. A edição utiliza o mesmo formulário simples.

## Causa e correção

O cadastro anterior exigia a associação manual com `setores`, que representa equipes executantes. Os sete setores registrados pelo usuário pertencem a `areas_fabrica`, que representa a apresentação da Curva S. Eram conceitos diferentes, mas o formulário dependia do primeiro para permitir criar um caderno.

A migration aditiva `20261024090000_CadernosSetorPrincipal` permite que `cadernos.setor_id`, referência executante legada, fique nulo. O campo existente `cadernos.area_curva_id` passa a ser a escolha principal da administração. As associações executantes históricas e suas FKs permanecem válidas. Nenhum setor ou período foi excluído ou recriado.

Foi acrescentado `areas_fabrica.entressafra_id` para identificar o período do setor. Na base existente, com uma única entressafra, os sete setores foram associados a esse período sem mudar IDs, nomes ou outros valores anteriores. Para bases com mais de um período, somente associações inequívocas por calendários podem preencher esse campo; setores legados compartilhados continuam reconhecidos nos períodos de seus calendários. Novos setores administrativos recebem o período da sessão. O formulário não permite selecionar um setor de outro período.

O modelo valida nome, setor principal e compatibilidade com a entressafra. O controlador aceita somente nome e setor principal; campos legados forjados no POST não permitem pular a obrigatoriedade. A associação de apresentação `cadernos_areas` é mantida ao salvar. Chamadores legados internos com equipe explícita continuam compatíveis, preservando as integrações existentes; a nova interface sempre exige o setor principal.

Administradores e PCM podem abrir o caderno mesmo antes de existir qualquer equipe executante associada. Contas operacionais continuam restritas às equipes autorizadas. A listagem e seu filtro agora mostram o setor principal da Curva S.

## Equipe executante automática

O fluxo permanece **abrir caderno → Vincular O.S. → colar números → Conferir → Confirmar vínculos**, na rota `/cadernos/{id}/vincular-lista`.

A equipe executante é identificada pelo par original `STJ010.TJ_FILIAL` / `STJ010.TJ_CODAREA`, obtido na consulta somente leitura da O.S. O sistema reutiliza a identificação local quando ela existe. Se ainda não existir e o código retornado for válido, a prévia permite o vínculo sem criar cadastros; somente após confirmação e revalidação é registrada a equipe local e sua associação ao caderno, com auditoria. A identificação é exibida pelo próprio código, como `TOTVS 01 / CALD`, sem inventar um nome de equipe não fornecido pela fonte.

Um lock PostgreSQL pelo par filial/código evita a criação concorrente da mesma equipe. Cadastros inativos, identidades ambíguas e divergências em equipes históricas continuam pendências, sem alteração silenciosa. Prévia, reload e paginação não criam equipes ou atividades. O código executante original e o snapshot TOTVS ficam preservados na O.S.

As novas O.S. herdam a classificação do caderno pela regra existente `COALESCE(atividade.area_curva_id,caderno.area_curva_id)`, sem inferir setor de apresentação a partir da equipe executante. Assim, uma O.S. de CALD vinculada a CALDEIRARIA - MOENDA entra em MOENDA quando esse é o principal do caderno. A consulta oficial continua contando cada atividade uma vez. Os históricos existentes não foram reclassificados nesta correção.

## Validação e preservação

Suíte completa aprovada: **234 testes e 1.775 asserções**, registrada em `tmp/caderno-principal-junit.xml`.

`tools/verify_caderno_principal_http.php` acessa o formulário real com uma sessão administrativa e verifica as duas únicas entradas obrigatórias, as opções disponíveis e a ausência do seletor executante. Na base do usuário, confirmou os sete setores existentes com seus IDs preservados:

| ID | Setor |
|---|---|
| 7 | CALDEIRA |
| 8 | CALDEIRARIA |
| 13 | DESTILARIA |
| 10 | ELÉTRICA |
| 11 | INSTRUMENTAÇÃO |
| 9 | MOENDA |
| 12 | TRAT. DE CALDO |

Essa verificação não salva um caderno e compara hashes de todas as 21 tabelas antes/depois. `tools/verify_curva_integridade.php` também confirmou que os dados de todas as colunas anteriores das 17 tabelas monitoradas permaneceram iguais após a migration. A migration acrescenta a informação de período e permite nulo na referência executante legada; não limpa a base.

`CadernosPrincipalTest` usa somente a base isolada SQLite e verifica HTML, criação apenas com nome/setor, detalhe acessível sem equipes, vários cadernos no mesmo setor, preservação dos sete setores, rejeição de setor de outro período e tentativas de substituir o principal por parâmetros legados. Também verifica prévia sem gravação, criação automática de duas equipes simuladas pelo código retornado pela fonte de teste, preservação da equipe e agrupamento da Curva S pelo principal, com confirmação repetida sem duplicidade. Um lançamento de 80% em uma das três O.S. contribui com média física de 26,6667% na Moenda, sem contribuir para Caldeira, independentemente do executante CALD.

## Arquivos

- `config/Migrations/20261024090000_CadernosSetorPrincipal.php`.
- `src/Model/Entity/Caderno.php` e `src/Model/Table/CadernosTable.php`.
- `src/Controller/CadernosController.php`, `CadastrosController.php`, `SetoresController.php` e `OrganizacaoController.php`.
- `src/Service/Access.php`, `SetoresApresentacao.php` e `ListLinks.php`.
- `templates/Cadastros/form.php`, `index.php` e `view.php`.
- `tests/TestCase/Controller/CadernosPrincipalTest.php`.
- `tests/TestCase/Controller/EtapaUmTest.php` e `EtapaDoisTest.php`: atualização das expectativas antigas do formulário, preservando os testes de relações históricas e permissões.
- `tools/verify_caderno_principal_http.php` e este documento.
