<?php $this->assign('title','Novo apontamento');
$lines=$this->request->getData('apontamentos');
if (!is_array($lines) || !$lines) { $lines=[[]]; }
?>
<h1>Novo apontamento</h1>
<?= $this->Html->css('apontamentos-batch.css?v='.filemtime(WWW_ROOT.'css/apontamentos-batch.css')) ?>
<section class="panel entry-sheet">
<?= $this->Form->create(null,['id'=>'batch-form']) ?>
<?= $this->Form->hidden('quantidade_linhas',['value'=>count($lines),'id'=>'entry-count']) ?>
<?= $this->element('order_autocomplete',['quickOrder'=>true,'completionRequired'=>true]) ?>
<div id="entry-order-status" class="mb-3 alert <?= !empty($selectedOrder['liberada_apontamento'])?'alert-success':'alert-warning' ?>" role="status" data-order="<?= h(json_encode($selectedOrder,JSON_THROW_ON_ERROR)) ?>" <?= $selectedOrder?'':'hidden' ?>><?php if($selectedOrder): ?>Percentual atual da O.S.: <?= h($selectedOrder['percentual_atual']??0) ?>% · <?= !empty($selectedOrder['liberada_apontamento'])?'Liberada para apontamento.':h(\App\Service\OrderCompletion::MESSAGE) ?><?php endif; ?></div>
<div class="entry-head" aria-hidden="true"><span>Funcionário</span><span>Data</span><span>Hora início</span><span>Hora fim</span><span>Serviço realizado</span><span>Ações</span></div>
<div id="entry-lines">
<?php foreach(array_values($lines) as $index=>$line): ?>
<?= $this->element('batch_entry',['index'=>$index,'line'=>is_array($line)?$line:[],'error'=>$batchErrors[$index]??'']) ?>
<?php endforeach; ?>
</div>
<button type="button" id="add-entry" class="entry-icon entry-add" aria-label="Adicionar apontamento" title="Adicionar linha">+</button>
<div class="entry-footer">
<?= $this->Form->button('Salvar apontamentos',['class'=>'btn btn-primary','id'=>'entry-save','disabled'=>true]) ?>
<?= $this->Html->link('Voltar',['action'=>'index'],['class'=>'btn btn-light']) ?>
</div>
<?= $this->Form->end() ?>
</section>
<template id="entry-template"><?= $this->element('batch_entry',['index'=>'__INDEX__','line'=>[],'error'=>'']) ?></template>
<?= $this->Html->script('employee-autocomplete.js?v='.filemtime(WWW_ROOT.'js/employee-autocomplete.js'),['block'=>true]) ?>
<?= $this->Html->script('apontamentos-batch.js?v='.filemtime(WWW_ROOT.'js/apontamentos-batch.js'),['block'=>true]) ?>
