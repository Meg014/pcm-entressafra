<?php $this->assign('title','Novo apontamento');
$lines=$this->request->getData('apontamentos');
if (!is_array($lines) || !$lines) { $lines=[[]]; }
?>
<h1>Novo apontamento</h1>
<?= $this->Html->css('apontamentos-batch.css?v='.filemtime(WWW_ROOT.'css/apontamentos-batch.css')) ?>
<section class="panel entry-sheet">
<?= $this->Form->create(null,['id'=>'batch-form']) ?>
<?= $this->element('envio_chave') ?>
<?= $this->Form->hidden('quantidade_linhas',['value'=>count($lines),'id'=>'entry-count']) ?>
<?= $this->element('order_autocomplete',['quickOrder'=>true,'showOrderProgress'=>true]) ?>
<div id="entry-order-status" class="mb-3 small text-secondary" role="status" data-order="<?= h(json_encode($selectedOrder,JSON_THROW_ON_ERROR)) ?>" <?= $selectedOrder?'':'hidden' ?>><?php if($selectedOrder): ?>Percentual atual da O.S.: <?= h($selectedOrder['percentual_atual']??0) ?>%<?php endif; ?></div>
<div class="entry-head" aria-hidden="true"><span>Funcionário</span><span>Data</span><span>Início manhã</span><span>Fim manhã</span><span>Início tarde</span><span>Fim tarde</span><span>Serviço realizado</span><span>Ações</span></div>
<div id="entry-lines">
<?php foreach(array_values($lines) as $index=>$line): ?>
<?= $this->element('batch_entry',['index'=>$index,'line'=>is_array($line)?$line:[],'error'=>$batchErrors[$index]??'']) ?>
<?php endforeach; ?>
</div>
<button type="button" id="add-entry" class="entry-icon entry-add" aria-label="Adicionar apontamento" title="Adicionar linha">+</button>
<div class="entry-footer">
<?= $this->Form->button('Salvar apontamentos',['class'=>'btn btn-primary','id'=>'entry-save']) ?>
<?= $this->Html->link('Voltar',['action'=>'index'],['class'=>'btn btn-light']) ?>
</div>
<?= $this->Form->end() ?>
</section>
<template id="entry-template"><?= $this->element('batch_entry',['index'=>'__INDEX__','line'=>[],'error'=>'']) ?></template>
<?= $this->Html->script('employee-autocomplete.js?v='.filemtime(WWW_ROOT.'js/employee-autocomplete.js'),['block'=>true]) ?>
<?= $this->Html->script('service-autocomplete.js?v='.filemtime(WWW_ROOT.'js/service-autocomplete.js'),['block'=>true]) ?>
<?= $this->Html->script('apontamentos-batch.js?v='.filemtime(WWW_ROOT.'js/apontamentos-batch.js'),['block'=>true]) ?>
