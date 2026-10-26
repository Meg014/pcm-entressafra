<?php $value=static fn(string $key):string=>is_scalar($line[$key]??null)?(string)$line[$key]:'';$prefix='insumos.'.$index.'.'; ?>
<article class="entry-line">
<div class="entry-number"><span><?= is_int($index)?$index+1:'' ?></span></div>
<div class="entry-fields">
<div class="entry-employee"><?= $this->element('batch_employee',['index'=>$index,'value'=>$value,'fieldGroup'=>'insumos']) ?></div>
<div><?= $this->Form->control($prefix.'data',['label'=>'Data','type'=>'text','value'=>$value('data'),'placeholder'=>'DD/MM/AAAA','inputmode'=>'numeric','maxlength'=>10,'required'=>true,'class'=>'form-control','data-supply-date'=>true]) ?></div>
<div><?= $this->Form->control($prefix.'material',['label'=>'Material','type'=>'text','value'=>$value('material'),'maxlength'=>500,'required'=>true,'class'=>'form-control']) ?></div>
<div><?= $this->Form->control($prefix.'quantidade',['label'=>'Quantidade','type'=>'text','value'=>$value('quantidade'),'inputmode'=>'decimal','maxlength'=>19,'required'=>true,'class'=>'form-control','data-supply-quantity'=>true]) ?></div>
<div><button type="button" class="entry-icon supplies-remove" aria-label="Remover insumo" title="Remover linha">−</button></div>
</div>
<div class="entry-error text-danger" role="alert"><?= h($error) ?></div>
</article>
