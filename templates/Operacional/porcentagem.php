<?php $this->assign('title','Lançar porcentagem'); ?>
<?= $this->Html->css('porcentagens-lote.css?v='.filemtime(WWW_ROOT.'css/porcentagens-lote.css'),['block'=>true]) ?>
<div class="page-heading"><h1>Lançar evolução das O.S.</h1></div>
<section class="panel" id="porcentagens-lote" data-storage="pcm-percentuais-u<?= h($currentUser->id) ?>-p<?= h($season) ?>-a<?= h($area) ?>" data-reference="<?= h($batch['reference']) ?>">
<?= $this->Form->create(null,['type'=>'get','id'=>'porcentagens-filtros','class'=>'row g-3 mb-3']) ?>
<div class="col-md-6"><?= $this->Form->control('area_id',['label'=>'Setor da Curva S','options'=>$batch['areas'],'empty'=>'Selecione o setor','value'=>$area,'class'=>'form-select']) ?></div>
<div class="col-md-6"><?= $this->Form->control('os',['label'=>'Pesquisar O.S.','value'=>$search,'placeholder'=>'Número da O.S.','maxlength'=>6,'class'=>'form-control','inputmode'=>'numeric']) ?></div>
<?= $this->Form->end() ?>
<?php if($area): ?>
<p><?= h($batch['total']) ?> O.S. · <span id="porcentagens-pendentes" role="status">Nenhuma alteração pendente.</span></p>
<p id="porcentagens-erro" role="alert"></p>
<?= $this->Form->create(null,['id'=>'porcentagens-form','data-key'=>$key,'data-pending'=>json_encode($pending,JSON_THROW_ON_ERROR)]) ?>
<?= $this->Form->hidden('chave',['id'=>'porcentagens-chave','value'=>$key]) ?><?= $this->Form->hidden('alteracoes_json',['id'=>'porcentagens-json','value'=>'[]']) ?>
<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>O.S.</th><th>Descrição da atividade</th><th>Bem / equipamento</th><th>Concluído (%)</th></tr></thead><tbody>
<?php foreach($batch['rows'] as $row): ?><tr><td><?= h($row['numero']) ?></td><td><?php if(mb_strlen($row['descricao'])>160): ?><details><summary><?= h(mb_substr($row['descricao'],0,160)) ?>…</summary><div style="white-space:pre-line"><?= h($row['descricao']) ?></div></details><?php else: ?><?= h($row['descricao']) ?><?php endif; ?></td><td><?= h($row['bem']) ?></td><td><input type="number" class="form-control" style="min-width:110px;max-width:160px" min="0" max="100" step="0.01" value="<?= h($row['percentual']) ?>" aria-label="Percentual total da O.S. <?= h($row['numero']) ?>" title="Último valor salvo: <?= h($row['percentual']) ?>%" data-percentage data-id="<?= h($row['id']) ?>" data-base="<?= h($row['percentual']) ?>" data-revision="<?= h($row['revisao']) ?>"></td></tr><?php endforeach; ?>
<?php if(!$batch['rows']): ?><tr><td colspan="4">Nenhuma O.S. vinculada neste resultado.</td></tr><?php endif; ?></tbody></table></div>
<nav class="d-flex gap-3 my-3" aria-label="Paginação de porcentagens"><span>Página <?= h($batch['page']) ?> de <?= h($batch['pages']) ?></span><?php foreach(['Anterior'=>$batch['page']-1,'Próxima'=>$batch['page']+1] as $label=>$number): ?><?php if($number>=1&&$number<=$batch['pages']): ?><?= $this->Html->link($label,['controller'=>'Operacional','action'=>'porcentagem','?'=>['area_id'=>$area,'os'=>$search,'pagina'=>$number]],['data-batch-page'=>true]) ?><?php endif; ?><?php endforeach; ?></nav>
<?= $this->Form->button('SALVAR PORCENTAGENS',['id'=>'porcentagens-salvar','class'=>'btn btn-primary','disabled'=>true]) ?><?= $this->Form->end() ?>
<noscript>Ative o JavaScript para manter alterações entre páginas e salvar o lote.</noscript>
<?php endif; ?></section>
<?= $this->Html->script('porcentagens-lote.js?v='.filemtime(WWW_ROOT.'js/porcentagens-lote.js'),['block'=>true]) ?>
