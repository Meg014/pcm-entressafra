# Retorno à listagem de Cadernos

Depois de cadastrar ou editar um caderno com sucesso, o destino é `/cadernos`. A operação mantém a mensagem de sucesso existente. Se a validação falhar, o formulário e seus valores continuam na mesma tela com os erros.

Na vinculação por lista, a confirmação e os blocos continuam usando as mesmas regras de consulta, revalidação, gravação e auditoria. O retorno automático ocorre apenas quando o lote chega a `concluido`, sem itens em outro caderno, não encontrados, inelegíveis, ambíguos ou com erro. A mensagem na listagem informa a quantidade de O.S. efetivamente vinculadas e o nome do caderno.

Quando o lote termina com pendências, a página permanece no resultado, exibe quantos vínculos tiveram sucesso e quantas pendências precisam de revisão, e oferece **Voltar à listagem de Cadernos**. O token, os números originais e os resultados continuam persistidos, inclusive depois de recarregar ou voltar à listagem. Essa exceção evita ocultar pendências ao redirecionar automaticamente.

A última listagem visitada é registrada na sessão por usuário e entressafra. O retorno recupera pesquisa, filtro do setor principal, filtro executante legado, página e ordenação. O destino é sempre uma rota interna fixa de Cadernos, sem aceitar uma URL de retorno arbitrária. Se a sessão pertence a outro período ou usuário, retorna à listagem do contexto atual sem reaplicar os filtros antigos. Se uma página anterior deixou de existir, retorna à primeira página com os filtros preservados.

**Visualizar** permanece abrindo `/cadernos/view/{id}` normalmente. Nenhuma regra TOTVS, vínculo, percentual ou Curva S foi alterada. Não houve migration, exclusão ou modificação de cadastros para esta mudança.

## Arquivos

- `src/Service/NavegacaoCadernos.php`: memória do contexto da lista e destino seguro.
- `src/Controller/CadernosController.php`: retorno após cadastro/edição e recuperação de página inexistente.
- `src/Controller/OrganizacaoController.php`: retorno após conclusão sem pendências, com quantidade vinculada.
- `templates/Organizacao/lista.php`: resultado com pendências e botão de retorno.
- `tests/TestCase/Controller/CadernosPrincipalTest.php`: cadastro/edição, filtros, página, validação e Visualizar.
- `tests/TestCase/Service/CurvaReorganizacaoTest.php`: lote em andamento, conclusão com retorno/mensagem e pendências persistentes.
- `tests/TestCase/Controller/EtapaDoisTest.php`: expectativa de retorno atualizada.
- Este documento.

Os testes de integração renderizam os formulários e resultados reais com fonte TOTVS simulada e registros apenas na base isolada de testes. Cobrem também o retorno com pesquisa e setor/página anterior, manutenção da entressafra, mensagem com quantidade, recarga do resultado pendente sem duplicar vínculos e o detalhe acessível por Visualizar.

Suíte completa aprovada: **236 testes e 1.805 asserções**, registrada em `tmp/cadernos-retorno-junit.xml`. A verificação HTTP do formulário de cadernos também confirmou os sete setores disponíveis e a preservação dos hashes das 21 tabelas durante a consulta.
