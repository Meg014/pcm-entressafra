# Sessão sem encerramento por inatividade para computadores operacionais

Aplicável somente às contas com perfil `RESPONSAVEL_SETOR` e opção **Computador operacional** habilitada no cadastro de usuários. Os demais usuários continuam sujeitos ao limite de 30 minutos sem atividade.

O login operacional não expira por inatividade na aplicação. Um cookie HttpOnly, SameSite=Lax, restrito à pasta do sistema e Secure em HTTPS permite recuperar a autenticação caso o PHP elimine a sessão nativa. O banco armazena apenas o HMAC de um token aleatório de 256 bits, vinculado à senha atual. A credencial no servidor não tem prazo de expiração. O navegador limita a duração de cookies persistentes: são enviados por até 400 dias e renovados a cada acesso. Limpar os cookies ou ficar além do prazo suportado pelo navegador exige entrar novamente.

O botão **Sair** remove o cookie, revoga a credencial e encerra a sessão. Alterar a senha, desativar a conta ou remover a opção de computador operacional invalida a recuperação. As permissões e os setores autorizados continuam sendo consultados a cada acesso. Não há credenciais de administrador persistentes nem gravações no TOTVS.

Cada conta operacional mantém uma credencial de recuperação: novo login substitui a anterior. Recomenda-se manter o cadastro individual já utilizado para cada computador, como Computador 1, Computador 2.

## Implantação manual

A migration `20261026150000_AddSessaoPermanenteComputador.php` acrescenta somente a coluna nullable `usuarios.sessao_computador_hash`, sem modificar usuários, senhas ou registros existentes. Não foi executada automaticamente em produção.

Após publicar os arquivos, verificar as migrations pendentes e aplicar manualmente, seguindo o procedimento habitual de backup:

```sh
php bin/cake.php migrations status --connection default
php bin/cake.php migrations migrate --connection default --target 20261026150000
php bin/cake.php schema_cache clear --connection default
```

O comando também aplica migrations anteriores pendentes até o alvo; conferir a listagem antes de executar. Até aplicar esta migration, o login continua funcionando, mas a recuperação após limpeza da sessão PHP depende da coluna nova. Depois da implantação, fazer um novo login na conta do computador; contas operacionais com sessão válida também recebem a credencial no próximo acesso.

## Arquivos desta alteração

- `src/Controller/AppController.php`: remove o timeout de inatividade somente das contas operacionais e recupera sessões PHP expiradas.
- `src/Controller/AuthController.php`: emite a credencial no login e revoga no logout.
- `src/Service/ComputerSession.php`: gera, valida e revoga credenciais e configura o cookie.
- `src/Service/Users.php`: revoga credenciais ao alterar senha, desativar ou remover a opção de computador.
- `src/Model/Entity/Usuario.php`: oculta o hash das representações do usuário.
- `config/Migrations/20261026150000_AddSessaoPermanenteComputador.php`.
- `tests/TestCase/Service/OperacaoSimplificadaTest.php`: verifica inatividade, recuperação, logout, invalidação e manutenção das permissões.
- `tests/TestCase/Service/ComputerSessionMigrationTest.php`: verifica preservação dos usuários na migration.

## Testes

```powershell
C:\xampp\php\php.exe vendor/phpunit/phpunit/phpunit --filter 'OperacaoSimplificadaTest|ComputerSessionMigrationTest'
```

Os testes HTTP simulam 90 dias sem atividade e a ausência da sessão PHP, além de verificar o logout e a rejeição de tokens adulterados, contas desativadas, senha alterada e opção operacional removida.

Validação final: suíte completa aprovada, **285 testes e 2.428 assertions**. A migration também foi validada no PostgreSQL local em schema temporário, com preservação dos usuários e armazenamento do hash; a transação foi desfeita ao final.
