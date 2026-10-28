<?php
$this->assign('title','Vincular O.S.');
$labels=['elegivel'=>'Disponível para vínculo','mesmo'=>'Já vinculada a este caderno','outro'=>'Já vinculada a outro caderno','nao_encontrada'=>'Não encontrada no TOTVS','inelegivel'=>'O.S. inelegível','ambigua'=>'Identificação pendente','erro'=>'Erro na revalidação','sucesso'=>'Vinculada com sucesso'];
?>
<div class="page-heading"><div><p class="eyebrow">ENTRESSAFRA <?= h($book->entressafra->nome) ?></p><h1>Vincular O.S.</h1><p>Caderno: <strong><?= h($book->nome) ?></strong> · Setor da Curva S: <?= h($areaCurva) ?></p></div><?= $this->Html->link('Voltar ao caderno',['controller'=>'Cadernos','action'=>'view',$book->id],['class'=>'btn btn-light']) ?></div>
<?php if(!$data): ?>
<section class="panel form-panel">
<?= $this->Form->create(null,['id'=>'lista-numeros']) ?><?= $this->Form->hidden('acao',['value'=>'criar']) ?>
<?= $this->Form->control('numeros',['label'=>'Cole os números das O.S.','type'=>'textarea','rows'=>10,'required'=>true,'maxlength'=>50000,'value'=>$this->request->getData('numeros'),'class'=>'form-control']) ?>
<p class="small text-secondary">Um número por linha. Também aceita vírgula, ponto e vírgula ou espaço. Zeros à esquerda são preservados e repetições não geram vínculos duplicados. Até 2.000 O.S. únicas.</p><p id="lista-contagem" aria-live="polite"></p>
<?= $this->Form->button('Conferir',['class'=>'btn btn-primary']) ?><?= $this->Form->end() ?>
</section>
<?php if($recent): ?><details class="panel mt-3"><summary>Retomar prévias deste caderno</summary><?php foreach($recent as $saved): ?><p><?= $this->Html->link('Retomar prévia de '.$saved['quantidade'].' O.S. — '.$saved['estado'].' — '.$saved['created'],['action'=>'lista',$book->id,'?'=>['token'=>$saved['token']]],['class'=>'btn btn-outline-primary']) ?></p><?php endforeach; ?></details><?php endif; ?>
<?php else:
$job=$data['job'];$counts=$data['counts'];$ready=$job->estado==='pronto';$canChoose=in_array($job->estado,['pronto','concluido'],true);
$expected=in_array($job->estado,['preparando','pronto'],true)?$data['filters']['unicas']:($counts['selecionadas']??0);
$done=$job->estado==='preparando'?(int)$job->cursor_os:($ready?$data['total']:($counts['processadas']??0));
?>
<section class="panel mb-3" aria-live="polite">
<h2><?= $ready?'Prévia completa':($job->estado==='preparando'?'Conferindo O.S.':'Resultado da vinculação') ?></h2>
<p><strong><?= h($done) ?>/<?= h($expected) ?></strong></p><progress value="<?= h($done) ?>" max="<?= h(max(1,$expected)) ?>" style="width:100%"></progress>
<p>Informadas: <?= h($data['filters']['informadas']) ?> · Únicas: <?= h($data['filters']['unicas']) ?> · Repetidas ignoradas: <?= h($data['filters']['repetidas']) ?></p>
<p>Selecionadas para transferência: <strong data-transfer-total><?= h($counts['transferencias_selecionadas']??0) ?></strong></p>
<p>Encontradas: <?= h($data['total']-($counts['nao_encontrada']??0)) ?> · Disponíveis: <?= h($counts['elegivel']??0) ?> · Já neste caderno: <?= h($counts['mesmo']??0) ?> · Em outro caderno: <?= h($counts['outro']??0) ?> · Não encontradas: <?= h($counts['nao_encontrada']??0) ?> · Inelegíveis: <?= h($counts['inelegivel']??0) ?> · Identificação pendente: <?= h($counts['ambigua']??0) ?><?php if($job->estado==='concluido'): ?> · Vinculadas: <?= h($counts['sucesso']??0) ?> · Erros: <?= h($counts['erro']??0) ?><?php endif; ?></p>
<?php if($canChoose): ?>
<?= $this->Form->create(null) ?><?= $this->Form->hidden('acao',['value'=>'confirmar']) ?>
<p>A confirmação vinculará todas as <?= h($counts['elegivel']??0) ?> O.S. disponíveis, incluindo as outras páginas. Transferências são confirmadas separadamente na seção abaixo.</p>
<?= $this->Form->button('Confirmar vínculos',['class'=>'btn btn-primary','disabled'=>empty($counts['elegivel'])]) ?><?= $this->Form->end() ?>
<?php endif; ?>
<?php if(in_array($job->estado,['preparando','processando'],true)): ?>
<?= $this->Form->create(null,['data-auto-process'=>$paused?'0':'1']) ?><?= $this->Form->hidden('acao',['value'=>$job->estado==='preparando'?'preparar':'processar']) ?>
<p><?= $paused?'Processamento pausado. Seus números e o progresso estão salvos.':'Processamento automático em blocos. Aguarde a conclusão.' ?></p>
<?= $this->Form->button('Continuar processamento',['class'=>'btn btn-outline-primary']) ?><?= $this->Form->end() ?>
<?php endif; ?>
</section>
<?php if(($counts['outro']??0)>0&&$canChoose): ?><?= $this->element('transferencias_lista',compact('book','data','token')) ?><?php endif; ?>
<?php $pending=array_filter($data['rows']->toList(),fn($item)=>in_array($item->estado,['nao_encontrada','inelegivel','ambigua','erro'],true)); ?>
<?php if($pending): ?><section class="panel mb-3"><h2>Pendências nesta página</h2><?php foreach($pending as $item): ?><p><strong><?= h($item->numero) ?></strong> — <?= h($labels[$item->estado]) ?>. <?= h(\App\Service\OsApresentacao::texto((string)$item->motivo)) ?></p><?php endforeach; ?></section><?php endif; ?>
<details class="panel" <?= $data['page']>1?'open':'' ?>><summary>Consultar O.S. encontradas e resultados — 10 por página</summary><div class="table-responsive"><table class="table"><thead><tr><th>O.S.</th><th>Descrição</th><th>Setor / equipe executante</th><th>Resultado</th></tr></thead><tbody>
<?php foreach($data['rows'] as $item): $snapshot=json_decode($item->snapshot,true);$os=$snapshot['os'];$classification=$snapshot['classificacao'];$local=$item->atividade_id?\App\Service\Access::table('Atividades')->get($item->atividade_id):null;$presentation=$local?\App\Service\OsApresentacao::activity($local,$os??[]):\App\Service\OsApresentacao::source($os??[],$classification['equipe']??null); ?>
<tr><td><?= h($item->numero) ?></td><td><?= h($presentation['descricao']) ?></td><td><?= h($presentation['rotulo_setor']) ?>: <?= h($presentation['setor_apresentacao']) ?></td><td><?= h($labels[$item->estado]) ?><?php if($item->caderno_atual): ?><small class="d-block">Caderno atual: <?= h($classification['caderno_nome']??$item->caderno_atual) ?></small><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div></details>
<?= $this->element('simple_pagination',['page'=>$data['page'],'pages'=>$data['pages']]) ?>
<details class="panel mt-3"><summary>Números informados (lista preservada)</summary><textarea class="form-control" rows="8" readonly><?= h($data['filters']['texto_original']??implode("\n",$data['filters']['numbers'])) ?></textarea></details>
<?php if($job->estado==='concluido'): ?><p class="alert alert-warning mt-3"><?= h($resultadoVinculos??'Processamento encerrado. Confira os resultados e pendências.') ?></p><div class="d-flex gap-2 mt-3"><?= $this->Html->link('Voltar à listagem de Cadernos',$listTarget,['class'=>'btn btn-primary']) ?><?= $this->Html->link('Conferir outra lista',['action'=>'lista',$book->id],['class'=>'btn btn-outline-secondary']) ?></div><?php endif; ?>
<?php endif; ?>
<?= $this->Html->script('lista-os.js?v='.filemtime(WWW_ROOT.'js/lista-os.js'),['block'=>true]) ?>
