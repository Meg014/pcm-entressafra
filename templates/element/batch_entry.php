<?php
$value=static fn(string $key): string => is_scalar($line[$key]??null)?(string)$line[$key]:'';
$prefix='apontamentos.'.$index.'.';
?>
<article class="entry-line">
<div class="entry-number small text-secondary"><span><?= is_int($index)?$index+1:'' ?></span></div>
<div class="entry-fields">
<div class="entry-employee">
<?= $this->element('batch_employee',compact('index','value')) ?>
</div>
<?php foreach(['data_servico'=>['Data','DD/MM/AAAA',10],'hora_inicial'=>['Hora início','HH:MM',5],'hora_final'=>['Hora fim','HH:MM',5]] as $field=>[$label,$placeholder,$length]): ?>
<div><?= $this->Form->control($prefix.$field,['type'=>'text','label'=>$label,'value'=>$value($field),'placeholder'=>$placeholder,'maxlength'=>$length,'inputmode'=>'numeric','class'=>'form-control','required'=>true,'data-mask'=>$field==='data_servico'?'date':'time']) ?></div>
<?php endforeach; ?>
<div><?= $this->Form->control($prefix.'descricao',['type'=>'textarea','label'=>'Serviço realizado','value'=>$value('descricao'),'class'=>'form-control','rows'=>1,'maxlength'=>10000,'required'=>true]) ?></div>
<div><button type="button" class="entry-icon remove-entry" aria-label="Remover apontamento" title="Remover linha">−</button></div>
</div>
<div class="entry-duration small text-secondary" aria-live="polite"></div>
<div class="entry-error text-danger" role="alert"><?= h($error) ?></div>
</article>
