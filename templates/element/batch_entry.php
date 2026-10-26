<?php
$value=static fn(string $key): string => is_scalar($line[$key]??null)?(string)$line[$key]:'';
$prefix='apontamentos.'.$index.'.';
?>
<article class="entry-line">
<div class="entry-number small text-secondary"><span><?= is_int($index)?$index+1:'' ?></span></div>
<div class="entry-fields">
<div class="entry-employee">
<?php
// Reuse the existing accessible employee widget with unique IDs and field names.
ob_start();
echo $this->element('employee_autocomplete',['employeeSearchLabel'=>$value('funcionario_rotulo'),'selectedEmployee'=>$value('funcionario_chave')!==''?['chave'=>$value('funcionario_chave'),'nome'=>$value('funcionario_rotulo'),'matricula'=>'']:null]);
$widget=ob_get_clean();
$widget=str_replace('id="funcionario-', 'id="row-'.$index.'-funcionario-', $widget);
$widget=str_replace(['for="funcionario-','aria-controls="funcionario-','aria-describedby="funcionario-'],['for="row-'.$index.'-funcionario-','aria-controls="row-'.$index.'-funcionario-','aria-describedby="row-'.$index.'-funcionario-'],$widget);
$widget=str_replace(['name="funcionario_rotulo"','name="funcionario_chave"'],['name="apontamentos['.$index.'][funcionario_rotulo]"','name="apontamentos['.$index.'][funcionario_chave]"'],$widget);
$widget=str_replace('Pesquisar funcionário por nome ou matrícula *','Funcionário *',$widget);
$widget=str_replace('Digite pelo menos 2 caracteres e selecione um funcionário NORMAL. O nome exibido vem de RA_NOME e pode estar abreviado no TOTVS.','Pesquise nome ou matrícula e selecione um funcionário NORMAL.',$widget);
$widget=str_replace('>Limpar</button>',' title="Limpar funcionário" aria-label="Limpar funcionário">×</button>',$widget);
echo $widget;
?>
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
