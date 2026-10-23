<?php $this->assign('title', $row ? 'Corrigir apontamento' : 'Novo apontamento'); ?>
<h1><?= h($this->fetch('title')) ?></h1><p class="text-secondary">Informe a data do serviço e os horários. Marque “Termina no dia seguinte” somente quando houver virada de dia.</p>
<?php if ($conflicts): ?><section class="alert alert-danger" role="alert"><h2>Horários conflitantes</h2><?php foreach ($conflicts as $conflict): ?><div class="border-top py-3"><strong><?= h($conflict['nome']) ?> · Matrícula <?= h($conflict['matricula']) ?></strong><p class="mb-1"><?= h($conflict['inicio']) ?> até <?= h($conflict['fim']) ?> (horário de Brasília)</p><p class="mb-1">O.S. <?= h($conflict['os']) ?> · <?= h(\App\Service\OsApresentacao::equipe($conflict['setor'])) ?> · <?= h($conflict['caderno']) ?></p><p class="mb-0"><?= h($conflict['descricao']) ?></p></div><?php endforeach; ?></section><?php endif; ?>
<section class="panel form-panel"><?= $this->Form->create(null, ['id' => 'apontamento-form']) ?>
<?php if (!$row): ?>
<?= $this->Form->hidden('continuar',['value'=>'1']) ?>
<?= $this->Form->hidden('consultar_totvs',['value'=>'1']) ?>
<?= $this->element('order_autocomplete',['quickOrder'=>true]) ?>
<?= $this->element('employee_autocomplete') ?>
<?php else: ?><div class="d-flex justify-content-between align-items-center gap-2 border-bottom py-2 mb-3"><div><strong><?= h($row->funcionario_nome) ?></strong><small class="d-block text-secondary"><?= h('Matrícula '.$row->funcionario_matricula) ?></small></div><span class="badge bg-success">✓ Identificação preservada</span></div><?php endif; ?>
<?php
$start=$row?->inicio?->setTimezone('America/Sao_Paulo');$end=$row?->fim?->setTimezone('America/Sao_Paulo');
foreach(['inicio'=>'start','fim'=>'end'] as $old=>$target){$value=$this->request->getData($old);if(is_string($value)){$parsed=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$value,new \DateTimeZone('America/Sao_Paulo'));if($parsed)${$target}=$parsed;}}
$posted=$this->request->getData();$values=['data_servico'=>$start?->format('d/m/Y')??'','hora_inicial'=>$start?->format('H:i')??'','hora_final'=>$end?->format('H:i')??''];
foreach($values as $field=>$value){if(array_key_exists($field,$posted))$values[$field]=is_scalar($posted[$field])?(string)$posted[$field]:'';}
$next=array_key_exists('termina_dia_seguinte',$posted)?in_array($posted['termina_dia_seguinte'],['1',1,true],true):($start&&$end&&$end->format('Y-m-d')>$start->format('Y-m-d'));
?>
<div class="row g-3 mb-3">
<div class="col-12"><?= $this->Form->control('data_servico',['label'=>'Data do serviço *','type'=>'text','value'=>$values['data_servico'],'placeholder'=>'DD/MM/AAAA','maxlength'=>10,'inputmode'=>'numeric','autocomplete'=>'off','class'=>'form-control','required'=>true,'data-time-mask'=>'date']) ?></div>
<?php foreach(['hora_inicial'=>'Hora inicial *','hora_final'=>'Hora final *'] as $field=>$label): ?><div class="col-md-6"><?= $this->Form->control($field,['label'=>$label,'type'=>'text','value'=>$values[$field],'placeholder'=>'HH:MM','maxlength'=>5,'inputmode'=>'numeric','autocomplete'=>'off','class'=>'form-control','required'=>true,'data-time-mask'=>'time']) ?></div><?php endforeach; ?>
<div class="col-12"><?= $this->Form->control('termina_dia_seguinte',['label'=>'Termina no dia seguinte','type'=>'checkbox','value'=>'1','checked'=>(bool)$next]) ?></div>
</div>
<p id="horas-calculadas" class="text-secondary" aria-live="polite">As horas serão calculadas pelo intervalo informado.</p>
<div class="mb-3"><?= $this->Form->control('descricao', ['label' => 'Serviço realizado *', 'type' => 'textarea', 'value' => $this->request->getData('descricao') ?? $row?->descricao, 'class' => 'form-control', 'rows' => 4, 'maxlength' => 10000, 'required' => true]) ?></div>
<?php if ($row): ?><div class="mb-3"><?= $this->Form->control('motivo', ['label' => 'Motivo da correção *', 'class' => 'form-control', 'required' => true]) ?></div><?php endif; ?>
<?= $this->Form->button('Salvar apontamento', ['name' => 'acao', 'value' => 'salvar', 'class' => 'btn btn-primary']) ?> <?= $this->Html->link('Voltar', ['action' => 'index'], ['class' => 'btn btn-light']) ?><?= $this->Form->end() ?></section>
<?= $this->Html->script('apontamentos.js?v='.filemtime(WWW_ROOT.'js/apontamentos.js'), ['block' => true]) ?>
