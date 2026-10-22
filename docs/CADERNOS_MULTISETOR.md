# Cadernos e setores: relacionamento muitos-para-muitos

## Estrutura entregue

- `cadernos_setores`: associação com unicidade `(caderno_id, setor_id)`, índice
  inverso e chaves estrangeiras. Um setor pode participar de vários cadernos
  e um caderno pode associar vários setores, mantendo sua entressafra.
- `cadernos.setor_id`: preservado para compatibilidade e referência legada.
  Não é mais usado para autorizar atividades ou determinar a área executante.
- `atividades.setor_executante_id`: identifica o setor executante desta O.S.
- `atividades.area_executante_totvs`: preserva o TJ_CODAREA original da O.S.;
  a filial continua em `os_filial` e os dados completos no snapshot original.
- `atividades.identificacao_setor`: `area_totvs`, `legado` ou `pendente` informa
  a origem da identificação. Novos apontamentos exigem identificação confirmada.
- `cadernos.area_fisica`: referência física opcional e descritiva. Não define
  permissões, setor executante, área TOTVS, caderno automaticamente ou centro de custo.

Caderno organiza atividades. Setor executante indica quem executa a O.S.
Área física indica um local da fábrica. TJ_CODAREA é o código da área executante
no TOTVS, mapeado explicitamente a um setor. Esses conceitos não são intercambiáveis.

## Migration e preservação

Migration aplicada: **20261021180000_CadernosMultiplosSetores**.

1. Copia os vínculos antigos para a tabela associativa.
2. Adiciona os campos novos, sem apagar/recriar cadernos ou atividades.
3. Resolve o setor de cada atividade pelo código/filial do snapshot original.
   Quando confirmado, mantém também a associação necessária ao caderno que já
   contém essa atividade, sem transferir a O.S. para outro caderno.
4. Na ausência de área no snapshot, preserva a referência antiga como `legado`;
   é necessária confirmação antes de novos apontamentos. Área presente sem
   mapeamento inequívoco fica `pendente`, sem conceder acesso por adivinhação.
5. Cria FK composta entre atividade e associação caderno/setor, impedindo remover
   uma associação usada no histórico. Apontamentos e auditorias não são reescritos.

Um manifesto com hashes das colunas anteriores foi registrado em `tmp` antes
da migration e conferido depois: **9 cadernos, 685 atividades e 1.380 auditorias
preservados**, sem alteração das colunas anteriores. Não havia apontamentos
cadastrados no banco real. Nenhuma atividade ficou pendente ou legada neste banco.

A reversão automática é bloqueada, pois o campo antigo não representa vários
setores. Não executar rollback destrutivo; uma eventual reversão requer análise
controlada. A coluna antiga e os snapshots continuam disponíveis.

Em outra instalação deste projeto:

```powershell
php tools/verify_cadernos_structure.php --before
php bin/cake.php migrations migrate
php tools/verify_cadernos_structure.php
```

## Como validar na interface

1. Entrar como PCM/ADMIN_PCM e abrir **Cadernos** pelo menu do usuário.
2. Editar um caderno e selecionar vários setores executantes com Ctrl/Cmd.
   Salvar e conferir os setores no detalhe e na listagem. Um setor pode ser
   selecionado em outros cadernos da mesma entressafra.
3. Área física é opcional; não copiar automaticamente o código TJ_CODAREA para ela.
4. Na vinculação individual, selecionar entressafra e caderno. Sem filtro de
   setor, a pesquisa considera os setores autorizados e mapeados associados ao
   caderno. É possível restringir a consulta a um deles. Os resultados exibem a
   área executante real da O.S.
5. Em **Vincular O.S. em Lote**, escolher a entressafra, o mesmo caderno e um de
   seus setores executantes para esta prévia, depois os serviços. Preparar a
   prévia, conferir e confirmar. Repetir para outro setor no mesmo caderno quando
   necessário; não existe mais um setor fixo proprietário do caderno.
6. Entrar como responsável autorizada apenas para um dos setores. O caderno
   compartilhado deve estar acessível, mas O.S., históricos e apontamentos dos
   outros setores não devem ser liberados. Seus links diretos continuam protegidos.
7. Conferir horários sobrepostos em O.S. diferentes e horários consecutivos.
   A trava é global por funcionário e não depende de caderno ou setor.

Não é possível remover um setor com atividades vinculadas nem mudar a entressafra
de um caderno que já possui atividades. Adicionar setores não reatribui O.S. ou
altera apontamentos. Uma mudança de área observada na sincronização TOTVS fica
no histórico da consulta e desabilita novos apontamentos incompatíveis; não
reescreve a área original nem transfere a atividade automaticamente.

## Contagens e futura Curva S

Listagens e totais usam subconsultas de associação, sem multiplicar atividades
por número de setores do caderno. A unicidade existente de O.S. e a identidade
da atividade foram preservadas; o mesmo vínculo não é contabilizado em dobro.
Uma O.S. já vinculada não é transferida nem duplicada. A associação permite
futura análise por caderno e setor executante, sem implementar pesos, percentuais
ou Curva S nesta entrega.

## Testes

- Suíte PHPUnit: **122 testes / 823 verificações aprovados**.
- Migration em SQLite isolado com dados anteriores: snapshots e históricos
  intactos, área real diferente do setor legado, caso legado, área não mapeada
  pendente e FK impedindo remover a associação histórica.
- Cadastro multissetorial por ORM e HTTP, associação inversa setor→cadernos,
  listagem/detalhe, ausência de contagem duplicada e bloqueio de remoção em uso.
- O.S. de setores diferentes no mesmo caderno; acesso por atividade, filtros de
  autocomplete e bloqueio de responsável sem autorização para o setor executante.
- Lote usando setor associado diferente da referência legada; snapshots originais,
  regras de duplicidade e auditoria existentes mantidos.
- Sincronização não transfere atividade nem reescreve sua área executante original.
- PostgreSQL real com fixtures próprias: caderno com dois setores, dois lotes,
  permissões, conflito global, horários consecutivos e FK histórica aprovados.
- PostgreSQL: testes concorrentes existentes de horários e vínculos em lote
  aprovados. Todos os registros temporários removidos; manifesto reconferido.
- JavaScript de lote: restauração da seleção, associações múltiplas no caderno,
  seleção entre páginas e confirmação testados com DOM simulado.

```powershell
php vendor/phpunit/phpunit/phpunit --colors=never
$env:PCM_RUN_MULTISETOR_TEST='1'
try { php -d display_errors=stderr tools/verify_multisetor_postgres.php }
finally { Remove-Item Env:PCM_RUN_MULTISETOR_TEST }
php tools/verify_cadernos_structure.php
```

A ferramenta multissetorial usa fonte simulada e grava somente fixtures próprias
no PostgreSQL exclusivo, removidas em finally. Executar sem usuários operando.
O manifesto compara a migração com seu estado anterior; edições operacionais
posteriores legítimas podem alterar os hashes e não fazem parte dessa comparação.
Pendência de validação: conferência visual do cadastro e dos filtros no navegador.
Nenhum outro projeto ou banco PCM foi alterado. Nenhuma escrita no TOTVS.
