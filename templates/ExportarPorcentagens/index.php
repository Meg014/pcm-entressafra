<?php $this->assign('title', 'Exportar porcentagens — Excel'); ?>
<h1>Exportar porcentagens — Excel</h1>
<?= $this->Form->create(null, ['type'=>'get', 'url'=>['controller'=>'ExportarPorcentagens','action'=>'index']]) ?>
<?php $options=[]; foreach ($seasons as $id=>$s) $options[$id]=$s->nome; ?>
<?= $this->Form->control('entressafra_id', ['label'=>'Entressafra','options'=>$options,'value'=>$season,'onchange'=>"this.form.elements.area_id.value=''; this.form.submit()"] ) ?>
<?= $this->Form->control('area_id', ['label'=>'Setor','options'=>$areas,'empty'=>'Selecione o setor','value'=>$area]) ?>
<?= $this->Form->button('Baixar Excel', ['name'=>'baixar','value'=>'1','class'=>'btn btn-primary']) ?>
<?= $this->Form->end() ?>
