<?php
$this->assign('title','Lançar evolução semanal das O.S.');
$number=static fn($v)=>$v===null?'':rtrim(rtrim(number_format((float)$v,2,'.',''),'0'),'.');
$period=static fn($d)=>(new DateTimeImmutable($d))->format('d/m/y');
$weeks=$batch['weeks'];
?>
<?= $this->Html->css('porcentagens-lote.css?v='.filemtime(WWW_ROOT.'css/porcentagens-lote.css'),['block'=>true]) ?>
<div class="page-heading"><h1>Lançar evolução das O.S.</h1></div>
<section class="panel" id="porcentagens-lote" data-storage="pcm-grade-v2-u<?= h($currentUser->id) ?>-p<?= h($season) ?>-a<?= h($area) ?>">
<?= $this->Form->create(null,['type'=>'get','id'=>'porcentagens-filtros','class'=>'row g-3 mb-3']) ?>
<div class="col-md-5"><?= $this->Form->control('os',['label'=>'Pesquisar O.S.','value'=>$search,'placeholder'=>'Número da O.S.','maxlength'=>6,'class'=>'form-control','inputmode'=>'numeric']) ?></div>
<div class="col-md-5"><?= $this->Form->control('area_id',['label'=>'Setor da Curva S','options'=>$batch['areas'],'empty'=>'Selecione o setor','value'=>$area,'class'=>'form-select']) ?></div>
<div class="col-md-2 d-flex align-items-end"><?= $this->Form->button('Pesquisar',['class'=>'btn btn-outline-primary']) ?></div>
<?= $this->Form->end() ?>
<?php if($area||$search!==''): ?>
<div class="weekly-sheet-caption"><span><?= h($batch['total']) ?> O.S. · Percentuais acumulados por semana</span><span id="porcentagens-pendentes" role="status">Nenhuma alteração pendente.</span></div>
<p class="small text-secondary">Vazio: sem lançamento. O cálculo mantém o último acumulado anterior. Semanas de terça a segunda, com análise na terça.</p>
<p id="porcentagens-erro" role="alert"></p>
<?= $this->Form->create(null,['id'=>'porcentagens-form','data-key'=>$key,'data-pending'=>json_encode($pending,JSON_THROW_ON_ERROR)]) ?>
<?= $this->Form->hidden('chave',['id'=>'porcentagens-chave','value'=>$key]) ?><?= $this->Form->hidden('alteracoes_json',['id'=>'porcentagens-json','value'=>'[]']) ?>
<div class="weekly-sheet-scroll" tabindex="0" role="region" aria-label="Planilha de evolução semanal; use rolagem horizontal para acessar as semanas">
<table class="table table-sm align-middle weekly-sheet"><thead><tr><th class="fixed-os">O.S.</th><th class="fixed-description">Descrição da atividade</th><th class="fixed-equipment">Equipamento</th><th>Mais recente</th>
<?php foreach($weeks as $w): $current=$w['inicio']<=$batch['today']&&$w['fim']>=$batch['today']; ?><th class="<?= $current?'week-current':'' ?>" data-week-header="<?= h($w['fim']) ?>"><strong><?= h($w['semana']) ?>ª semana</strong><small><?= h($period($w['inicio']).' a '.$period($w['fim'])) ?></small><small>Análise <?= h($period($w['referencia'])) ?></small><?= $current?'<span class="current-week-label">Atual</span>':'' ?></th><?php endforeach; ?>
</tr></thead><tbody>
<?php foreach($batch['rows'] as $row): ?><tr data-order-row="<?= h($row['id']) ?>"><td class="fixed-os"><?= $this->Html->link($row['numero'],['controller'=>'Evolucao','action'=>'historico',$row['id']],['title'=>'Consultar histórico e revisões da O.S.']) ?></td><td class="fixed-description"><span title="<?= h($row['descricao']) ?>"><?= h($row['descricao']) ?></span></td><td class="fixed-equipment"><?= h($row['bem']) ?></td><td class="latest-percentage"><?= h($number($row['percentual'])) ?>%</td>
<?php foreach($weeks as $w): $cell=$row['cells'][$w['fim']];$current=$w['inicio']<=$batch['today']&&$w['fim']>=$batch['today'];$cellKey=$row['id'].':'.$w['fim']; ?>
<td class="weekly-cell <?= $current?'week-current':'' ?>" data-cell="<?= h($cellKey) ?>">
<input type="number" class="form-control" min="0" max="100" step="0.01" value="<?= h($number($cell['valor'])) ?>" placeholder="—" aria-label="O.S. <?= h($row['numero']) ?> · <?= h($w['semana']) ?>ª semana · <?= h($period($w['inicio']).' a '.$period($w['fim'])) ?>" title="<?= $cell['valor']===null?'Sem lançamento; acumulado anterior considerado: '.h($number($cell['acumulado'])).'%':'Último valor salvo: '.h($number($cell['valor'])).'%' ?>" data-percentage data-id="<?= h($row['id']) ?>" data-os="<?= h($row['numero']) ?>" data-week="<?= h($w['semana']) ?>" data-reference="<?= h($w['fim']) ?>" data-base="<?= h($number($cell['valor'])) ?>" data-carry="<?= h($cell['acumulado']) ?>" data-revision="<?= h($cell['revisao']) ?>" data-record="<?= h($cell['registro_id']) ?>">
<details class="cell-exception" hidden><summary>Correção excepcional</summary><label><input type="checkbox" data-confirm-reduction> Confirmar correção do acumulado</label><textarea data-correction-reason class="form-control" rows="2" maxlength="3000" aria-label="Justificativa da O.S. <?= h($row['numero']) ?>, semana <?= h($w['semana']) ?>" placeholder="Justificativa"></textarea></details>
</td><?php endforeach; ?></tr><?php endforeach; ?>
<?php if(!$batch['rows']): ?><tr><td colspan="<?= 4+count($weeks) ?>">Nenhuma O.S. neste resultado.</td></tr><?php endif; ?>
</tbody></table></div>
<?php if(!$weeks): ?><p class="small text-secondary">A entressafra ainda não possui semanas iniciadas.</p><?php endif; ?>
<nav class="d-flex gap-3 my-3" aria-label="Paginação de porcentagens"><span>Página <?= h($batch['page']) ?> de <?= h($batch['pages']) ?></span><?php foreach(['Anterior'=>$batch['page']-1,'Próxima'=>$batch['page']+1] as $label=>$n): if($n>=1&&$n<=$batch['pages']): ?><?= $this->Html->link($label,['controller'=>'Operacional','action'=>'porcentagem','?'=>['area_id'=>$area,'os'=>$search,'pagina'=>$n]],['data-batch-page'=>true]) ?><?php endif; endforeach; ?></nav>
<?= $this->Form->button('Salvar porcentagens',['id'=>'porcentagens-salvar','class'=>'btn btn-primary','disabled'=>true]) ?><?= $this->Form->end() ?>
<noscript>Ative o JavaScript para editar e salvar a planilha semanal.</noscript>
<?php endif; ?></section>
<?= $this->Html->script('porcentagens-lote.js?v='.filemtime(WWW_ROOT.'js/porcentagens-lote.js'),['block'=>true]) ?>
