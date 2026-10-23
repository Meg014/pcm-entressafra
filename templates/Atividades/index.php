<?php $this->assign('title', 'Atividades / O.S.'); ?>
<div class="page-heading"><h1>Atividades / O.S.</h1><div class="pcm-activity-actions d-flex flex-wrap gap-2">
<?php if (in_array($currentUser->perfil,['ADMIN_PCM','PCM'],true)): ?><?= $this->Html->link('Abrir caderno e vincular O.S. por lista', ['controller'=>'Cadernos','action'=>'index'], ['class'=>'btn btn-primary']) ?><?php endif; ?>
<?= $this->Html->link('Vincular O.S. individual', ['action' => 'add'], ['class' => 'btn btn-outline-secondary']) ?>
</div></div>
<section class="panel mt-4"><?= $this->element('operation_filters') ?>
<div class="table-responsive"><table class="table"><thead><tr><th>O.S.</th><th>Descrição / Setor</th><th>Entressafra / Setor / Caderno</th><th>Situação na consulta</th><th>Apontamentos / horas / evolução</th><th>Ação</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= h($row->os_numero) ?></td><td><?= $this->element('os_identificacao',['order'=>['numero'=>$row->os_numero]+\App\Service\OsApresentacao::activity($row)]) ?></td><td><?= h($row->entressafra->nome) ?> · <br><?= h(($row->caderno?->nome??'Sem caderno')) ?></td><td><?= h($row->situacao_totvs) ?></td><td><?= $this->element('activity_metrics',['m'=>$metrics[$row->id]??[],'percentage'=>$progress[$row->id]??0]) ?></td><td><?= $this->Html->link('Visualizar O.S.', ['action' => 'view', $row->id]) ?></td></tr><?php endforeach; ?>
<?php if (!$rows->count()): ?><tr><td colspan="6">Nenhuma atividade encontrada.</td></tr><?php endif; ?></tbody></table></div>
<?= $this->element('operation_pagination') ?></section>
