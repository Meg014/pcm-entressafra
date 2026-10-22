# Validação real TOTVS — 21/10/2026

## Estado: interrompida por permissão insuficiente

A conexão SQL Server funcionou e retornou o catálogo de tabelas/colunas.
Uma tentativa de SELECT agregado e limitado recebeu SQLSTATE 42000, erro
nativo SQL Server 230, com negativa explícita na coluna `RA_NOMECMP` de
`dbo.SRA010`. As consultas de negócio foram interrompidas. Nenhuma consulta
real foi adicionada ao Gateway das telas; ele continua bloqueando vínculos e
novos apontamentos que dependem de confirmação cadastral do TOTVS.

Não foram exibidos nomes, matrículas, registros individuais, credenciais ou
dados pessoais de funcionários. Não foi executado nenhum comando de escrita,
GRANT, procedure ou alteração no SQL Server.

## Existência e tipos confirmados no catálogo real

| Tabela | Campos confirmados relevantes |
| --- | --- |
| dbo.STJ010 | TJ_ORDEM, TJ_FILIAL, TJ_CODBEM, TJ_SERVICO, TJ_SITUACA, TJ_CCUSTO, TJ_CODAREA, TJ_OBSERVA, D_E_L_E_T_, R_E_C_N_O_ |
| dbo.ST4010 | T4_SERVICO, T4_FILIAL, T4_NOME, T4_DESCRIC, T4_CODAREA, D_E_L_E_T_, R_E_C_N_O_ |
| dbo.ST9010 | T9_CODBEM, T9_FILIAL, T9_NOME, T9_CCUSTO, D_E_L_E_T_, R_E_C_N_O_ |
| dbo.SRA010 | RA_MAT, RA_FILIAL, RA_NOME, RA_NOMECMP, RA_SITFOLH, RA_DEMISSA, RA_CC, RA_DEPTO, D_E_L_E_T_, R_E_C_N_O_ |
| dbo.ST1010 | T1_CODFUNC, T1_FILIAL, T1_NOME, T1_CCUSTO, T1_DISPONI, D_E_L_E_T_, R_E_C_N_O_ |
| dbo.CTT010 | CTT_CUSTO, CTT_FILIAL, CTT_DESC01, D_E_L_E_T_, R_E_C_N_O_ |

TJ_OBSERVA e T4_DESCRIC são varbinary(max), não texto comum: conversão e
codificação deverão ser validadas antes do uso. RA_NOME possui 30 caracteres;
RA_NOMECMP possui 70. Não foi adotado fallback automático para o nome reduzido.

O usuário confirmou o escopo exclusivo da família 010. As famílias 020, 030
e demais não participarão. A allowlist de catálogo e o utilitário de inspeção
foram restringidos às seis tabelas 010 acima; tabelas de outras famílias são
rejeitadas. A filial continuará compondo consultas e chaves dos vínculos,
mesmo com uma única empresa/filial. Não será presumido um código fixo de filial.
Não foi presumida correspondência entre T1_CODFUNC e RA_MAT, nem equivalência
entre departamento/centro de custo TOTVS e o cadastro de setores PostgreSQL.

**Catálogo confirmado não equivale a consulta de negócio validada.** Ainda faltam
validar SELECT dos dados necessários, cardinalidade das chaves, códigos de
situação, exclusão lógica, compartilhamento por filial e correspondências dos
serviços/bens/centros de custo. Nenhum desses pontos foi afirmado a partir de
nomes presumidos de tabelas ou colunas.

## Ação necessária do administrador SQL Server

A validação foi repetida após a confirmação da família 010. A negativa SELECT
em RA_NOMECMP permanece; a integração de negócio continua interrompida.

Conferir a permissão SELECT de `RA_NOMECMP` para a conta de consulta já
configurada neste projeto. A existência da tabela no INFORMATION_SCHEMA não
garante acesso a todas as suas colunas.

Exemplo para avaliação e execução pelo administrador, substituindo o usuário
pelo principal correto no banco autorizado:

```sql
GRANT SELECT ON OBJECT::dbo.SRA010 (RA_NOMECMP) TO [USUARIO_DE_CONSULTA];
```

Este comando **não foi executado**. Se houver DENY herdado por papel/grupo,
o administrador deve revisar a política; não ampliar a conta para dbo ou
conceder escrita. Se o acesso ao nome completo não puder ser autorizado,
é necessária uma decisão explícita sobre o uso de RA_NOME ou de uma view
somente leitura com os campos permitidos. Não se contorna a negativa.

Depois da correção e confirmação pelo usuário:

```powershell
php tools/validate_totvs.php
```

O utilitário retorna somente agregados limitados e indicadores de permissões,
ou mensagem sanitizada indicando as colunas sem acesso. As queries exatas estão
na allowlist de `src/Integration/Totvs/Queries.php`.

## Continuidade da Etapa 2

Regressão executada após a inspeção: **32 testes e 156 assertions aprovados**.
O teste PostgreSQL com dois processos independentes confirmou novamente uma
gravação aceita e uma bloqueada por conflito; as fixtures temporárias foram
removidas. Os casos automatizados incluem conflitos entre setores e O.S.,
intervalos consecutivos, edição, meia-noite e cancelamento lógico. Sintaxe dos
arquivos ajustados e Composer validate --strict aprovados. Esses resultados
não equivalem a uma integração de negócio TOTVS concluída.

Após o acesso ser validado, concluir pesquisas parametrizadas de O.S. e
funcionários, compor chaves completas por empresa/filial/vínculo e integrar o
Gateway às telas. O PostgreSQL exclusivo continua responsável por todas as
gravações. Não há Curva S nem lançamento semanal de porcentagens.
