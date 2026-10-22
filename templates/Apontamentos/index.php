<?php $this->assign('title', $this->request->getParam('action') === 'consulta' ? 'Consulta PCM' : 'Apontamentos'); ?>
<div class="page-heading"><h1><?= h($this->fetch('title')) ?></h1><?= $this->Html->link('Novo apontamento', ['action' => 'add'], ['class' => 'btn btn-primary']) ?></div>
<?php if($currentUser->perfil!=='RESPONSAVEL_SETOR'): ?><p class="mt-3"><?= $this->Html->link('Pendências de identificação de funcionários',['controller'=>'Funcionarios','action'=>'pendencias']) ?></p><?php endif; ?>
<section class="panel mt-4"><?= $this->element('operation_filters', ['entries' => true]) ?><?= $this->element('entries_table', ['rows' => $rows]) ?><?= $this->element('operation_pagination') ?></section>
