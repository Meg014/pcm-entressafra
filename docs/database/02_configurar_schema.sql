-- Abrir NOVA conexão ao banco pcm_controle_entressafra como administrador.
-- A proteção impede aplicar este script em qualquer outro banco.
BEGIN;
DO $$
BEGIN
    IF current_database() <> 'pcm_controle_entressafra' THEN
        RAISE EXCEPTION 'Conecte-se exclusivamente a pcm_controle_entressafra';
    END IF;
END $$;

-- Executar junto com a verificação acima, em uma única transação.
REVOKE ALL ON SCHEMA public FROM PUBLIC;
GRANT USAGE, CREATE ON SCHEMA public TO pcm_entressafra_app;
COMMIT;
