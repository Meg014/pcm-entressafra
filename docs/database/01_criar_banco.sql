-- Executar como administrador conectado ao banco postgres.
-- No pgAdmin, desative transação automática em bloco: CREATE DATABASE não aceita BEGIN.
-- Execute as instruções separadamente. Não execute novamente se já existirem.
-- Substitua a senha abaixo antes de executar; não salve a senha neste arquivo.
CREATE ROLE pcm_entressafra_app
    LOGIN PASSWORD 'L4s@pcM!@'
    NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS;

CREATE DATABASE pcm_controle_entressafra
    OWNER pcm_entressafra_app
    ENCODING 'UTF8'
    TEMPLATE template0;

REVOKE ALL ON DATABASE pcm_controle_entressafra FROM PUBLIC;
GRANT CONNECT, TEMPORARY ON DATABASE pcm_controle_entressafra TO pcm_entressafra_app;
