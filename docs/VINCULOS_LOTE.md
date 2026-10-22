# Vincular O.S. em Lote

Disponível para PCM e ADMIN_PCM em **Atividades / O.S. → Vincular O.S. em Lote**,
ou http://127.0.0.1:8766/vinculos-lote após login.

## Como usar

1. Escolher entressafra, setor e caderno ativo. A lista de cadernos é filtrada
   pelas duas primeiras escolhas. O setor precisa de Área TOTVS e Filial.
2. Os serviços são carregados diretamente do TOTVS, com nome e quantidade
   de O.S. elegíveis na área/filial. Selecionar um ou mais serviços, até 20
   por pesquisa; Ctrl/Cmd permite seleção múltipla.
3. Se necessário, filtrar por parte do centro de custo, Bem ou número/serviço.
   A área já fica restrita à configuração do setor; não é inferida pelo C.C.
4. Clicar em **Preparar prévia**. A consulta é paginada no SQL Server e ainda
   não cria atividades. Não há limite de uma página para a quantidade total.
5. Conferir número/filial, código/nome do serviço, área, C.C., Bem, abertura,
   situação, caderno já vinculado e motivos de bloqueio.
6. Marcar individualmente ou usar **Selecionar todas as elegíveis**. Essa ação
   confirma a quantidade e inclui todas as páginas da pesquisa atual na prévia.
   O campo de pesquisa da tabela filtra número, código/nome, Bem e C.C.; a
   seleção de todas respeita essa pesquisa. Limpar seleção desmarca a prévia inteira.
7. Conferir a quantidade total selecionada e clicar em **Confirmar vinculação**.
   A confirmação informa caderno, setor e entressafra. Cancelar não grava vínculos.
8. Acompanhar os contadores e o resultado. É possível parar após o lote atual
   e retomar. Atualizar a página mantém o endereço da prévia e permite retomá-la.
9. Para outro caderno ou outros filtros, usar **Nova pesquisa / outro caderno**.

Um serviço pode abranger vários cadernos. Selecionar todas não significa que
o sistema conhece a distribuição correta: o PCM deve conferir e confirmar.
O.S. previamente vinculadas ficam bloqueadas; nunca são transferidas.

## Consulta e integridade

- Somente SELECT em DADOSP12.dbo.STJ010/ST4010, família 010, com parâmetros
  e allowlist do driver. L, término N, origem 20260101–20261231, nome do
  serviço contendo ENTRESSAFRA e correspondência inequívoca do cadastro.
- Serviços antigos e atuais são descobertos na fonte. Não existe lista fixa
  de códigos na implementação. 2425ME/2425EL não são excluídos por serem antigos.
- Área/filial são obrigatórias e aplicadas antes da paginação. Filtros de
  C.C., Bem e texto usam parâmetros, com escape literal de curingas.
- A fonte retorna até 200 itens por passo de preparação, usando cursor por
  número dentro da filial. A tabela da prévia exibe 50 por página. Prévia e
  seleção ficam persistidas no PostgreSQL, com acesso limitado ao autor PCM.
- Número/filial ambíguos são bloqueados. A confirmação individual via detalhe
  também rejeita mais de um registro para a identidade.
- Antes de cada gravação, o backend reconfirma o destino, o mapeamento atual
  e a O.S. real, inclusive serviço e filtros adicionais. Alterações após a prévia
  podem impedir a vinculação e aparecem no resultado com motivo.
- Cada requisição processa até cinco itens, com orçamento de oito segundos
  verificado entre itens. O progresso fica salvo. Não há fila externa exigida.
- Cada item é uma transação: atividade, resultado e auditorias são confirmados
  juntos. Uma falha reverte o item inteiro; itens já concluídos permanecem.
  Não existe promessa de rollback de toda a operação após lotes já confirmados.
- Bloqueio da linha da operação serializa requisições da mesma prévia. A
  unicidade existente de atividades.os_chave protege prévias concorrentes e
  vínculos individuais. Reenviar processamento concluído não duplica registros.
- A unicidade global já existente foi preservada: uma O.S. vinculada em outro
  caderno/entressafra também exige revisão específica, sem transferência automática.
- Auditoria registra início com filtros/quantidade, autor, cada vínculo e fim.
  Autenticação, autorização PCM e CSRF são exigidos nas ações de alteração.
- Apontamentos existentes e a proteção PostgreSQL contra sobreposição de
  horários não foram modificados. Não há percentuais, Curva S ou escrita TOTVS.

## Banco e arquivos

Migration aplicada no banco exclusivo pcm_controle_entressafra:
`20261021170000_CreateVinculosLotes`. Cria vinculos_lotes/vinculos_itens com
chaves estrangeiras, índices e unicidade de item por prévia/O.S. Não modifica
registros operacionais existentes. Prévias e resultados são mantidos para
retomada e conferência; não há limpeza automática nesta entrega.

Em outra instalação deste projeto, executar:

```powershell
php bin/cake.php migrations migrate
```

Arquivos principais: BulkOrderQueries/BulkOrders (SELECTs), BulkLinks (fluxo
transacional), VinculosLoteController, modelos VinculosLotes/VinculosItens,
templates/VinculosLote/index.php, webroot/js/bulk-links.js e migration acima.
Activities::link recebeu uma validação adicional opcional para reconfirmar os
filtros do lote sobre a mesma leitura usada na gravação individual.

## Validação em 21/10/2026

- PHPUnit: 104 testes / 644 verificações, incluindo os testes existentes.
- Fonte real: MECANI, 2 serviços e 396 O.S.; ELETRI, 2 serviços e 75 O.S.
  Dessas O.S., 76/57 respectivamente utilizavam os códigos antigos consultados.
  Todas atenderam aos filtros definitivos. Totais representam o momento do teste.
- HTTP real autenticado com CSRF: serviços reais, preparação de 396 O.S., seleção
  em todas as páginas, limpeza, seleção individual, confirmação, vínculo temporário,
  auditoria e reenvio idempotente aprovados. Todos os registros próprios removidos.
- PostgreSQL real: dois processos atingiram a inserção da mesma O.S. em prévias
  distintas; um vínculo/auditoria foi aceito e outro rejeitado, sem duplicidade.
  O teste usa fonte simulada, sem consultar/escrever no TOTVS, e remove suas fixtures.
- Testes de domínio: serviços antigos/atuais, prévia de 240 O.S. em dois passos,
  seleção de todas além da primeira página, seleção parcial, quantidade incorreta,
  serviços/itens forjados, prévia de outro autor, caderno incompatível, área divergente,
  situações C/P, término S, duplicidade na fonte, vínculo existente e concorrente,
  alteração de C.C. após prévia, identidade legada com mesmo número/filial,
  auditoria e rollback quando a auditoria falha.
- Testes de JavaScript com DOM simulado: restauração da prévia/destino, serviços
  múltiplos, bloqueios, seleção global, cancelamento das confirmações, CSRF e resultado.
  Não equivalem à conferência visual em navegador, que permanece para validação do PCM.

Ferramentas reproduzíveis (fixtures apenas no PostgreSQL exclusivo, removidas
em finally; não executar com usuários operando em produção):

```powershell
php vendor/phpunit/phpunit/phpunit --colors=never
php tools/verify_bulk_source.php
$env:PCM_RUN_BULK_HTTP_TEST='1'
try { php -d display_errors=stderr tools/verify_bulk_http.php }
finally { Remove-Item Env:PCM_RUN_BULK_HTTP_TEST }
$env:PCM_RUN_BULK_CONCURRENCY_TEST='1'
try { php -d display_errors=stderr tools/verify_bulk_concurrency.php }
finally { Remove-Item Env:PCM_RUN_BULK_CONCURRENCY_TEST }
```

Pendências operacionais: conferir visualmente a tela e cadastrar/configurar os
setores e cadernos reais antes do uso. Nenhum vínculo operacional permanente
foi criado automaticamente. Nenhum outro projeto ou banco PCM foi alterado.
