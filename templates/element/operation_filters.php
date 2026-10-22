<?= $this->Form->create(null, ['type' => 'get', 'class' => 'row g-3 mb-4']) ?>
<?php foreach (['entressafra_id' => ['Entressafra', $seasons], 'setor_id' => ['Setor', $sectors], 'caderno_id' => ['Caderno', $notebooks]] as $field => [$label,$options]): if($field==='entressafra_id'&&isset($periodoSelecionado))continue; ?>
<div class="col-md-4"><?= $this->Form->control($field, ['label' => $label, 'options' => $options, 'empty' => 'Todos', 'class' => 'form-select', 'value' => $this->request->getQuery($field)]) ?></div>
<?php endforeach; ?>
<div class="col-md-4"><?= $this->Form->control('os', ['label' => 'Número da O.S.', 'class' => 'form-control', 'value' => $this->request->getQuery('os')]) ?></div>
<?php if ($entries ?? false): ?>
<div class="col-md-4"><?= $this->Form->control('funcionario', ['label' => 'Funcionário ou matrícula', 'class' => 'form-control', 'value' => $this->request->getQuery('funcionario')]) ?></div>
<?php foreach (['data_inicio' => 'Data inicial', 'data_fim' => 'Data final'] as $field => $label): ?><div class="col-md-2"><?= $this->Form->control($field, ['label' => $label, 'type' => 'date', 'class' => 'form-control', 'value' => $this->request->getQuery($field)]) ?></div><?php endforeach; ?>
<?php endif; ?>
<div class="col-12"><?= $this->Form->button('Filtrar', ['class' => 'btn btn-outline-primary']) ?> <?= $this->Html->link('Limpar filtros', ['action' => $this->request->getParam('action')], ['class' => 'btn btn-light']) ?></div>
<?= $this->Form->end() ?>
