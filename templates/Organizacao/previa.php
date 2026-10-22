<?php $this->assign('title','Conferir classificação principal'); ?>
<h1>Conferir classificação principal da curva</h1><section class="panel">
<p><?= h($total) ?> registro(s) selecionado(s). Destino: <strong><?= h($plan['area']?($areas[$plan['area']]??'Área'):'Herdar do caderno / pendente') ?></strong>.</p>
<?php if($plan['type']==='caderno'): ?><p><?= count($plan['affected']) ?> O.S. neste caderno. Aquelas com exceção individual mantêm a própria classificação. Os demais vínculos do caderno não serão removidos; haverá uma única área principal de contabilização.</p><?php endif; ?>
<p>Justificativa: <?= h($plan['reason']) ?></p>
<table class="table"><thead><tr><th>Registro</th><th>Classificação individual/principal atual</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><?= h($row['nome']) ?></td><td><?= h($areas[$row['atual']]??'Herdada ou pendente') ?></td></tr><?php endforeach; ?></tbody></table>
<?= $this->element('simple_pagination') ?>
<?= $this->Form->create(null) ?><?= $this->Form->button('Confirmar os '.$total.' registro(s) da prévia',['class'=>'btn btn-primary mt-3']) ?><?= $this->Form->end() ?>
<?= $this->Html->link('Cancelar',['action'=>'index','?'=>['entressafra_id'=>$plan['season']]],['class'=>'btn btn-light mt-2']) ?></section>
