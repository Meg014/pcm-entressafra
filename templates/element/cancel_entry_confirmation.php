<?php if ($currentUser->perfil==='ADMIN_PCM'): ?>
<dialog id="cancel-entry-dialog" aria-labelledby="cancel-entry-title" style="width:min(440px,calc(100% - 24px));border:1px solid #ddd;border-radius:14px;padding:22px;max-height:90vh;overflow:auto">
<h2 id="cancel-entry-title" class="h5">Excluir apontamento</h2>
<p><strong>O.S. <?= h($activity->os_numero) ?></strong></p>
<div id="cancel-entry-details" class="small mb-3"></div>
<?= $this->Form->create(null,['id'=>'cancel-entry-form']) ?>
<?= $this->Form->hidden('acao',['value'=>'cancelar']) ?>
<?= $this->Form->hidden('confirmar',['value'=>'1']) ?>
<?= $this->Form->hidden('apontamento_id',['value'=>'','id'=>'cancel-entry-id']) ?>
<?= $this->Form->hidden('revisao',['value'=>'','id'=>'cancel-entry-revision']) ?>
<div id="cancel-entry-warning" class="alert alert-warning small" hidden>Este apontamento está marcado como lançado manualmente no TOTVS. A exclusão neste sistema não remove o lançamento realizado no TOTVS.</div>
<?= $this->Form->control('motivo',['type'=>'textarea','label'=>'Motivo da exclusão','required'=>true,'maxlength'=>3000,'rows'=>3,'class'=>'form-control','id'=>'cancel-entry-reason']) ?>
<label id="cancel-entry-ack" class="small my-3" hidden><input type="checkbox" name="ciencia_totvs" value="1"> Estou ciente de que o TOTVS não será alterado.</label>
<p id="cancel-entry-error" class="text-danger small" role="alert"></p>
<div class="d-flex justify-content-end gap-2 mt-3"><button type="button" class="btn btn-light" data-cancel-close>Cancelar</button><button type="submit" class="btn btn-danger">Confirmar exclusão</button></div>
<?= $this->Form->end() ?>
</dialog>
<?php endif; ?>
