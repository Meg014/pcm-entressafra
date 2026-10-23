<?php $this->assign('title','O.S. sem Caderno'); ?>
<div class="page-heading"><div><h1>O.S. sem Caderno</h1><p>Entressafra <?= h($periodoSelecionado->nome) ?> · Conferência de pendências</p></div></div>
<section class="panel">
<p>Localize as O.S. disponíveis da entressafra e vincule as pendências ao caderno correto.</p>
<?= $this->Form->create(null) ?><?= $this->Form->button($data?'Atualizar consulta no TOTVS':'Consultar O.S. no TOTVS',['name'=>'acao','value'=>'consultar','class'=>'btn btn-outline-primary','disabled'=>$data&&in_array($data['job']->estado,['consultando','vinculando'],true)]) ?><?= $this->Form->end() ?>
<?php if($data): $job=$data['job'];$active=in_array($job->estado,['consultando','vinculando'],true); ?>
<p class="mt-3" role="status"><strong><?= h($data['pendentes']) ?> O.S. sem caderno</strong> · <span data-selected-count><?= h($data['selected']) ?></span> selecionada(s) · <?= h($job->consultadas) ?> O.S. consultada(s)<?= $job->estado==='consultando'?' — consulta em andamento; total parcial':'' ?></p>
<?php if($active): ?>
<div id="pendencias-progress" data-auto="<?= $paused?'0':'1' ?>">
<p><?= $job->estado==='consultando'?'Consultando o TOTVS em blocos de até 200 O.S.':'Vinculando as O.S. confirmadas em blocos, com revalidação.' ?> Você pode retomar esta tela após uma interrupção.</p>
<?php if($job->estado==='vinculando'): ?><p><?= h(($data['counts']['sucesso']??0)+($data['counts']['mesmo']??0)+($data['counts']['erro']??0)) ?>/<?= h($data['plan']['quantidade']) ?> O.S. processada(s) · <?= h($data['counts']['sucesso']??0) ?> vinculada(s)</p><?php endif; ?>
<?= $this->Form->create(null,['id'=>'pendencias-continuar']) ?><?= $this->Form->hidden('acao',['value'=>'continuar']) ?><?= $this->Form->button('Continuar processamento',['class'=>'btn btn-primary']) ?><?= $this->Form->end() ?>
<p id="pendencias-error" role="alert"></p></div>
<?php endif; ?>
<?php if($job->estado==='concluido'): ?>
<div class="alert alert-info mt-3"><strong>Resultado:</strong> <?= h($data['counts']['sucesso']??0) ?> vinculada(s), <?= h($data['counts']['mesmo']??0) ?> já vinculada(s), <?= h(($data['counts']['outro']??0)+($data['counts']['erro']??0)) ?> conflito(s)/erro(s), <?= h(($data['counts']['inelegivel']??0)+($data['counts']['nao_encontrada']??0)+($data['counts']['ambigua']??0)) ?> indisponível(is).
<p>As pendências continuam abaixo. Para nova seleção, atualize a consulta.</p>
<?php foreach($data['plan']['tokens'] as $part): ?><?= $this->Html->link('Ver resultados e pendências do lote',['controller'=>'Organizacao','action'=>'lista',$data['plan']['caderno_id'],'?'=>['token'=>$part]],['class'=>'d-block']) ?><?php endforeach; ?></div>
<?php endif; ?>
</section>
<section class="panel mt-3">
<?= $this->Form->create(null,['type'=>'get','class'=>'row g-3']) ?><?= $this->Form->hidden('token',['value'=>$token]) ?>
<?php $services=[''=>'Todos os serviços'];foreach($data['services'] as $service)$services[$service['servico_codigo']]=$service['servico_codigo'].' — '.$service['servico_nome'];$teams=[''=>'Todas as equipes'];foreach($data['teams'] as $team)$teams[$team]=$team;$states=[''=>'Todas as situações'];foreach($data['states'] as $state)$states[$state]=$state==='L'?'L — Liberada':$state; ?>
<div class="col-md-4"><?= $this->Form->control('servico',['label'=>'Tipo/código do serviço','options'=>$services,'value'=>$filters['servico'],'class'=>'form-select']) ?></div>
<div class="col-md-3"><?= $this->Form->control('equipe',['label'=>'Equipe executante TOTVS','options'=>$teams,'value'=>$filters['equipe'],'class'=>'form-select']) ?></div>
<div class="col-md-2"><?= $this->Form->control('situacao',['label'=>'Situação','options'=>$states,'value'=>$filters['situacao'],'class'=>'form-select']) ?></div>
<div class="col-md-3"><?= $this->Form->control('numero',['label'=>'Número da O.S.','value'=>$filters['numero'],'maxlength'=>6,'class'=>'form-control']) ?></div>
<div><?= $this->Form->button('Pesquisar',['class'=>'btn btn-outline-primary']) ?></div><?= $this->Form->end() ?>
<p class="mt-3" role="status"><?= h($data['total']) ?> resultado(s) neste filtro · <span data-selected-count><?= h($data['selected']) ?></span> selecionada(s) em todas as páginas</p>
<p id="pendencias-selection-status" role="status"></p>
<?= $this->Form->create(null,['id'=>'pendencias-selecao']) ?>
<div class="table-responsive"><table class="table"><thead><tr><th><input type="checkbox" id="pendencias-todas-pagina" aria-label="Marcar todas as O.S. desta página"></th><th>O.S.</th><th>Descrição da atividade</th><th>Serviço</th><th>Equipe executante TOTVS</th><th>Situação</th><th>Seleção</th></tr></thead><tbody>
<?php foreach($data['display'] as $entry): $row=$entry['row']; ?><tr><td><input type="hidden" name="exibidas[]" value="<?= h($row->numero) ?>"><input type="checkbox" name="numeros[]" value="<?= h($row->numero) ?>" aria-label="Marcar O.S. <?= h($row->numero) ?>" <?= $row->selecionada?'checked':'' ?> <?= $active||$job->estado==='concluido'?'disabled':'' ?>></td><td><?= h($row->numero) ?></td><td><?php if(mb_strlen($entry['descricao'])>180): ?><details><summary><?= h(mb_substr($entry['descricao'],0,180)) ?>…</summary><p class="mt-2" style="white-space:pre-line"><?= h($entry['descricao']) ?></p></details><?php else: ?><?= h($entry['descricao']) ?><?php endif; ?></td><td><?= h($row->servico_codigo) ?> — <?= h($row->servico_nome) ?></td><td><?= h($row->equipe) ?></td><td><?= h($row->situacao==='L'?'L — Liberada':$row->situacao) ?></td><td><?= $row->selecionada?'Selecionada':'—' ?></td></tr><?php endforeach; ?>
<?php if(!$data['display']): ?><tr><td colspan="7">Nenhuma O.S. sem caderno neste resultado.</td></tr><?php endif; ?></tbody></table></div>
<?php if($job->estado==='pronto'): ?><div class="d-flex flex-wrap gap-2">
<?php foreach(['pagina'=>'Salvar seleção da página','todas'=>'Selecionar todos os resultados filtrados','limpar'=>'Limpar seleção'] as $value=>$label): ?><?= $this->Form->button($label,['name'=>'acao','value'=>$value,'class'=>'btn btn-outline-primary']) ?><?php endforeach; ?></div><?php endif; ?>
<?= $this->Form->end() ?>
<?php $page=$data['page'];$pages=$data['pages']; ?><?= $this->element('simple_pagination',compact('page','pages')) ?>
<?php if($job->estado==='pronto'): ?>
<?= $this->Form->create(null,['class'=>'mt-4']) ?><?= $this->Form->control('caderno_id',['label'=>'Vincular selecionadas ao caderno','options'=>$notebooks,'empty'=>'Selecione um caderno','required'=>true,'class'=>'form-select','value'=>$data['plan']['caderno_id']??null]) ?><?= $this->Form->button('Vincular selecionadas',['name'=>'acao','value'=>'resumo','class'=>'btn btn-primary mt-2']) ?><?= $this->Form->end() ?>
<?php if($data['plan']): ?><div id="pendencias-resumo" class="alert alert-warning mt-3"><h2>Confirmar vínculos</h2><p><?= h($data['plan']['quantidade']) ?> O.S. para o caderno <strong><?= h($data['plan']['caderno']) ?></strong>. O setor da Curva S será o setor principal deste caderno. A equipe executante será preservada.</p><?= $this->Form->create(null) ?><?= $this->Form->hidden('confirmacao',['value'=>$data['plan']['confirmacao']]) ?><?= $this->Form->button('Confirmar vínculos',['name'=>'acao','value'=>'confirmar','class'=>'btn btn-primary']) ?><?= $this->Form->end() ?></div><?php endif; ?>
<?php endif; ?></section>
<?= $this->Html->script('os-sem-caderno.js?v='.filemtime(WWW_ROOT.'js/os-sem-caderno.js'),['block'=>true]) ?>
<?php endif; ?>
