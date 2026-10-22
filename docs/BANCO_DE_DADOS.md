# Preparação do PostgreSQL — Etapa 1

Referência consultada somente para leitura: `C:\PCM\Sistemas\PCM-Solicitacao-OS`.
O projeto usa CakePHP 5, driver Postgres e `.env` na raiz com DB_HOST,
DB_PORT, DB_NAME, DB_USER e DB_PASSWORD. O README orienta criar o banco
previamente e depois aplicar migrations; não foi localizado um script de
CREATE DATABASE entre os arquivos de configuração examinados.
Nenhum arquivo ou banco do projeto de referência foi alterado. Nenhuma
credencial foi copiada. Nenhum comando SQL foi executado nesta preparação.

## 1. Criar banco e usuário no pgAdmin

Abra o Query Tool no banco administrativo `postgres`, com uma conta
administradora. Execute cada instrução de
`docs/database/01_criar_banco.sql` separadamente, sem BEGIN/COMMIT e com
Auto-commit habilitado. Substitua a senha de exemplo por uma senha exclusiva.
Se o usuário ou banco já existir, pare e confira sua origem; não exclua nem
reaproveite objetos sem verificar.

Abra outro Query Tool conectado especificamente a
`pcm_controle_entressafra` e execute todo o conteúdo de
`docs/database/02_configurar_schema.sql` em um único lote.

O papel exclusivo não é superusuário, não cria bancos nem papéis e não
recebe associação a outros papéis. Pode criar as tabelas das migrations no
seu próprio banco. São revogados os privilégios de PUBLIC apenas no NOVO
banco e no seu schema. Nenhum GRANT/REVOKE é executado nos bancos existentes.

Observação sobre isolamento: bancos existentes podem conceder CONNECT ou
outros privilégios a PUBLIC, herdados por qualquer login PostgreSQL. Este
script não altera essas permissões. Para proibir inclusive conexões a outros
bancos, um administrador pode configurar regras específicas no pg_hba.conf,
após avaliar o ambiente. A configuração deste CakePHP aceita somente o nome
`pcm_controle_entressafra` e ignora DATABASE_URL.

## 2. Configurar o novo projeto

O `.env` já foi preparado e está ignorado no Git. Edite somente nele:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=pcm_controle_entressafra
DB_USER=pcm_entressafra_app
DB_PASSWORD="SUA_SENHA_EXCLUSIVA"
```

Use a senha definida para o novo papel. Não envie a senha no chat.
A chave SECURITY_SALT local foi gerada aleatoriamente para este projeto.
`config/app_local.php` já carrega essas variáveis; o modelo versionável é
`config/app_local.example.php`. Os testes estão configurados para um arquivo
SQLite isolado dentro de `tmp`, sem acesso aos dados PostgreSQL.

## 3. Verificar a conexão antes das migrations

No PowerShell:

```powershell
& 'C:\Program Files\PostgreSQL\18\bin\psql.exe' -h 127.0.0.1 -p 5432 -U pcm_entressafra_app -d pcm_controle_entressafra -W -c 'SELECT current_database(), current_user;'
```

O resultado esperado é `pcm_controle_entressafra` / `pcm_entressafra_app`.
Não use a credencial administrativa na aplicação.

## 4. Aplicar migrations e executar o sistema

A criação do banco foi confirmada pelo usuário e a conexão exclusiva foi
validada. As quatro migrations e as telas da Etapa 1 foram implementadas.
As migrations já foram aplicadas neste ambiente. Em uma nova instalação,
depois da conexão validada, execute:

```powershell
Set-Location C:\PCM\Sistemas\PCM_ControleEntressafra
php bin/cake.php migrations migrate --connection default
```

Para iniciar o servidor local depois da implementação:

```powershell
php bin/cake.php server -H 127.0.0.1 -p 8766
```

Abra http://localhost:8766. A porta 8765 pertence a outro sistema PCM.
A Etapa 1 não inclui integração TOTVS nem dados fictícios permanentes.

## Ambiente inspecionado

- PHP CLI 8.2.12, Composer 2.10.3 e PostgreSQL 18 em execução.
- Extensões intl, mbstring, PDO, pdo_pgsql, SimpleXML, curl e zip disponíveis.
- CakePHP instalado: 5.4.3; Migrations: 5.2.6.
- Aviso preexistente: OpenSSL carregado duas vezes no PHP global. Nenhuma
  configuração global foi modificada.
