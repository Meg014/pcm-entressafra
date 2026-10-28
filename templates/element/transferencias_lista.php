<section class="panel mb-3" id="transferencias-lista">
<h2>O.S. vinculadas a outros cadernos</h2>
<div class="alert alert-warning"><strong>Atenção! Foram encontradas <?= h($data['counts']['outro']) ?> O.S. vinculadas a outros cadernos.</strong><br>Selecione as ordens que deseja transferir. Nenhuma O.S. será movimentada sem confirmação.</div>
<p>Destino: <strong><?= h($book->nome) ?></strong>. Apontamentos, percentuais e classificações individuais da Curva S serão preservados.</p>
<p class="small text-secondary">A equipe executante da O.S. permanece a mesma; quando necessário, será associada ao caderno de destino.</p>
<?= $this->Form->create(null,['id'=>'transferencias-selecao','data-selected-total'=>$data['counts']['transferencias_selecionadas']??0]) ?><?= $this->Form->hidden('acao',['value'=>'revisar_transferencias']) ?>
<div class="table-responsive"><table class="table"><thead><tr><th>Selecionar</th><th>O.S.</th><th>Caderno atual</th><th>Caderno de destino</th><th>Situação</th></tr></thead><tbody>
<?php foreach($data['transferRows'] as $item): $snapshot=json_decode($item->snapshot,true); ?>
<tr><td><input type="hidden" name="visiveis[]" value="<?= h($item->id) ?>"><input type="checkbox" name="transferencias[]" value="<?= h($item->id) ?>" <?= $item->selecionado?'checked':'' ?> aria-label="Transferir O.S. <?= h($item->numero) ?>"></td><td><?= h($item->numero) ?></td><td><?= h($snapshot['classificacao']['caderno_nome']??$item->caderno_atual) ?></td><td><?= h($book->nome) ?></td><td>Vinculada · aguardando confirmação</td></tr>
<?php endforeach; ?></tbody></table></div>
<?= $this->Form->button('Transferir selecionadas',['class'=>'btn btn-primary','id'=>'transferencias-revisar']) ?><?= $this->Form->end() ?>
<div class="d-flex flex-wrap gap-2 mt-3">
<?= $this->Form->create(null) ?><?= $this->Form->hidden('acao',['value'=>'todas_transferencias']) ?><?= $this->Form->button('Selecionar todas',['class'=>'btn btn-outline-primary']) ?><?= $this->Form->end() ?>
<?= $this->Form->create(null) ?><?= $this->Form->hidden('acao',['value'=>'manter_origem']) ?><?= $this->Form->button('Manter no caderno original',['class'=>'btn btn-light']) ?><?= $this->Form->end() ?>
</div>
<nav class="d-flex flex-wrap gap-3 mt-3" aria-label="Paginação das transferências"><span>Página <?= h($data['transferPage']) ?> de <?= h($data['transferPages']) ?> · 50 por página</span>
<?php foreach(['Anterior'=>$data['transferPage']-1,'Próxima'=>$data['transferPage']+1] as $label=>$number): if($number>=1&&$number<=$data['transferPages']): ?><?= $this->Html->link($label,['action'=>'lista',$book->id,'?'=>['token'=>$token,'pagina'=>$data['page'],'pagina_transferencias'=>$number]],['data-transfer-page'=>true]) ?><?php endif; endforeach; ?></nav>
<?php if($this->request->getQuery('revisar')==='1'&&($data['counts']['transferencias_selecionadas']??0)>0): ?>
<dialog open id="transferencias-confirmacao" class="panel" style="max-width:560px;width:calc(100% - 32px);border:1px solid #c7d5dc;border-radius:12px">
<h2>Confirmar transferência</h2><p>Transferir <strong><?= h($data['counts']['transferencias_selecionadas']) ?> O.S.</strong> selecionadas para o caderno <strong><?= h($book->nome) ?></strong>?</p><p class="small text-secondary">As origens serão revalidadas. Os históricos permanecerão ligados às mesmas O.S.</p>
<?= $this->Form->create(null,['id'=>'transferencias-confirmar']) ?><?= $this->Form->hidden('acao',['value'=>'confirmar_transferencias']) ?><?= $this->Form->hidden('quantidade_transferencias',['value'=>$data['counts']['transferencias_selecionadas']]) ?><?= $this->Form->hidden('assinatura',['value'=>$data['selectionSignature']]) ?>
<?= $this->Form->control('justificativa',['label'=>'Justificativa da transferência','required'=>true,'maxlength'=>3000,'class'=>'form-control']) ?>
<div class="d-flex gap-2 mt-3"><?= $this->Form->button('Confirmar transferência',['name'=>'confirmar_transferencia','value'=>'1','class'=>'btn btn-primary']) ?><?= $this->Html->link('Cancelar',['action'=>'lista',$book->id,'?'=>['token'=>$token,'pagina_transferencias'=>$data['transferPage']]],['class'=>'btn btn-light','data-transfer-cancel'=>true]) ?></div><?= $this->Form->end() ?>
</dialog><?php endif; ?>
</section>
