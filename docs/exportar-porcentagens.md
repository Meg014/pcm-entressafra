# Exportar porcentagens — Excel

Disponível somente para ADMIN_PCM pelo botão Exportar Excel ao lado de Pesquisar
na grade de evolução. Reutiliza `/exportar-porcentagens?baixar=1`, com URL gerada
pelo CakePHP para preservar o prefixo de instalação, inclusive `/pcm-entressafra/`.
Envia explicitamente a entressafra ativa e o setor selecionado, sem pesquisa ou
paginação. O download via fetch e Blob não navega, salva alterações ou aciona
beforeunload. HTML/JSON não são aceitos como Excel; erros aparecem na própria tela.
Os setores são as áreas utilizadas na grade de porcentagens. São exportadas todas as
O.S. PROGRAMADA vinculadas aos cadernos cujo setor principal (`cadernos.area_curva_id`)
corresponde ao setor selecionado, com caderno e atividade na mesma entressafra.
A regra é a mesma de `PorcentagensLote`; `cadernos_setores` representa equipes
executantes e não substitui o setor principal. O.S. sem caderno ficam excluídas.
Somente no Excel, perguntas reconhecidas do checklist com caixas sim/não são
removidas. A descrição principal e textos posteriores são preservados. Quebras
residuais são compactadas e a altura permanece automática. Descrições comuns
permanecem integrais, inclusive suas quebras de linha.
As células semanais usam o último registro/revisão da semana, pelo mesmo calendário
da grade. Ausência permanece vazia; zero explícito permanece numérico. O percentual
mais recente fica vazio para O.S. sem registros nas semanas iniciadas.

Dependência: `phpoffice/phpspreadsheet`, já presente no composer.json/lock.
Nenhuma migration nova. O banco precisa estar atualizado com as migrations existentes,
inclusive `20261106100000_AddTipoPlanejamento.php` para excluir corretivas.

Validação automatizada:

```powershell
php vendor/phpunit/phpunit/phpunit tests/TestCase/Controller/PorcentagensLoteTest.php
php vendor/phpunit/phpunit/phpunit tests/TestCase/Service/EvolucaoSemanalTest.php
php tools/verify_exportar_porcentagens.php
```

O último comando exige PostgreSQL e confere todas as áreas da entressafra ativa,
comparando cada célula com consultas independentes dos registros persistidos.
Executa em transação REPEATABLE READ READ ONLY, revertida ao terminar. Não salva
arquivos com dados reais, não escreve auditorias e não acessa TOTVS.

Na implementação, essa conferência no banco conectado foi impedida pela ausência
da coluna `atividades.tipo_planejamento`. Nenhuma migration foi aplicada pelo teste.
É necessário atualizar o banco pelo processo de implantação do projeto e executar
novamente a conferência antes de considerar DESTILARIA e Mecânica homologadas.

## Correção da integração e verificação no navegador

Confirmado no banco operacional: `atividades.tipo_planejamento` ausente.
A consulta HTTP de TRAT. DE CALDO (setor 12, entressafra 43) recebe 503 com mensagem
indicando a migration existente. Nenhuma migration foi aplicada e a verificação
operacional usou conexão com `default_transaction_read_only=on`.

Em Chrome headless, com banco SQLite de teste atualizado: download real iniciado
na página 2; arquivo reaberto com 260 O.S., incluindo registros sem porcentagens;
MIME e Content-Disposition corretos; prefixo preservado; URL da tela e rascunho
inalterados; erro 503 exibido e resposta HTML rejeitada; nenhum erro JavaScript.

Para reproduzir, instale Playwright apenas no diretório temporário e use o Chrome
local (não há dependência nova do aplicativo):

```powershell
php vendor/phpunit/phpunit/phpunit --filter testExportButtonUsesExplicitActiveSeasonAndExistingEndpoint
npm.cmd install --prefix tmp/export-download-browser --no-save --package-lock=false playwright
$env:PCM_EXPORT_BROWSER_TEST='1'
php -d display_errors=0 -S 127.0.0.1:8879 tests/Support/ExportDownloadRouter.php
```

Com o servidor de teste em execução, em outro terminal:

```powershell
node tools/test_exportar_porcentagens_browser.js
```

O router de teste autentica somente no SQLite isolado, exige a variável acima e
aceita conexões locais. Encerre o servidor de teste após a execução. O artefato
baixado fica em `tmp/export-download-browser.xlsx`.
