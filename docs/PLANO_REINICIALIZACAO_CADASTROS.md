# Reinicialização dos cadastros — execução autorizada concluída

**Status em 23/10/2026: limpeza autorizada explicitamente pelo usuário e concluída.** Foram removidos exatamente os 2.583 registros deste inventário; administrador, senha, 17 migrations, 1.551 auditorias e estrutura foram preservados. O plano abaixo registra o escopo aprovado. Resultado detalhado em `backups/reinicializacao_20261022_204134/limpeza_autorizada_20261023.json` e em `docs/REINICIO_E_VINCULACAO_SIMPLES.md`.

## Banco e backup aprovado

- Projeto exclusivo: `C:\PCM\Sistemas\PCM_ControleEntressafra`.
- Banco confirmado pela conexão real: `pcm_controle_entressafra`, PostgreSQL 18.6; conta da aplicação `pcm_entressafra_app`.
- Backup completo custom do pg_dump: `backups/reinicializacao_20261022_204134/pcm_controle_entressafra_20261022_204134.dump`.
- Tamanho: **168.465 bytes**.
- SHA-256: `626e6e709767b398e00754195812e9611f47dfd18af4be23c841add228e2f91e`.
- Inventário e evidências: `backups/reinicializacao_20261022_204134/manifesto.json`; conteúdo do arquivo em `conteudo_backup.txt`; logs de geração e restauração na mesma pasta.
- Estado: **BACKUP_RESTAURADO_E_VALIDADO_SEM_LIMPEZA**.

O backup foi obtido com snapshot consistente compartilhado entre a contagem e o pg_dump, em uma transação somente leitura. A validação criou uma instância PostgreSQL independente, restrita a localhost na porta 15486, e restaurou o arquivo com `pg_restore --exit-on-error`, em uma base de teste vazia. Foram comparados os registros por contagem e SHA-256 das **21 tabelas**, as **217 restrições** e os hashes de senha dos administradores. Quatro CHECKs receberam do PostgreSQL uma representação equivalente do cast de arrays; o validador aceita exclusivamente essa reescrita literal, mantendo a comparação integral das demais definições. O restore de teste usou `--no-owner --no-privileges`; o arquivo original conserva proprietários e permissões do banco.

A instância isolada foi encerrada. Não se restaurou sobre o banco atual, não se alteraram outros bancos e não se consultou ou escreveu no TOTVS durante a preparação. Uma comparação final confirmou a origem inalterada. Os arquivos de tentativas anteriores permanecem separados e não são o backup aprovado acima.

## Contagem exata proposta

| Tabela | Antes | Excluir após autorização | Preservar |
|---|---:|---:|---:|
| apontamentos | 3 | 3 | 0 |
| areas_fabrica | 6 | 6 | 0 |
| atividades | 753 | 753 | 0 |
| auditorias | 1.554 | 3 | 1.551 |
| cadernos | 9 | 9 | 0 |
| cadernos_areas | 9 | 9 | 0 |
| cadernos_setores | 9 | 9 | 0 |
| cake_migrations | 17 | 0 | 17 |
| calendarios_operacionais | 7 | 7 | 0 |
| entressafras | 1 | 1 | 0 |
| evolucao_lotes | 2 | 2 | 0 |
| evolucao_metas | 0 | 0 | 0 |
| evolucao_pesos | 0 | 0 | 0 |
| evolucao_registros | 2 | 2 | 0 |
| evolucao_semanas | 27 | 27 | 0 |
| evolucao_versoes | 0 | 0 | 0 |
| setores | 6 | 6 | 0 |
| usuarios | 1 | 0 | 1 |
| usuarios_setores | 0 | 0 | 0 |
| vinculos_itens | 1.723 | 1.723 | 0 |
| vinculos_lotes | 23 | 23 | 0 |
| **Total** | **4.152** | **2.583** | **1.569** |

Preservar integralmente o administrador **ID 3**, login **pcm@biolasa.com.br**, perfil **ADMIN_PCM**, ativo, com senha atual. É o único usuário existente. Não redefinir senha, não substituir seu cadastro e não alterar suas credenciais.

Auditorias: somente as três linhas com `apontamento_id IS NOT NULL` precisam ser removidas porque a chave estrangeira impede apagar os apontamentos referenciados. As outras **1.551** permanecerão como histórico imutável, inclusive seus snapshots textuais de operações anteriores. Não bloqueiam a remoção dos cadastros. Essa preservação atende ao pedido de excluir auditorias apenas quando necessário à integridade.

## Procedimento proposto, ainda não executado

1. Receber autorização explícita deste plano e das contagens acima. A solicitação de continuar a revisão não equivale a essa autorização.
2. Antes da execução, suspender novas gravações durante a janela da limpeza e conferir novamente banco, administrador, arquivo/hash do backup, todas as contagens e hashes. Havendo dados novos, atualizar o inventário e o backup antes de prosseguir; não ampliar silenciosamente a exclusão.
3. Usar uma transação PostgreSQL única, com bloqueio das tabelas envolvidas. Executar exclusões explícitas em ordem de dependência; não usar `TRUNCATE CASCADE`, não desabilitar FKs/triggers e não remover tabelas.
4. Ordem: auditorias que referenciam apontamentos; registros de evolução; metas; pesos; lotes de evolução; versões; itens de vínculos; lotes de vínculos; apontamentos; atividades; calendários; semanas de evolução; associações caderno/área; associações caderno/equipe; cadernos; associações usuário/setor; setores executantes; áreas de apresentação; entressafras.
5. Não executar exclusão em `usuarios` nem em `cake_migrations`. Não reiniciar sequências: a limpeza não depende de reutilizar IDs. Preservar extensões, tabelas, índices, constraints, integrações, configuração e todos os arquivos do sistema.
6. Antes do commit, conferir todas as contagens esperadas (coluna Preservar), ausência de referências inválidas, estrutura e senha/hash do administrador inalterados. Qualquer divergência exige rollback integral.
7. Após commit, invalidar somente contextos de período e prévias antigos nas sessões e caches pertinentes. O administrador deverá poder entrar e cadastrar a primeira entressafra. Não excluir as credenciais.
8. Verificar autenticação real, cadastros vazios e contexto sem período. Os testes funcionais posteriores devem usar base isolada para não repovoar o banco recém-limpo. Não criar vínculos reais automaticamente.

## Simplificação a implementar depois desta aprovação

Manter o sistema existente e ajustar seus fluxos, sem recriar módulos:

- Vinculação no caderno: colar números, **Conferir**, resumo e um único **Confirmar vínculos** para todas as O.S. elegíveis. Retirar seleção individual, quantidade digitada e justificativa inicial; manter equipe/serviço/equipamento como informações lidas do TOTVS, sem exigir seletores.
- Zeros à esquerda, deduplicação, lotes, retomada, token, contexto, revalidação transacional e auditoria continuam obrigatórios. Conflitos com outro caderno ficam pendentes e são corrigidos em operação administrativa separada, preservando os históricos.
- Organização principal: entressafra → setor de apresentação com datas → cadernos → O.S. O percentual deve seguir o setor principal do caderno, independentemente da equipe TOTVS, com contribuição única no consolidado. Rever os caminhos atuais de classificação individual para não contrariar a nova regra.
- Reaproveitar telas operacionais de horas e porcentagens. Manter trava global/histórica de horários e registro sem aprovação de calendário ou obrigação semanal.
- Controle PCM com setor, evolução, relação paginada em dez O.S. e históricos. Manter a distinção entre média física simples e curva oficialmente ponderada quando faltarem pesos validados.
- Executar a suíte e acrescentar regressões para a confirmação única, pendências separadas, contexto vazio após reinício e classificação pelo caderno. As verificações atuais não substituem esses testes da nova experiência, ainda pendentes.

## Arquivos desta preparação

- `tools/prepare_reset_backup.php`: geração e validação somente preparatórias; não contém rotina de limpeza.
- `.gitignore`: exclusão da pasta de backups do versionamento.
- Este plano e os arquivos de backup/evidências citados acima.

**Concluído após autorização:** exclusão dos **2.583 registros** discriminados. A preparação não realizou limpeza. Na execução, a primeira tentativa de DELETE encontrou o gatilho de imutabilidade da evolução e foi revertida integralmente. As cinco tabelas de evolução previstas foram então esvaziadas com `TRUNCATE` explícito, `RESTRICT` e `CONTINUE IDENTITY`, dentro da mesma transação das demais exclusões. Não houve `CASCADE`, desativação de gatilhos/FKs, reinicialização de sequências ou alteração da estrutura. A comparação final incluiu também definições e estado dos gatilhos e funções de proteção.
