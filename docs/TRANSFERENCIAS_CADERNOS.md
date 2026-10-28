# Transferência de O.S. na prévia da lista

## Funcionamento

Em **Cadernos → Vincular O.S.**, cole a lista e aguarde a conferência. Os indicadores anteriores são mantidos, com o total selecionado para transferência.

O.S. encontradas em outro caderno aparecem na seção **O.S. vinculadas a outros cadernos**, com seleção, número, origem, destino e situação. A seção tem paginação própria de 50 itens. **Selecionar todas** inclui todas as transferíveis da prévia, inclusive outras páginas. A paginação guarda mudanças da seleção antes de navegar. Desmarcar itens remove somente esses itens da seleção; **Manter no caderno original** limpa a seleção sem movimentar O.S.

**Transferir selecionadas** primeiro salva a seleção e abre uma janela com quantidade e destino. O PCM informa a justificativa e usa **Confirmar transferência**. Cancelar a janela não movimenta nenhum registro. A confirmação é exigida também no backend; a simples seleção ou a abertura de uma prévia não autoriza transferência.

O.S. disponíveis continuam sendo vinculadas por **Confirmar vínculos**, separadamente das transferências. Essa ação nunca inclui transferências selecionadas por outra tela. Uma prévia mista pode ser processada em etapas: transferir as escolhidas e depois vincular as disponíveis, ou o inverso, sem precisar repetir a consulta original. Resultados e erros de cada item permanecem na prévia.

## Estrutura e integridade

A vinculação atual é um único `atividades.caderno_id`. Apontamentos, insumos e percentuais são vinculados ao ID da atividade, não copiados para o novo caderno. A transferência atualiza somente o vínculo de caderno da atividade existente. Não cria outra O.S. e não altera registros de horas, insumos, evolução, snapshots de origem ou suas revisões.

Foi reutilizado `ListLinks`, que já possuía uma transferência administrativa separada. O novo fluxo foi integrado à tela principal, mantendo a ferramenta administrativa antiga compatível. Não há migration nova nem alteração de estrutura.

Cada transferência mantém uma auditoria `transferencia_caderno_lista`, com o estado anterior e posterior, ID/número da O.S., origem e destino identificados por ID e nome, usuário, horário UTC e justificativa. O registro de confirmação do lote também preserva a quantidade autorizada. Falha em um item desfaz sua transação e registra o motivo na prévia; o processamento em blocos preserva os resultados dos demais itens, conforme o padrão existente.

Os totais por caderno são obtidos do vínculo atual e passam a refletir o destino sem duplicação. A área da Curva S herdada do caderno acompanha o novo caderno. Uma classificação individual `atividades.area_curva_id` continua prevalecendo, conforme a regra existente, e não é apagada silenciosamente. A equipe executante continua sendo a do TOTVS; se necessário, sua associação ao caderno de destino é criada e auditada pelo mecanismo existente.

Indicadores atuais são calculados a partir do vínculo atualizado. Percentuais históricos continuam na mesma O.S.; fechamentos versionados já publicados não são recalculados ou reescritos por essa operação.

## Confirmação e concorrência

O job pertence ao usuário PCM/ADMIN que criou a prévia. Operacionais não recebem acesso ao recurso. Entressafra e caderno de destino precisam continuar válidos. Campos POST continuam protegidos por CSRF.

A seleção global e os dados do destino recebem uma assinatura HMAC. Se a seleção, o destino ou a quantidade mudar depois da revisão, a confirmação é recusada. As O.S. selecionadas são revalidadas e bloqueadas em ordem estável na confirmação, conferindo origem e entressafra. O destino recebe bloqueio compartilhado no PostgreSQL, impedindo sua alteração durante a verificação.

Durante a execução, o sistema consulta novamente o TOTVS exclusivamente em leitura, verifica identidade, filial, disponibilidade e equipe, bloqueia a atividade e confere novamente o vínculo de origem contra a prévia. O destino confirmado também é comparado novamente. Uma mudança concorrente gera erro no item e exige nova prévia; não sobrescreve o vínculo novo criado por outra operação. Se outra operação já levou a O.S. ao mesmo destino, ela é reconhecida sem duplicação.

## Testes

Foram aprovados **56 testes automatizados, com 766 assertions**, abrangendo os fluxos novos e regressões de cadernos, entressafras e evolução semanal. Os cenários incluem:

- Seleção de 77 transferências, paginação, selecionar todas e manter origem.
- Confirmação obrigatória, assinatura alterada e origem modificada antes de confirmar.
- Revalidação de origem e TOTVS durante o processamento.
- Transferência de apenas uma O.S. selecionada em lista mista, seguida de vínculo normal das disponíveis.
- Preservação integral dos apontamentos, insumos e revisões de percentuais da O.S.
- Totais e média simples recalculados nos setores de origem/destino, sem duplicar atividades.
- Preservação de classificação individual da Curva S, autenticação e permissões.
- Prévia HTTP com janela de confirmação aberta sem movimentar a O.S.

O navegador aprovou **42 verificações**, em larguras de 1440, 820 e 390 pixels: campos de origem/destino, confirmação modal, quantidade, assinatura, justificativa, cancelamento, contagem e ausência de erros JavaScript. Todas as transferências dos testes utilizaram fixtures no banco de testes; nenhuma O.S. real foi transferida como parte da implementação.

```powershell
$env:TRANSFERENCIAS_PREVIEW='1'
C:\xampp\php\php.exe vendor/phpunit/phpunit/phpunit tests/TestCase/Service/CurvaReorganizacaoTest.php tests/TestCase/Controller/CadernosPrincipalTest.php tests/TestCase/Controller/PeriodosNavegacaoTest.php tests/TestCase/Service/EvolucaoSemanalTest.php
powershell -ExecutionPolicy Bypass -File tools/test_apontamentos_batch_browser.ps1 -Preview transferencias-lista-preview.html -TestScript test_transferencias_lista_browser.js
```

## Arquivos alterados

- `src/Service/ListLinks.php`: seleção de transferências, resumo/paginação, assinatura, confirmação, retomada de prévia mista, bloqueios, revalidação e auditoria.
- `src/Controller/OrganizacaoController.php`: ações da seleção e confirmação na rota principal e persistência da paginação.
- `templates/Organizacao/lista.php`: integração da seção e manutenção dos indicadores.
- Novo `templates/element/transferencias_lista.php`: tabela e janela de confirmação.
- `webroot/js/lista-os.js`: contagem, persistência da seleção ao paginar e modal.
- `tests/TestCase/Service/CurvaReorganizacaoTest.php`: cenários de transferência e atualização das expectativas da prévia.
- Novo `tools/test_transferencias_lista_browser.js` e este documento.

**Migrations necessárias: nenhuma.**
