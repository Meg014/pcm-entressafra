<?php if ($bulkCancellation && $currentUser->perfil==='ADMIN_PCM'):
    extract($bulkCancellation,EXTR_SKIP);
    $returnQuery=array_intersect_key($this->request->getQueryParams(),array_flip(['os_numero','setor','evolucao','pagina','entressafra_id']));
?>
<?= $this->Html->css('cancel-os',['block'=>true]) ?>
<dialog open id="cancel-os-dialog" class="panel" aria-labelledby="cancel-os-title">
<h2 id="cancel-os-title">Excluir registros da O.S. <?= h($activity->os_numero) ?></h2>
<p class="text-break"><strong>Descrição da O.S.:</strong> <?= h($description) ?></p>
<p>Você está prestes a cancelar <strong><?= h($preview['apontamentos']) ?> apontamentos</strong> e <strong><?= h($preview['insumos']) ?> insumos</strong> da O.S. <?= h($activity->os_numero) ?>. Essa ação retirará os registros das pendências do PCM.</p>
<?php if ($preview['bloqueado']): ?>
<p class="alert alert-warning">Existem registros já confirmados no TOTVS. O lote inteiro está bloqueado e exige tratamento individual específico de conferência. Nenhum registro será cancelado.</p>
<?php elseif ($preview['apontamentos']+$preview['insumos']===0): ?>
<p>Não há registros ativos para cancelar nesta O.S.</p>
<?php else: ?>
<p class="small text-secondary">O histórico será preservado. A ordem de serviço, sua evolução e o TOTVS permanecerão intactos.</p>
<?= $this->Form->create(null,['url'=>['action'=>'cancelRecords',$activity->id,'_method'=>'POST','?'=>$returnQuery],'id'=>'cancel-os-form']) ?>
<?= $this->Form->hidden('confirmacao',['value'=>$token]) ?>
<?= $this->Form->control('motivo',['type'=>'textarea','label'=>'Justificativa da exclusão','required'=>true,'maxlength'=>3000,'class'=>'form-control','rows'=>3]) ?>
<div class="d-flex flex-wrap gap-2 mt-3">
<?= $this->Html->link('Cancelar',['action'=>'index','?'=>$returnQuery],['class'=>'btn btn-sm btn-outline-secondary','data-cancel-os-return'=>true]) ?>
<?= $this->Form->button('Confirmar exclusão',['name'=>'confirmar','value'=>'1','class'=>'btn btn-sm btn-danger']) ?>
</div>
<?= $this->Form->end() ?>
<?php endif; ?>
<?php if ($preview['bloqueado'] || $preview['apontamentos']+$preview['insumos']===0): ?>
<?= $this->Html->link('Cancelar',['action'=>'index','?'=>$returnQuery],['class'=>'btn btn-sm btn-outline-secondary','data-cancel-os-return'=>true]) ?>
<?php endif; ?>
</dialog>
<?= $this->Html->script('cancel-os',['block'=>true]) ?>
<?php endif; ?>
