<?php $title = ($editing ? 'Editar ' : 'Cadastrar ') . mb_strtolower($singular); $this->assign('title', $title); ?>
<div class="page-heading"><div><p class="eyebrow"><?= h(mb_strtoupper($cadastro)) ?></p><h1><?= h($title) ?></h1><p class="text-secondary mb-0">Os campos marcados com * são obrigatórios.</p></div><?= $this->Html->link('Voltar à listagem', ['action' => 'index'], ['class' => 'btn btn-outline-secondary']) ?></div>
<section class="panel mt-4 form-panel">
<?= $this->Form->create($registro) ?>
<div class="mb-4"><?= $this->Form->control('nome', ['value'=>$cadastro==='Setores'?\App\Service\OsApresentacao::texto((string)$registro->nome):$registro->nome,'label' => 'Nome *', 'class' => 'form-control', 'maxlength' => $cadastro === 'Entressafras' ? 100 : 150, 'placeholder' => $cadastro === 'Entressafras' ? 'Ex.: 2026/2027' : 'Informe o nome', 'required' => true]) ?></div>
<?php if ($cadastro === 'Entressafras'): ?>
    <div class="row g-4 mb-4">
        <div class="col-md-6"><?= $this->Form->control('data_inicio', ['type' => 'date', 'label' => 'Data inicial *', 'class' => 'form-control', 'required' => true]) ?></div>
        <div class="col-md-6"><?= $this->Form->control('data_fim', ['type' => 'date', 'label' => 'Data final *', 'class' => 'form-control', 'required' => true]) ?></div>
    </div>
    <div class="mb-4"><?= $this->Form->control('status', ['label' => 'Status *', 'options' => \App\Model\Table\EntressafrasTable::STATUS, 'class' => 'form-select']) ?></div>
    <div class="mb-4"><?= $this->Form->control('observacoes', ['type' => 'textarea', 'label' => 'Observações', 'class' => 'form-control', 'rows' => 4]) ?></div>
<?php elseif($cadastro==='Cadernos'): ?>
    <?= $this->Form->hidden('entressafra_id',['value'=>$periodoSelecionado->id]) ?>
    <div class="mb-4"><?= $this->Form->control('area_curva_id',['label'=>'Setor principal da Curva S *','options'=>$areasPrincipais,'empty'=>'Selecione o setor','required'=>true,'class'=>'form-select']) ?></div>
    <p class="text-secondary">Cada caderno pertence a um setor principal. As equipes executantes das O.S. serão identificadas automaticamente no TOTVS.</p>
    <?php if($areasPrincipais->isEmpty()): ?><p class="alert alert-info">Cadastre um setor da Curva S nesta entressafra.</p><?php endif; ?>
<?php else: ?>
    <?php if($cadastro==='Setores'): ?><div class="row g-4 mb-4"><div class="col-md-8"><?= $this->Form->control('area_totvs',['label'=>'Área executante TOTVS (TJ_CODAREA)','class'=>'form-control','maxlength'=>6]) ?></div><div class="col-md-4"><?= $this->Form->hidden('filial_totvs',['value'=>$registro->filial_totvs?:'01']) ?></div><small class="text-secondary">Mapeamento explícito para o vínculo das O.S. Deixe a área vazia se ainda não foi conferida.</small></div><?php endif; ?>
    <div class="mb-4"><?= $this->Form->control('descricao', ['type' => 'textarea', 'label' => 'Descrição', 'class' => 'form-control', 'rows' => 4]) ?></div>
    <div class="mb-4"><?= $this->Form->control('ativo', ['type' => 'checkbox', 'label' => 'Ativo', 'class' => 'form-check-input me-2']) ?></div>
<?php endif; ?>
<div class="d-flex gap-2 border-top pt-4"><?= $this->Form->button('Salvar cadastro', ['class' => 'btn btn-primary']) ?><?= $this->Html->link('Cancelar', ['action' => 'index'], ['class' => 'btn btn-light']) ?></div>
<?= $this->Form->end() ?>
</section>
