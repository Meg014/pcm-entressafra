# Descrições específicas e vinculação por lista

A rota é `/cadernos/{id}/vincular-lista`. Para CALDEIRARIA - MOENDA na base atual: http://localhost:8766/cadernos/24/vincular-lista.

Entrar como administrador/PCM, escolher a entressafra e abrir Cadernos. Cada linha e o detalhe do caderno têm o botão **Vincular O.S. por lista**. Período e caderno vêm do contexto, sem novos seletores. A ferramenta antiga por serviço permanece em Ferramentas avançadas.

O processamento existente foi preservado: até 2.000 números únicos, separadores por linha/espaço/vírgula/ponto e vírgula, zeros preservados, duplicidades detectadas, consultas de 100 números, confirmações processadas em blocos de 20 com transações e auditoria. A prévia mostra filial, serviço, equipamento, equipe, elegibilidade e vínculo existente. Transferências exigem seleção explícita e justificativa. Nenhuma escrita é enviada ao TOTVS.

Na Curva S, cada O.S. continua contribuindo uma vez por sua classificação principal, sem usar equipe executante como setor de apresentação. O formulário de lista mostra o setor principal do caderno e um acesso para conferência administrativa. Classificações individuais preexistentes são preservadas e podem ser revisadas pela ferramenta administrativa existente.

## Descrições

`/controle-pcm` tem cinco colunas: O.S., descrição específica, equipe executante, acumulado e última atualização, com 10 registros por página. O número abre `/atividades/view/{id}`, que reúne identificação, descrição/fonte, serviço TOTVS, equipamento, equipe, caderno, setor da curva, apontamentos e histórico paginado de porcentagens.

A migração `20261023120000_DescricaoAtividade` adiciona `atividades.descricao_atividade` e `descricao_origem`, inicialmente nulos. Não houve substituição de descrições ou preenchimento fictício. A descrição geral do caderno não identifica cada atividade e não é utilizada como alternativa. Sem descrição específica, a tabela mostra **Descrição pendente**.

No detalhe da O.S., administrador/PCM pode cadastrar a descrição real do caderno e identificar sua fonte. Também pode consultar **observações da O.S. no TOTVS**: consulta parametrizada por número e filial, limitada a dois resultados, permitida explicitamente pelo driver somente leitura. O catálogo autorizado confirmou `STJ010.TJ_OBSERVA` como `varbinary(max)`. O texto é convertido para exibição, com tratamento Windows-1252 quando necessário. Não se presume que toda observação seja uma descrição válida: o conteúdo é sugerido no formulário apenas quando ainda não há descrição cadastrada e exige conferência e gravação explícita. O serviço genérico ST4010 permanece separado.

Salvar usa POST com CSRF, permissão PCM, período ativo/editável, transação e auditoria com antes/depois e fonte. Períodos encerrados e atividades de outra sessão são protegidos pelas mesmas regras existentes.

## Consulta real da 005978

`php tools/verify_os_005978.php` executa somente SELECT. Resultado na verificação: filial 01, serviço ESMECA / ENTRESSAFRA, área executante MECANI, equipamento TER 20 005, situação L, término N, elegível. Há observação específica no TOTVS. Já existe atividade local 600, vinculada a MOENDA; uma passagem para CALDEIRARIA - MOENDA exige transferência explícita.

O caderno 24 ainda está **sem classificação principal da Curva S**. É necessário conferir e confirmar Moenda no cadastro administrativo antes de esperar sua contribuição na curva de Moenda. Nenhuma classificação, transferência ou descrição dessa O.S. foi gravada pelos testes.

## Arquivos desta alteração

- `config/Migrations/20261023120000_DescricaoAtividade.php`
- `config/routes.php`
- `src/Integration/Totvs/Queries.php`
- `src/Service/DescricaoAtividade.php`
- `src/Service/Curva.php`
- `src/Controller/AtividadesController.php`
- `src/Controller/OrganizacaoController.php`
- `templates/Atividades/view.php`
- `templates/Cadastros/index.php`
- `templates/Evolucao/acompanhamento.php`
- `templates/Organizacao/lista.php`
- `tests/TestCase/Controller/PeriodosNavegacaoTest.php`
- `tools/verify_os_005978.php`
- `tools/verify_periodo_http.php`
- Este documento.

## Validação

Suíte completa aprovada: **227 testes e 1.462 asserções**, registrada em `tmp/descricao-lista-junit.xml`.

Os testes de navegação cobrem os dois botões, contexto sem seletores, ausência de descrição fictícia, cadastro auditado, pesquisa por descrição, equipamento somente no detalhe e histórico completo. A suíte de reorganização cobre listas grandes, repetidos, prévia sem vínculo, vínculos existentes, seleção entre páginas, transferência explícita e justificada, revalidação, equipes preservadas, setor Moenda por caderno, consolidado único e paginação de 10 registros.

`tools/verify_periodo_http.php --preview` verifica também as rotas reais do caderno 24 e a consulta da descrição da atividade 600, além de sessão, CSRF e períodos. Remove somente suas próprias fixtures e compara hashes de 19 tabelas. `tools/verify_curva_integridade.php` confirmou preservação de todos os registros e da estrutura anterior em 17 tabelas após a migração aditiva.
