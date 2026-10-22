# Consultas diretas no Novo Apontamento — 21/10/2026

## Diagnóstico e correção

A credencial da aplicação confirmou SELECT em RA_MAT, RA_NOME, RA_SITFOLH
e RA_CODFUNC de DADOSP12.dbo.SRA010. A busca anterior comparava RA_NOME
diretamente com LIKE. No SQL Server real, `Maria` retornou zero registros,
`MARIA` retornou 21 e `maria` retornou zero. A correção usa
`UPPER(s.RA_NOME) LIKE UPPER(:nome)` com parâmetro e escape de curingas.
As três formas agora produzem os mesmos resultados na API HTTP autenticada.
Registros NORMAL aparecem primeiro; FÉRIAS (F) permanecem visíveis e bloqueadas.
D e outros códigos são excluídos no SQL e no serviço backend. O servidor
retorna até 20 sugestões e avisa quando é preciso
refinar a busca. Não é necessário cadastrar funcionários no PostgreSQL.

São usados exclusivamente os quatro campos autorizados. Não há leitura de
RA_FILIAL, R_E_C_N_O_, D_E_L_E_T_ ou RA_NOMECMP nessa funcionalidade.
Nome cadastral, matrícula, função e situação são somente leitura na tela;
seus valores enviados pelo navegador não substituem a confirmação no backend.
Matrículas continuam como texto, com zeros à esquerda. Apenas NORMAL com
matrícula inequívoca pode ser selecionado; férias, outras situações e
duplicidades permanecem bloqueadas individualmente e são reconfirmadas ao salvar.

A busca anterior de O.S. consultava somente Atividades no PostgreSQL.
Agora o autocomplete consulta STJ010/ST4010 diretamente, por área e filial
dos setores autorizados, com L/N, origem em 2026 e serviço ENTRESSAFRA.
A O.S. 005523 foi confirmada na fonte: área CALDEI, filial 01,
centro de custo 4101006, situação L e término N.

O.S. sem vínculo disponível no caderno/entressafra selecionados aparecem
com aviso e sem identificador de atividade selecionável. Para PCM/ADMIN_PCM,
há uma ação em nova aba para conferir/vincular ao caderno correto, com setor
e pesquisa preenchidos. Não se cria vínculo automaticamente. A responsável
solicita essa ação ao PCM. Atividades antigas e apontamentos não são alterados.

## Configuração dos setores

Valores confirmados por SELECT agregado das O.S. elegíveis, usando a conexão
da aplicação, em 21/10/2026:

| Área TOTVS (TJ_CODAREA) | Filial TOTVS (TJ_FILIAL) |
| --- | --- |
| MECANI | 01 |
| ELETRI | 01 |
| CALDEI | 01 |
| CALD | 01 |
| DESTIL | 01 |
| TRATA | 01 |

1. Entrar com permissão de administração dos cadastros e abrir Setores.
2. Cadastrar/editar o setor real, marcar Ativo e preencher Área TOTVS e
   Filial TOTVS com a combinação correspondente acima. Manter `01` como texto.
3. CALD e CALDEI são áreas distintas na fonte; não juntar automaticamente.
   O PCM deve confirmar a correspondência de cada área ao setor operacional.
4. Autorizar a responsável para os setores necessários em Usuários.
5. Cadastrar o caderno na entressafra 2026/2027 e no setor correto.
6. Em Atividades / O.S., pesquisar e vincular a O.S. ao caderno após conferência.
7. Voltar ao Novo Apontamento, selecionar os filtros e pesquisar novamente.

Área e filial são necessárias para autorizar a pesquisa de O.S.; não são
consultadas para funcionários. Centro de custo não determina caderno.
Setor sem mapeamento não provoca consulta ampla sem restrição: a API orienta
a configuração. Não foram cadastrados setores operacionais automaticamente.

## Validação e navegador

- PHPUnit: 88 testes / 529 verificações, aprovados.
- SQL Server real: quatro permissões de funcionários, busca por nome/matrícula,
  confirmação individual, bloqueio das duplicidades e filtros de O.S. aprovados.
- HTTP autenticado em 127.0.0.1:8766: Maria/MARIA/maria equivalentes,
  matrícula, tentativa de SQL Injection, O.S. 005523 sem vínculo bloqueada
  e seleção após vínculo autorizado temporário, aprovados.
- HTTP: campos cadastrais readonly e arquivos JavaScript servidos conferidos.
- JavaScript: testes com DOM simulado para debounce, mínimo de caracteres,
  seleção por teclado, limpeza, bloqueios, ação PCM, estados vazio/erro e
  preservação dos demais campos. Não substituem conferência visual em navegador.
- PostgreSQL: teste concorrente com dois processos, uma gravação aceita
  e outra bloqueada pela proteção global de sobreposição. Horários consecutivos,
  setores/O.S. distintos e identificadores inválidos cobertos pela suíte.
- Migrations existentes aplicadas; nenhuma migration nova necessária.

Para conferir visualmente, acessar http://127.0.0.1:8766/apontamentos/add
após login, atualizar com Ctrl+F5 e digitar `Maria`. Selecionar um resultado
NORMAL por clique ou setas/Enter e conferir os quatro campos somente leitura.
Limpar e pesquisar a matrícula, verificando os zeros. Selecionar o setor
mapeado para CALDEI/01 e pesquisar `005523`: sem atividade vinculada, o
resultado deve aparecer bloqueado e com orientação ao PCM. Depois do vínculo
explícito ao caderno, repetir a pesquisa com filtros correspondentes.

O teste HTTP cria apenas registros temporários próprios no PostgreSQL e os
remove em finally, inclusive o vínculo de teste; nenhum apontamento real é
criado por ele. Executar com servidor local ativo:

```powershell
php tools/validate_employee_access.php
php tools/verify_employee_autocomplete.php
php tools/diagnose_direct_lookup.php
$env:PCM_RUN_HTTP_LOOKUP_TEST='1'
php -d display_errors=stderr tools/verify_direct_lookup_http.php
Remove-Item Env:PCM_RUN_HTTP_LOOKUP_TEST
php vendor/phpunit/phpunit/phpunit --colors=never
```

Pendências operacionais: configurar os setores/cadernos reais, conferir as
duas matrículas duplicadas na lista restrita do PCM e validar visualmente a
tela. Todas as consultas TOTVS desta entrega são SELECT; não há porcentagens
ou Curva S. Outros sistemas e seus bancos não foram alterados.
