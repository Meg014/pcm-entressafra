# Menu operacional e acesso à vinculação em lote

A funcionalidade existente não foi recriada. A rota GET `/vinculos-lote`,
VinculosLoteController, a tela e as proteções PCM/ADMIN_PCM foram preservadas.
O botão que antes era secundário agora é o acesso principal em Atividades / O.S.
A vinculação individual permanece como opção secundária, com a autorização anterior.

## Navegação

- Menu principal: Visão geral, Atividades / O.S., Apontamentos e Consulta PCM.
  Consulta PCM permanece visível apenas aos perfis já autorizados.
- Clicar no nome do usuário, no canto superior direito, abre os cadastros e a conta.
- ADMIN_PCM: Entressafras, Setores, Cadernos, Usuários e permissões e Vincular O.S. em lote.
- PCM: Entressafras, Setores, Cadernos e Vincular O.S. em lote, conforme as permissões existentes.
- RESPONSAVEL_SETOR: não vê opções administrativas; o menu da conta oferece Sair.
- Sair usa a rota POST existente e proteção CSRF; não depende de um link GET.
- A página atual recebe destaque. Não foram adicionados links para configurações,
  Evolução %, Curva S ou outros módulos não implementados.

O menu usa o elemento nativo details/summary, aberto por clique/toque ou teclado,
com JavaScript local para fechamento por Esc, clique externo e escolha de opção,
além de setas, Home/End e controle de foco. Tab mantém a navegação natural.
Não requer CDN ou bibliotecas novas. As regras de autorização no backend não mudaram.

## Como validar no navegador

1. Atualizar com Ctrl+F5 e entrar no sistema com o perfil autorizado.
2. Clicar no nome do usuário. Conferir os cadastros e acessar cada opção.
3. Fechar por clique externo, Esc e escolha de opção. Usar Tab, Enter/Espaço
   no nome do usuário e setas/Home/End nas opções.
4. Abrir Atividades / O.S. e clicar no botão principal **Vincular O.S. em Lote**.
5. Conferir o endereço http://127.0.0.1:8766/vinculos-lote e a tela de preparação
   de prévia. O acesso também está no menu do usuário; nenhum vínculo é automático.
6. Repetir com PCM e RESPONSAVEL_SETOR. Este último não vê os acessos administrativos
   e continua recebendo bloqueio no backend ao tentar a vinculação em lote.
7. Conferir em uma janela larga e em celular/janela estreita: navegação quebra
   em linhas, menu permanece dentro da largura disponível e botões empilham.
8. Usar Sair no menu; o acesso protegido deve voltar a exigir login.

Para o exemplo CALDEIRA / serviço ESCAL, SELECT real confirmou 45 O.S.
elegíveis em **TJ_CODAREA=CALD / TJ_FILIAL=01**, com origem em 2026,
situação L, término N e serviço ENTRESSAFRA. Na área CALDEI, ESCAL retornou zero.
O PCM precisa conferir esse mapeamento no setor de destino e escolher o caderno
correto; nenhum dado de configuração foi alterado automaticamente nesta tarefa.

## Verificação executada

- Suíte existente e novos testes: **115 testes / 750 verificações aprovados**.
- Testes HTTP da aplicação em SQLite isolado: rota, navegação, botão principal,
  cadastros permitidos, três perfis, destaque da página e logout com CSRF.
- Testes JavaScript com DOM simulado: abertura, fechamento externo/Esc/seleção,
  setas, Home/End, foco e saída. Não equivalem à conferência visual em navegador.
- CSS com regras responsivas para desktop e celular, foco visível e movimento reduzido.
- Consulta real ESCAL: exclusivamente SELECT no TOTVS, sem criação de prévia persistida.

Não foram executadas migrations nem gravações no PostgreSQL/TOTVS. Os testes
de gravação existentes usam somente o banco SQLite de testes. A conferência visual
em navegador desktop/celular fica disponível pelo procedimento acima.
