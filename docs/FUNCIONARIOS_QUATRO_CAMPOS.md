# Funcionários: consulta simplificada e controle de unicidade

## Resultado real — 21/10/2026

O serviço consulta exclusivamente **DADOSP12.dbo.SRA010** usando RA_MAT,
RA_NOME, RA_CODFUNC e RA_SITFOLH. Os quatro SELECTs individuais e as pesquisas
parametrizadas por nome e matrícula foram executados pela credencial da aplicação.
O autocomplete retornou resultados reais. Nenhuma credencial ou nome pessoal
foi exibido no terminal ou nos relatórios.

Não existe mais dependência de RA_FILIAL, R_E_C_N_O_, D_E_L_E_T_ ou
RA_NOMECMP nas consultas de funcionários. Não há solicitação de aumento de
permissões para essa funcionalidade. As outras famílias não são consultadas.

## Habilitação de apontamentos: BLOQUEADA POR DUPLICIDADES

A verificação global entre registros em situação NORMAL encontrou:

| Matrícula mascarada | Registros NORMAL |
| --- | ---: |
| ****25 | 2 |
| ****91 | 2 |

As matrículas são mascaradas somente no relatório. Os registros originais
continuam separados no TOTVS. Não foi inventado um identificador ou unificado
um cadastro para contornar a ambiguidade. Esses casos devem ser analisados
pelo PCM/TI antes de habilitar novos apontamentos.

A pesquisa permanece disponível para visualização. Enquanto existir qualquer
grupo ambíguo/vazio, todos os resultados são não selecionáveis, sem chave
liberada para gravação; a interface informa o bloqueio. O backend repete a
verificação e bloqueia também tentativas manuais de enviar matrículas.

A consulta de duplicidades agrupa todas as matrículas NORMAL na fonte 010,
sem amostrar as linhas. A saída é limitada a 51 grupos, suficiente para
bloquear assim que uma duplicidade for detectada. Os dois grupos encontrados
não atingiram esse limite. Zeros à esquerda são preservados; apenas espaços
finais do campo SQL são removidos. Matrículas vazias também bloqueiam a habilitação.

Para reproduzir a análise no SSMS com uma conta autorizada, sem consultar
colunas adicionais:

```sql
SELECT TOP (51) RTRIM(RA_MAT) AS matricula, COUNT_BIG(*) AS quantidade
FROM DADOSP12.dbo.SRA010
WHERE LTRIM(RTRIM(RA_SITFOLH)) = ''
GROUP BY RTRIM(RA_MAT)
HAVING COUNT_BIG(*) > 1 OR RTRIM(RA_MAT) = ''
ORDER BY RTRIM(RA_MAT);
```

O sistema não altera nem corrige o TOTVS. Mesmo que as quatro colunas sejam
iguais em duas linhas, não se presume que elas representem o mesmo vínculo.

## Identificação após unicidade confirmada

A matrícula será usada diretamente como `funcionario_chave`, sem conversão
numérica e sem gerar um ID substituto. Essa chave só é disponibilizada após
uma verificação sem duplicidades. Na gravação, o serviço repete a checagem
global e confirma exatamente um registro NORMAL pela matrícula parametrizada.
Se aparecer uma duplicidade depois da seleção, o salvamento é recusado.

Nome e função enviados pelo navegador não são usados como cadastro. São
confirmados novamente no SQL Server. A situação em branco é NORMAL; F é
exibida como FÉRIAS e bloqueada. Outros códigos são exibidos sem significado
presumido e não permitem novos apontamentos.

Os campos legados do PostgreSQL permanecem compatíveis: filial não consultada
é armazenada como string vazia, com `filial_consultada=false` no snapshot.
Isso não é uma filial inferida do TOTVS. O campo legado de vínculo guarda
a própria matrícula, identificada no snapshot como `matricula_010`; nenhum
novo código de vínculo é fabricado. O snapshot também guarda a origem 010,
nome, função e situação consultados.

## Histórico e trava global

Antes da alteração, foi confirmada a ausência de apontamentos no PostgreSQL
deste ambiente. Nenhuma linha histórica foi alterada ou excluída; não houve
migration de dados nem remoção de restrições.

Como proteção para ambientes com histórico, novos apontamentos nessa estratégia
são bloqueados se houver registros ativos com a chave anterior diferente da
matrícula armazenada. Nesse caso é necessária análise explícita; o código não
reescreve chaves nem mistura esquemas de identificação silenciosamente.

A exclusão PostgreSQL `ex_apontamento_funcionario_horario` permanece intacta,
assim como a validação global no backend. Com a matrícula confirmada como
única, o bloqueio continua entre todos os setores e O.S. Intervalos consecutivos
são permitidos; sobreposições parciais, totais e idênticas são bloqueadas.

## Interface

Pesquisa automática a partir de 2 caracteres, debounce de 300 ms e limite de
20 sugestões. As sugestões mostram nome, matrícula e situação. Seleção por
clique ou teclado preenche nome, matrícula, função e situação. Filial não é
mais exibida como campo a preencher nem como informação presumida.

Com as duplicidades atuais, a busca real funciona, mas os resultados ficam
desabilitados e a mensagem orienta análise pelo PCM. Férias e outras situações
continuam bloqueadas independentemente da unicidade.

## Testes realizados

- **60 testes e 377 assertions aprovados.**
- Nome parcial, matrícula, função, zeros à esquerda e homônimos.
- NORMAL, FÉRIAS e outros códigos não elegíveis.
- Duplicidades bloqueando seleção, confirmação e tentativa de envio manual.
- Nova checagem após a seleção e rejeição de resultado inexistente/ambíguo.
- Ausência das quatro colunas removidas nas consultas operacionais.
- Preservação de registros com identificação anterior e bloqueio de mistura.
- Conflitos entre setores/O.S., intervalos consecutivos e demais testes anteriores.
- Autocomplete real no SQL Server por nome/matrícula e bloqueio real da gravação
  por duplicidade, sem inserir qualquer dado operacional.
- Concorrência PostgreSQL em dois processos independentes aprovada. Fixtures
  temporárias criadas pelo teste removidas; registros preexistentes preservados.
- Composer validate --strict aprovado.

A interação visual do autocomplete no navegador permanece para validação
manual. A execução real verificada foi a consulta do mesmo serviço utilizado
pelo endpoint e a aplicação do bloqueio no backend.

## Comandos de verificação

```powershell
php tools/validate_employee_access.php
php tools/verify_employee_uniqueness.php
php tools/verify_employee_autocomplete.php
php vendor/phpunit/phpunit/phpunit
```

O verificador de unicidade retorna código 1 enquanto houver casos para análise.
Isso representa o bloqueio esperado, não falha de conexão. O relatório usa
matrículas mascaradas; o SELECT acima permite à equipe autorizada analisar os
casos completos diretamente na fonte.

Nenhum outro projeto ou banco foi alterado. Não houve escrita no TOTVS,
implementação de porcentagens ou Curva S.
