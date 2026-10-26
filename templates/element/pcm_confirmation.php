<div class="row g-3 mt-1">
<div class="col-md-5"><?= $this->Form->control('acao',['id'=>$category.'-acao','label'=>'Operação em '.$label,'options'=>['lancado'=>'Confirmar lançamento manual no TOTVS','pendente'=>'Reabrir confirmação (exige justificativa)'],'class'=>'form-select']) ?></div>
<div class="col-md-7"><?= $this->Form->control('motivo',['id'=>$category.'-motivo','label'=>'Justificativa / referência','class'=>'form-control','maxlength'=>3000]) ?></div>
</div>
<?= $this->Form->control('confirmar',['id'=>$category.'-confirmar','type'=>'checkbox','label'=>'Confirmo a operação exclusivamente nos '.$label.' selecionados.','value'=>'1','required'=>true]) ?>
<?= $this->Form->button('Confirmar '.$label.' selecionados',['class'=>'btn btn-primary mt-2']) ?>
