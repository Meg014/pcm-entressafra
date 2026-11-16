<?php
$controller = $this->request->getParam('controller');
$action = $this->request->getParam('action');
$canPcm = !empty($currentUser) && in_array($currentUser->perfil,['ADMIN_PCM','PCM'],true);
$computer=\App\Service\Access::operational($currentUser??null);
$administrative = $canPcm ? ['Entressafras'=>'Entressafras','Setores'=>'Setores','Cadernos'=>'Cadernos'] : [];
if (!empty($currentUser) && $currentUser->perfil==='ADMIN_PCM') { $administrative['OsSemCaderno']='O.S. sem Caderno';$administrative['Usuarios']='Usuários e permissões';$administrative['ServicosSugeridos']='Serviços sugeridos'; }
if ($canPcm) { $administrative['Atividades']='Todas as atividades / O.S.'; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="<?= h($this->Url->image('icon.png?v='.filemtime(WWW_ROOT.'img/icon.png'))) ?>">
    <link rel="apple-touch-icon" href="<?= h($this->Url->image('icon.png?v='.filemtime(WWW_ROOT.'img/icon.png'))) ?>">
    <title><?= h($this->fetch('title') ?: 'Início') ?> | PCM Entressafra</title>
    <?= $this->Html->css(['bootstrap.min', 'pcm.css?v='.filemtime(WWW_ROOT.'css/pcm.css')]) ?>
    <?= $this->fetch('meta') ?><?= $this->fetch('css') ?>
</head>
<body>
<a class="visually-hidden-focusable" href="#conteudo">Ir para o conteúdo</a>
<header class="pcm-header">
    <div class="container-fluid px-lg-5 py-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <?= $this->Html->link($this->Html->image('icon.png?v='.filemtime(WWW_ROOT.'img/icon.png'), ['class'=>'brand-icon','alt'=>'PCM','width'=>60,'height'=>60]).'<span>Controle de Entressafra<small>Planejamento e execução industrial</small></span>', ['controller' => 'Dashboard', 'action' => 'index'], ['class' => 'brand', 'escape' => false]) ?>
        <?php if (!empty($currentUser)): ?>
        <details id="pcm-user-menu" class="pcm-user-menu">
            <summary id="pcm-user-toggle" aria-controls="pcm-user-options" aria-expanded="false" class="<?= isset($administrative[$controller]) ? 'selected' : '' ?>"><span><?= h($currentUser->nome) ?><small><?= h($computer?'Computador operacional':$currentUser->perfil) ?></small></span><span class="pcm-user-chevron" aria-hidden="true">⌄</span></summary>
            <div id="pcm-user-options" class="pcm-user-options">
                <?php if ($administrative): ?><p class="pcm-menu-label">Cadastros administrativos</p><nav aria-label="Cadastros administrativos">
                    <?php foreach ($administrative as $name=>$label): ?><?= $this->Html->link($label,['controller'=>$name,'action'=>'index'],['class'=>'pcm-user-link'.($controller===$name?' selected':''),'aria-current'=>$controller===$name?'page':'false']) ?><?php endforeach; ?>
                </nav><?php endif; ?>
                <p class="pcm-menu-label">Conta</p>
                <?= $this->Form->create(null,['url'=>['controller'=>'Auth','action'=>'logout','_method'=>'POST'],'type'=>'post','class'=>'pcm-logout-form']) ?>
                <?= $this->Form->button('Sair',['type'=>'submit','class'=>'pcm-user-link pcm-logout-button']) ?>
                <?= $this->Form->end() ?>
            </div>
        </details>
        <?php else: ?><span class="header-caption">GESTÃO DE MANUTENÇÃO</span><?php endif; ?>
    </div>
    <?php if(isset($periodoSelecionado)): ?><div class="container-fluid px-lg-5 pb-3 d-flex flex-wrap align-items-center gap-3"><strong>Entressafra <?= h($periodoSelecionado->nome) ?></strong><span><?= h($periodoSelecionado->data_inicio->format('d/m/Y')) ?> a <?= h($periodoSelecionado->data_fim->format('d/m/Y')) ?></span><?php if(!empty($periodoEncerrado)): ?><span class="badge text-bg-warning">Encerrada · consulta histórica</span><?php endif; ?><?php if($canPcm): ?><?= $this->Html->link('Trocar entressafra',['controller'=>'Periodos','action'=>'index'],['class'=>'text-white']) ?><?php endif; ?></div><?php endif; ?>
    <nav aria-label="Navegação principal" class="pcm-nav"><div class="container-fluid px-lg-5 d-flex flex-wrap">
        <?php if(!empty($currentUser)): ?>
        <?php if($canPcm): ?><?= $this->Html->link('Apontamentos recebidos',['controller'=>'FilaPcm','action'=>'index'],['class'=>'nav-item'.($controller==='FilaPcm'?' selected':''),'aria-current'=>$controller==='FilaPcm'?'page':'false']) ?><?php endif; ?>
        <?= $this->Html->link('Novo apontamento',['controller'=>'Apontamentos','action'=>'add'],['class'=>'nav-item'.($controller==='Apontamentos'&&$action==='add'?' selected':''),'aria-current'=>$controller==='Apontamentos'&&$action==='add'?'page':'false']) ?>
        <?= $this->Html->link('Insumos',['controller'=>'Insumos','action'=>'add'],['class'=>'nav-item'.($controller==='Insumos'?' selected':''),'aria-current'=>$controller==='Insumos'?'page':'false']) ?>
        <?= $this->Html->link('Lançar porcentagem',['controller'=>'Operacional','action'=>'porcentagem'],['class'=>'nav-item'.($controller==='Operacional'?' selected':''),'aria-current'=>$controller==='Operacional'?'page':'false']) ?>
        <?php endif; ?>
        <?php foreach (!empty($currentUser) && !$canPcm ? ($computer?['Apontamentos'=>'Histórico de apontamentos']:['Dashboard' => 'Visão geral', 'Atividades' => 'Atividades / O.S.', 'Apontamentos' => 'Apontamentos']) : [] as $name => $label): ?>
            <?php $active = $controller === $name && !($name==='Apontamentos' && $action!=='index') && !($name==='Evolucao' && $action!=='index'); ?>
            <?= $this->Html->link($label, ['controller' => $name, 'action' => 'index'], ['class' => 'nav-item ' . ($active ? 'selected' : ''), 'aria-current' => $active ? 'page' : 'false']) ?>
        <?php endforeach; ?>
        <?php if ($canPcm): ?><?= $this->Html->link('Consulta PCM', ['controller' => 'Apontamentos', 'action' => 'consulta'], ['class' => 'nav-item'.($controller==='Apontamentos' && $action==='consulta'?' selected':''),'aria-current'=>$controller==='Apontamentos' && $action==='consulta'?'page':'false']) ?><?php endif; ?>
        <?php if ($canPcm): ?><?= $this->Html->link('Controle PCM / Curva S',['controller'=>'Evolucao','action'=>'controle'],['class'=>'nav-item'.($controller==='Evolucao' && $action==='controle'?' selected':''),'aria-current'=>$controller==='Evolucao' && $action==='controle'?'page':'false']) ?><?php endif; ?>
    </div></nav>
</header>
<main id="conteudo" class="container-fluid px-lg-5 py-4 py-lg-5">
    <?php if(!empty($periodoEncerrado)): ?><div class="alert alert-info">Entressafra encerrada: consulta histórica. Novos lançamentos e alterações deste período estão bloqueados.</div><?php endif; ?>
    <?= $this->Flash->render() ?>
    <?= $this->fetch('content') ?>
</main>
<footer class="container-fluid px-lg-5 py-4 text-secondary small border-top">PCM · Controle de Entressafra</footer>
<?= $this->fetch('script') ?>
<?php if(!empty($periodoEncerrado)&&$controller!=='Usuarios'&&!($controller==='Entressafras'&&$action==='add')): ?><script>document.querySelectorAll('main form[method="post"]').forEach(form=>form.querySelectorAll('button,input,select,textarea').forEach(field=>field.disabled=true));</script><?php endif; ?>
<?php if (!empty($currentUser)): ?><?= $this->Html->script('user-menu.js?v='.filemtime(WWW_ROOT.'js/user-menu.js')) ?><?php endif; ?>
</body>
</html>
