# Validação da Etapa 1 — 20/10/2026

- CakePHP 5.4.3 / PHP 8.2.12 / PostgreSQL 18.
- Conexão real confirmada com `pcm_controle_entressafra` / `pcm_entressafra_app`.
- CONNECT no banco e USAGE/CREATE no schema confirmados.
- Quatro migrations em estado `up`: Entressafras, Setores, Cadernos e CHECKs PostgreSQL.
- PHPUnit: 9 testes, 63 assertions, todos aprovados.
- Os testes exercitam listagens e formulários vazios, criação e edição das três
  entidades, visualização dos vínculos, filtro de cadernos, seletor do dashboard,
  status ativo, unicidade, datas invertidas, status inválido, referências
  inexistentes, feedback de erro, escape HTML, CSRF e IDs inválidos.
- Testes PHPUnit usam SQLite em `tmp`, sem tocar no PostgreSQL principal.
- Verificação adicional no PostgreSQL: 9 restrições confirmadas por SQLSTATE
  (CHECK, FK, RESTRICT e unicidade), com rollback de todas as linhas temporárias.
- Contagens finais no PostgreSQL: 0 entressafras, 0 setores, 0 cadernos.
- Composer validate --strict: aprovado.
- Servidor local validado na porta 8766; dashboard, três listagens, três
  formulários e dois CSS retornaram HTTP 200 (9 URLs).
- A porta 8765 pertence ao PCM-Solicitacao-OS e foi preservada.
- A captura de tela em navegador foi bloqueada pela política de execução.
  Validação visual final em desktop/celular permanece para o usuário.
- O PHP global continua emitindo o aviso preexistente de OpenSSL duplicado.
  Nenhum php.ini compartilhado foi alterado.

## Roteiro para validação manual

1. Inicie o servidor conforme o README e abra http://localhost:8766.
2. Em Entressafras, cadastre `2026/2027` com as datas reais e status Planejamento.
3. Cadastre os setores reais da fábrica.
4. Cadastre os cadernos, selecionando a entressafra e o setor correspondentes.
5. Confira listagem, pesquisa, edição, detalhes e os links para cadernos vinculados.
6. No dashboard, selecione a entressafra e confira os totais e o período.
7. Quando apropriado, altere o status para Ativa e confira a identificação no dashboard.

Não foram criados dados de demonstração permanentes. Importação, TOTVS,
atividades/O.S., programação e Curva S não foram implementados.
