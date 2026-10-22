# Operação por setor e evolução sem confirmação de calendário

> A regra de apresentação da curva foi atualizada: equipe executante e área principal pelo caderno são independentes. Consulte [a documentação atual](CURVA_CADERNOS_E_LISTAS.md).

A O.S. usa a área executante TJ_CODAREA e a filial do TOTVS para identificar o setor autorizado. Os seis mapeamentos existentes (MECANI, ELETRI, CALDEI, CALD, TRATA e DESTIL) permanecem administráveis em Setores. Localização física, caderno e prazo não definem a autorização operacional.

## Banco e preservação

A inspeção do PostgreSQL confirmou `atividades.caderno_id` anulável. Apontamentos e percentuais referenciam a atividade, sem coluna obrigatória de caderno. `cadernos_setores.caderno_id` e `vinculos_lotes.caderno_id` continuam obrigatórios apenas nas respectivas associações e operações administrativas de vinculação.

Não foi necessária nova migration: **20261023090000_OrdensAutonomasCalendarios**, já aplicada, permite atividade sem caderno e garante unicidade por entressafra/filial/número. **20261021180000_CadernosMultiplosSetores** preserva a associação muitos-para-muitos. As migrations de proteção dos apontamentos e versões permanecem aplicadas. Não foram transferidos vínculos nem modificados históricos existentes.

O calendário de realização agora reúne as semanas das datas gerais da entressafra e todas as referências previamente registradas. Uma referência ausente é criada no salvamento, dentro da mesma transação e do bloqueio por entressafra. Datas arbitrárias e futuras continuam rejeitadas; erro de autorização, revisão ou percentual reverte também a criação da semana. A referência automática usa as semanas gerais, independentemente da aprovação de metas e das datas parciais de outros agrupamentos. Referências históricas continuam disponíveis ao PCM.

Os prazos padrão **26/03/2027** e de Moenda **10/04/2027** permanecem configurações de planejamento. Nenhum prazo físico é atribuído a uma equipe inteira por inferência. Metas, pesos e aprovações continuam opcionais para lançar realização.

## Validar as telas

1. Acesse http://localhost:8766/apontamentos/add com uma conta de computador. Pesquise uma O.S. aberta do setor autorizado, selecione funcionário, data, horários e serviço realizado. Salve. Não há seletor de entressafra, setor ou caderno. O backend reconsulta o TOTVS e mantém a trava global de horários.
2. Acesse http://localhost:8766/lancar-porcentagem. Selecione uma O.S. válida, inclusive sem caderno. Confira informações e acumulado anterior, informe o acumulado atual e salve. A semana é automática; não exige confirmar calendário, pesos, metas ou localização física.
3. Com perfil PCM, abra http://localhost:8766/controle-pcm. Selecione Mecânica e aplique os filtros. Confira O.S., serviço, equipamento, acumulado, evolução simples e histórico semanal. Repita para os demais setores. Deixe todos os setores e cadernos para a consolidação da entressafra. O caderno é filtro opcional.
4. A evolução simples é a média dos acumulados das O.S. únicas do filtro, identificada como **não ponderada**; O.S. sem lançamento contam como zero. O gráfico roxo e os grupos por setor funcionam sem pesos. Os indicadores ponderados oficiais permanecem separados. Sem metas aprovadas, aparece **Programação pendente**.
5. Clique no número da O.S. para consultar o histórico. O encerramento posterior no TOTVS não apaga percentuais, apontamentos ou auditorias. Retificações continuam exigindo perfil PCM e justificativa.
6. Confira uma conta sem acesso ao setor: a O.S. deve ficar indisponível para seleção/gravação. Tente horários sobrepostos para o mesmo funcionário: o apontamento deve ser rejeitado.

## Verificações

```powershell
php bin/cake.php migrations status
php vendor/phpunit/phpunit/phpunit --no-progress
php tools/verify_setores_calendario.php
```

O último comando consulta a O.S. 005300 real no TOTVS somente por SELECT. Testa identidade idempotente, percentual sem caderno/calendário, evolução simples e histórico encerrado em transação revertida. Compara hashes de 14 tabelas antes/depois. Não mantém dados fictícios nem altera o TOTVS. A conta deve poder acessar o PostgreSQL exclusivo e existir exatamente uma entressafra ativa.

Os testes automatizados também cobrem permissões de computador, O.S. previamente vinculadas, continuidade das semanas após o primeiro lançamento automático, rejeição de referências inválidas, prazos independentes, contagem por O.S., CSRF e conflitos de horários.

Resultado da validação: **197 testes e 1.190 asserções aprovados**, incluindo 14 testes específicos de ordens/calendário. Sintaxe PHP, verificação PostgreSQL com rollback e hashes de 14 tabelas, e verificações HTTP reais dos três perfis também aprovadas. A inspeção visual no navegador deve seguir o roteiro acima.
