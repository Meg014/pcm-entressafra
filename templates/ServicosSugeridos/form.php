<?php $this->assign('title',$row?'Editar sugestão':'Adicionar sugestão'); ?>
<h1><?= h($this->fetch('title')) ?></h1>
<section class="panel form-panel mt-3">
<?= $this->Form->create(null) ?>
<?= $this->Form->control('descricao',['label'=>'Descrição','type'=>'textarea','rows'=>3,'maxlength'=>500,'required'=>true,'value'=>$this->request->getData('descricao')??$row?->descricao,'class'=>'form-control']) ?>
<?= $this->Form->control('ativo',['label'=>'Sugestão ativa','type'=>'checkbox','checked'=>$this->request->is('post')?(bool)$this->request->getData('ativo'):($row?->ativo??true)]) ?>
<?= $this->Form->button('Salvar',['class'=>'btn btn-primary']) ?>
<?= $this->Html->link('Voltar',['action'=>'index'],['class'=>'btn btn-secondary']) ?>
<?= $this->Form->end() ?></section>
