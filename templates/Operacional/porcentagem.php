<?php $this->assign('title','Lançar porcentagem');$pcm=in_array($currentUser->perfil,['ADMIN_PCM','PCM'],true); ?>
<div class="page-heading"><h1>Lançar evolução da O.S.</h1><?php if($pcm): ?><?= $this->Html->link('Lançamento em lote',['controller'=>'Evolucao','action'=>'index'],['class'=>'btn btn-outline-secondary']) ?><?php endif; ?></div>
<section class="panel form-panel mt-3">
<?= $this->Form->create(null,['id'=>'porcentagem-rapida','data-initial'=>json_encode($selectedOrder,JSON_THROW_ON_ERROR)]) ?>
<?= $this->element('order_autocomplete',['quickOrder'=>true,'progressOrder'=>true]) ?>
<p>Último percentual acumulado: <strong id="rapida-ultimo"><?= h($selectedOrder['percentual']??0) ?>%</strong></p>
<?= $this->Form->hidden('chave',['value'=>$key]) ?><?= $this->Form->hidden('revisao',['id'=>'rapida-revisao','value'=>$selectedOrder['revisao']??'']) ?>
<?= $this->Form->hidden('referencia',['id'=>'rapida-referencia','value'=>$selectedOrder['referencia']??'']) ?>
<p class="small text-secondary">Referência semanal automática: <span id="rapida-semana"><?= h($reference??'Selecione a O.S.') ?></span> (São Paulo)</p>
<?= $this->Form->control('percentual',['id'=>'rapida-percentual','type'=>'number','label'=>'Quanto do serviço está concluído agora? (%)','min'=>0,'max'=>100,'step'=>'0.01','class'=>'form-control','required'=>true,'data-anterior'=>$selectedOrder['anterior']??0]) ?>
<small>Digite o total acumulado. Pode repetir o valor anterior para confirmar sem avanço. Não é obrigatório atualizar todas as O.S.</small>
<p id="rapida-avanco" class="mt-2" role="status">Avanço calculado: —</p>
<?php if($pcm): ?><details class="my-3"><summary>Correção ou referência retroativa (PCM)</summary>
<p>Selecione uma O.S. para acessar seu calendário.</p>
<label for="rapida-retroativa">Referência retroativa autorizada</label><select id="rapida-retroativa" class="form-select" data-url="<?= h($this->Url->build(['action'=>'porcentagem'])) ?>"><option value="">Referência automática</option></select>
<?php if($selectedOrder): ?><a class="btn btn-outline-secondary" href="<?= h($this->Url->build(['controller'=>'Evolucao','action'=>'index','?'=>['entressafra_id'=>$selectedOrder['entressafra_id'],'os'=>$selectedOrder['numero']]])) ?>">Abrir calendário e retificações em lote</a><?php endif; ?>
<?= $this->Form->control('motivo',['label'=>'Justificativa da retificação','class'=>'form-control','maxlength'=>3000]) ?></details><?php endif; ?>
<?= $this->Form->button('Salvar porcentagem',['class'=>'btn btn-primary mt-3']) ?><?= $this->Form->end() ?>
<p id="rapida-historico" class="mt-3" hidden><a id="rapida-historico-link" data-template="<?= h($this->Url->build(['controller'=>'Evolucao','action'=>'historico',1])) ?>">Histórico desta O.S.</a></p>
</section><?= $this->Html->script('porcentagem-rapida.js?v='.filemtime(WWW_ROOT.'js/porcentagem-rapida.js'),['block'=>true]) ?>
