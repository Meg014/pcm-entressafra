<?= $this->Form->create(null,['type'=>'get','class'=>'row g-3 mb-4']) ?>
<div class="col-md-3"><?php if(isset($periodoSelecionado)): ?><?= $this->Form->hidden('entressafra_id',['value'=>$periodoSelecionado->id]) ?><?php else: ?><?= $this->Form->control('entressafra_id',['label'=>'Entressafra','options'=>$seasons,'value'=>$season,'class'=>'form-select']) ?><?php endif; ?></div>
<div class="col-md-3"><?= $this->Form->control('setor_id',['label'=>'Setor executante','options'=>$sectors,'empty'=>'Todos autorizados','value'=>$this->request->getQuery('setor_id'),'class'=>'form-select']) ?></div>
<div class="col-md-3"><?= $this->Form->control('caderno_id',['label'=>'Caderno','options'=>$notebooks,'empty'=>'Todos','value'=>$this->request->getQuery('caderno_id'),'class'=>'form-select']) ?></div>
<div class="col-md-3"><?= $this->Form->control('referencia',['label'=>'Semana de referência','options'=>array_combine(array_column($weeks,'referencia'),array_column($weeks,'referencia')),'value'=>$reference,'class'=>'form-select']) ?></div>
<div class="col-md-3"><?= $this->Form->control('os',['label'=>'Número da O.S.','value'=>$this->request->getQuery('os'),'class'=>'form-control']) ?></div>
<div class="col-md-3"><?= $this->Form->control('bem',['label'=>'Bem / equipamento','value'=>$this->request->getQuery('bem'),'class'=>'form-control']) ?></div>
<div class="col-md-3"><?= $this->Form->control('pendentes',['type'=>'checkbox','label'=>'Somente sem atualização na semana','checked'=>(bool)$this->request->getQuery('pendentes')]) ?></div>
<div class="col-md-3 align-self-end"><?= $this->Form->button('Aplicar filtros',['class'=>'btn btn-outline-primary']) ?></div>
<?= $this->Form->end() ?>
