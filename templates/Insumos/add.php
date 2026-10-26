<?php
$this->assign('title','Lançar insumos');
$lines=$this->request->getData('insumos');if(!is_array($lines)||!$lines)$lines=[[]];
?>
<div class="page-heading mb-2"><h1>Lançar insumos</h1><?= $this->Html->link('Insumos registrados',['action'=>'index'],['class'=>'btn btn-light']) ?></div>
<?= $this->Html->css('apontamentos-batch.css?v='.filemtime(WWW_ROOT.'css/apontamentos-batch.css')) ?>
<section class="panel entry-sheet supplies-sheet">
<?= $this->Form->create(null,['id'=>'insumos-form']) ?>
<?= $this->Form->hidden('quantidade_linhas',['id'=>'supplies-count','value'=>count($lines)]) ?>
<?= $this->element('order_autocomplete',['quickOrder'=>true]) ?>
<div class="entry-head" aria-hidden="true"><span>Funcionário</span><span>Data</span><span>Material</span><span>Quantidade</span><span>Ações</span></div>
<div id="supplies-lines">
<?php foreach(array_values($lines) as $index=>$line): ?><?= $this->element('insumo_entry',['index'=>$index,'line'=>is_array($line)?$line:[],'error'=>$errors[$index]??'']) ?><?php endforeach; ?>
</div>
<button type="button" id="supplies-add" class="entry-icon entry-add" aria-label="Adicionar insumo" title="Adicionar linha">+</button>
<div class="entry-footer"><?= $this->Form->button('Salvar insumos',['class'=>'btn btn-primary']) ?></div>
<?= $this->Form->end() ?>
</section>
<template id="supplies-template"><?= $this->element('insumo_entry',['index'=>'__INDEX__','line'=>[],'error'=>'']) ?></template>
<?= $this->Html->script('employee-autocomplete.js?v='.filemtime(WWW_ROOT.'js/employee-autocomplete.js'),['block'=>true]) ?>
<?= $this->Html->script('insumos.js?v='.filemtime(WWW_ROOT.'js/insumos.js'),['block'=>true]) ?>
