# Exportar porcentagens — Excel

Disponível no menu administrativo somente para ADMIN_PCM, em `/exportar-porcentagens`.
A seleção inicia na entressafra ativa e não altera a entressafra de edição da sessão.
Os setores são as áreas utilizadas na grade de porcentagens. São exportadas todas as
O.S. PROGRAMADA da área, inclusive sem caderno quando possuem classificação direta.
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
