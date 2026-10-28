<?php $this->assign('title','Calendário e apuração semanal'); ?>
<div class="page-heading"><h1>Calendário e apuração semanal</h1><?= $this->Html->link('Voltar à Curva S',['action'=>'controle','?'=>['area_id'=>$area]],['class'=>'btn btn-light']) ?></div>
<section class="panel my-3">
<?= $this->Form->create(null,['type'=>'get','class'=>'d-flex gap-2 align-items-end']) ?>
<?= $this->Form->control('area_id',['type'=>'select','options'=>$areas,'value'=>$area,'label'=>'Setor principal','class'=>'form-select']) ?><?= $this->Form->button('Selecionar',['class'=>'btn btn-primary']) ?><?= $this->Form->end() ?>
</section>
<section class="panel mb-3"><h2>Programado automático — modelo histórico</h2>
<p class="small text-secondary">Referência estimada calculada pelo prazo operacional do setor. Não é um cronograma aprovado e não exige metas semanais. O realizado não altera esta linha.</p>
<?php if($weekly['automatic']['calendario']['configurado']): ?><p><?= h($weekly['automatic']['calendario']['regra']) ?> · <?= h($weekly['automatic']['posicao']['total']) ?> dias operacionais.</p><?php else: ?><p class="alert alert-warning"><?= h($weekly['automatic']['calendario']['motivo']) ?></p><?php endif; ?>
<div class="table-responsive"><table class="table"><thead><tr><th>Semana</th><th>Período</th><th>Análise</th><th>Referência automática (%)</th></tr></thead><tbody>
<?php foreach($weekly['points'] as $point): ?><tr><td><?= h($point['semana']) ?></td><td><?= h($point['inicio'].' a '.$point['fim']) ?></td><td><?= h($point['referencia']) ?></td><td><?= $point['programado']===null?'—':h(number_format($point['programado'],2,',','.').'%') ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<section class="panel mb-3"><h2>Dias do calendário operacional existente</h2>
<?php if($calendar): ?>
<p>Calendário <?= h($calendar['chave']) ?> · <?= h($calendar['data_inicio']??'início não confirmado') ?> a <?= h($calendar['data_fim']) ?>.</p>
<?php if(!$calendar['inicio_confirmado']||!$calendar['data_inicio']): ?><p class="small text-secondary">Confirme a data de início em <?= $this->Html->link('Prazos',['controller'=>'Prazos','action'=>'index']) ?> para habilitar a contagem de dias operacionais.</p><?php endif; ?>
<?php if($calendar['chave']==='padrao'): ?><p class="small text-secondary">Este é o calendário padrão compartilhado pelos setores sem calendário próprio.</p><?php endif; ?>
<?= $this->Form->create(null) ?><?= $this->Form->hidden('acao',['value'=>'dias']) ?>
<?= $this->Form->control('dias',['type'=>'select','multiple'=>'checkbox','options'=>[1=>'Segunda',2=>'Terça',3=>'Quarta',4=>'Quinta',5=>'Sexta',6=>'Sábado',7=>'Domingo'],'value'=>json_decode($calendar['dias_semana']??'[]',true),'label'=>'Dias operacionais da semana']) ?>
<?= $this->Form->control('excluidos',['type'=>'textarea','rows'=>2,'label'=>'Datas não operacionais (AAAA-MM-DD, separadas por espaço)','value'=>implode(' ',json_decode($calendar['dias_excluidos']??'[]',true)),'class'=>'form-control']) ?>
<?= $this->Form->control('motivo',['label'=>'Justificativa dos dias operacionais','required'=>true,'class'=>'form-control','maxlength'=>3000]) ?>
<?= $this->Form->button('Salvar dias neste calendário',['class'=>'btn btn-outline-primary mt-2']) ?><?= $this->Form->end() ?>
<?php else: ?><p>Configure primeiro as datas em <?= $this->Html->link('Setores',['controller'=>'Setores','action'=>'index']) ?> ou <?= $this->Html->link('Prazos',['controller'=>'Prazos','action'=>'index']) ?>.</p><?php endif; ?>
</section>
<section class="panel mb-3"><h2>Apuração das semanas encerradas</h2><p class="small text-secondary">Apurar novamente conserva a versão existente. Correção autorizada cria outra versão, mantém a lista original de O.S. e aceita registros retroativos disponíveis até o momento da correção.</p>
<?php $today=(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');foreach($weekly['points'] as $point): if($point['referencia']>$today)continue; ?>
<details class="border-bottom py-2"><summary><?= h('Análise '.$point['referencia'].' · '.$point['inicio'].' a '.$point['fim']) ?> · <?= $point['versao_id']?'Versão #'.h($point['versao_id']):'Não apurada' ?></summary>
<?= $this->Form->create(null,['class'=>'my-2']) ?><?= $this->Form->hidden('referencia',['value'=>$point['referencia']]) ?>
<?php if($point['versao_id']): ?><?= $this->Form->hidden('acao',['value'=>'corrigir']) ?><?= $this->Form->hidden('base',['value'=>$point['versao_id']]) ?><?= $this->Form->control('motivo',['label'=>'Justificativa da correção retroativa','required'=>true,'maxlength'=>3000,'class'=>'form-control']) ?><?= $this->Form->button('Registrar nova versão da apuração',['class'=>'btn btn-outline-primary mt-2']) ?>
<?php else: ?><?= $this->Form->hidden('acao',['value'=>'apurar']) ?><?= $this->Form->button('Apurar semana encerrada',['class'=>'btn btn-primary']) ?><?php endif; ?><?= $this->Form->end() ?>
</details><?php endforeach; ?></section>
<section class="panel" id="historico-versoes"><h2>Histórico de versões</h2>
<?php foreach(array_merge($weekly['plans'],$weekly['versions']) as $version): ?><div class="border-bottom py-2"><?= h('#'.$version['id'].' · '.$version['nome'].' · '.$version['created'].' UTC') ?> · <?= $this->Html->link('Consultar composição',['action'=>'programacaoSemanal','?'=>['area_id'=>$area,'versao_id'=>$version['id']],'#'=>'historico-versoes']) ?></div><?php endforeach; ?>
<?php if($selectedVersion): ?><h3 class="mt-3">Composição da versão #<?= h($selectedVersion['id']) ?></h3><pre class="small text-wrap text-break"><?= h(json_encode(json_decode($selectedVersion['conteudo'],true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre><?php endif; ?>
<?php if(!$weekly['plans']&&!$weekly['versions']): ?><p>Nenhuma versão registrada.</p><?php endif; ?></section>
