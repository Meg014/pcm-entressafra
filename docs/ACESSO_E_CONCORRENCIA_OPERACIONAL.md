# Acesso operacional e lançamentos simultâneos — 28/10/2026

## Diagnóstico e limites da investigação

Foram inspecionados autenticação, cookies, sessão permanente, escopo por setor, consultas TOTVS, transações, restrição de horários e logs. As funcionárias não forneceram mensagem de erro ou endereço. Os logins computador1/computador2 não existem no PostgreSQL local inspecionado. Portanto, não é possível atribuir retrospectivamente o incidente da empresa a uma causa única; as falhas abaixo foram confirmadas no código e reproduzidas em testes isolados.

1. `Access::sectors` exigia `usuarios_setores` também para computadores operacionais. Isso restringia os autocompletes, identificação, seleção e gravação de O.S. pelas verificações compartilhadas. Agora contas com perfil `RESPONSAVEL_SETOR` e `computador_operacional=true` podem operar em todos os setores, sem vínculo usuário/setor. Responsáveis humanos que não sejam computadores mantêm suas restrições. Nenhuma conta existente foi convertida ou promovida.
2. `Insumos::saveBatch` bloqueava a linha da entressafra com `FOR UPDATE`, serializando gravações independentes. O caminho de identificação operacional de O.S. também fazia isso. Agora usam `FOR SHARE` para proteger a verificação de entressafra aberta, permitindo operações concomitantes. A identificação usa ainda um bloqueio por identidade da O.S., e não por todos os setores.
3. Um conflito concorrente de funcionário dentro de um lote provocava `23P01` (restrição de exclusão). O tratamento tentava buscar os conflitos antes de recuperar a transação externa, provocando `25P02` (transação abortada). A reprodução confirmou o erro. Savepoints permitem recuperar a operação interna, consultar o conflito e desfazer o lote completo com mensagem de validação. A exclusão do PostgreSQL continua sendo a proteção definitiva contra sobreposição entre sessões e O.S.
4. Apontamentos já tinham proteção contra repetição pelo conflito de horários, mas insumos não tinham identificação de envio. Os formulários agora recebem uma chave aleatória. `EnvioLote` serializa somente reenvios da mesma chave/usuário/módulo e registra o resultado na tabela de auditoria existente, dentro da mesma transação. Reenvios devolvem os mesmos IDs; alteração de dados com chave já utilizada exige novo formulário. Uma falha não consome a chave. Os demais envios continuam independentes. Chamadas legadas sem chave permanecem compatíveis; os formulários novos sempre enviam a chave.
5. O servidor local em `127.0.0.1:8766` usa `php -S` no Windows, que atende uma requisição por vez. É um gargalo de hospedagem, independente das sessões. A validação HTTP concorrente foi executada com um único Apache/PHP de 32 threads, na porta temporária 18771. O servidor existente e o outro projeto da porta 8765 não foram modificados.

Os logs históricos disponíveis não demonstraram uma sessão de computador1 invalidando computador2. A credencial permanente é vinculada ao ID e à senha de cada usuário. Dois logins e dois cookies independentes foram testados; logout de computador1 não encerrou computador2. Não houve alteração no mecanismo de autenticação ou na proteção CSRF.

## Regras preservadas

- Entressafra ativa, O.S. disponível, número com zeros à esquerda, filial, mapeamento do setor executante e validação no TOTVS continuam obrigatórios.
- Funcionário precisa estar identificado e ativo. A validação de horários continua global por funcionário, inclusive entre O.S., usuários e sessões.
- Funcionários diferentes podem registrar na mesma O.S. Os períodos manhã/tarde continuam sendo respeitados.
- Lotes são atômicos, com auditoria. A gravação ordena a aquisição de conflitos por funcionário para reduzir deadlocks e devolve IDs na ordem original das linhas.
- Consultas de apontamentos e insumos operacionais são limitadas a `criado_por`; o detalhe de apontamento de outro usuário é bloqueado. Permissões PCM/ADMIN e dos responsáveis não operacionais são preservadas.
- Nenhuma escrita no TOTVS, migration nova ou alteração em registros existentes foi realizada.

No banco local, a coluna da sessão permanente `sessao_computador_hash` não está instalada, embora a migration `20261026150000` já exista no projeto. Essa pendência anterior não foi aplicada automaticamente. O diagnóstico não altera usuários, sessões ou dados. Na implantação da empresa, confirmar o schema e a opção **Computador operacional** das contas já existentes; a liberação não depende do texto do login.

## Validação reproduzível

```powershell
$env:PCM_RUN_OPERATIONAL_TEST='1'
C:\xampp\php\php.exe tools/verify_operational_concurrency.php
```

O script clona somente a estrutura para um schema aleatório `pcm_op_test_*`, com sequências próprias. Cria contas e O.S. fictícias exclusivamente nesse schema e usa um gateway TOTVS de teste. Nunca usa os logins ou senhas reais. Processos PHP independentes mantêm a transação A aberta enquanto B salva, comprovando que não há bloqueio de operações independentes.

São verificados os cenários A/B/C/D, conflito concorrente verdadeiro, rollback de todo o lote quando a segunda linha conflita, reenvio simultâneo sem duplicação, pesquisas nos quatro setores solicitados, autocompletes, logins independentes, acesso administrativo negado, CSRF e consulta de registros próprios. POSTs concorrentes usam dois cookies diferentes no mesmo Apache de 32 threads. O script encerra seu Apache, remove o schema e compara o conteúdo de nove tabelas públicas antes/depois. Resultado: **33 verificações aprovadas**, sem alteração das tabelas reais.

Testes de aplicação adicionais: **49 testes, 163 assertions**, cobrindo operação, reenvio, falha de lote e períodos. Comando:

```powershell
C:\xampp\php\php.exe vendor/phpunit/phpunit/phpunit --filter '/test(?!NoWeights)/' tests/TestCase/Service/OperacaoSimplificadaTest.php tests/TestCase/Service/EntryPeriodsTest.php tests/TestCase/Service/TimeEntryInputTest.php
```

O filtro exclui um teste antigo da Curva S (`testNoWeights...`), cujas expectativas de layout são anteriores à implementação atual; esse teste não pertence à correção operacional.

## Hospedagem com requisições simultâneas

`config/apache-pcm.conf.example` contém uma configuração independente, com a mesma base de Apache/PHP validada no teste. Aponta somente para `webroot`, tem 32 threads e usa a porta local 8767 para não interromper o servidor atual. A sintaxe pode ser conferida sem iniciar ou reiniciar serviços:

```powershell
C:\xampp\apache\bin\httpd.exe -t -f C:/PCM/Sistemas/PCM_ControleEntressafra/config/apache-pcm.conf.example
```

Para uso pela rede da empresa, adaptar o listener e o hostname ao endereço efetivo, com `App.fullBaseUrl` e HTTPS da implantação. Não substituir uma instância de produção pelo servidor de desenvolvimento `php -S`. A configuração fornecida não foi instalada no servidor da empresa, cujo endereço não foi informado.

## Arquivos da correção

Produção: `src/Service/Access.php`, `Users.php`, `Activities.php`, `TimeEntries.php`, `Insumos.php`, novo `EnvioLote.php`; `src/Controller/ApontamentosController.php`, `InsumosController.php`; `templates/Usuarios/form.php`, `Apontamentos/batch.php`, `Apontamentos/form.php`, `Insumos/add.php` e novo `templates/element/envio_chave.php`.

Testes e diagnóstico: `tests/TestCase/Service/OperacaoSimplificadaTest.php`, `tests/Support/OperationalTotvs.php`, `tools/diagnostico_operacao.php`, `verify_operational_concurrency.php`, `operational_concurrency_worker.php`, `operational_test_support.php`, `operational_http_server.php`, `apache_pcm_config.php`; exemplo de Apache e esta documentação. Os scripts auxiliares de gravação exigem flag explícita e schema temporário validado.
