<?php if ($cancelEntry && $currentUser->perfil==='ADMIN_PCM'): ?>
<section class="panel mb-4" id="cancelamento">
<h2>Excluir apontamento #<?= h($cancelEntry->id) ?></h2>
<p><strong>O.S.:</strong> <?= h($activity->os_numero) ?> · <strong>Funcionário:</strong> <?= h($cancelEntry->funcionario_nome) ?> (<?= h($cancelEntry->funcionario_matricula) ?>)</p>
<p><strong>Data do serviço:</strong> <?= h($cancelEntry->inicio->setTimezone('America/Sao_Paulo')->format('d/m/Y')) ?></p>
<?= $this->element('entry_periods',['entry'=>$cancelEntry]) ?>
<p class="text-break"><strong>Descrição do serviço:</strong> <?= h($cancelEntry->descricao) ?></p>
<?php if ($cancelEntry->cancelado): ?><p>Apontamento já cancelado.</p>
<?php elseif ($cancelEntry->controle_pcm==='lancado'): ?><p>Cancelamento bloqueado: lançamento manual no TOTVS confirmado. É necessária conferência e reabertura justificada da confirmação antes do cancelamento. Esta operação não desfaz lançamentos no TOTVS.</p>
<?php else: ?>
<p>O cancelamento preserva o histórico e retira estas horas dos totais operacionais. Nenhuma alteração será realizada no TOTVS.</p>
<?= $this->Form->create(null) ?>
<?= $this->Form->hidden('acao',['value'=>'cancelar']) ?>
<?= $this->Form->hidden('apontamento_id',['value'=>$cancelEntry->id]) ?>
<?= $this->Form->hidden('revisao',['value'=>$cancelEntry->revisao_pcm]) ?>
<?= $this->Form->control('motivo',['type'=>'textarea','label'=>'Justificativa da exclusão','required'=>true,'maxlength'=>3000,'class'=>'form-control']) ?>
<?= $this->Form->control('confirmar',['type'=>'checkbox','value'=>'1','label'=>'Confirmo o cancelamento deste apontamento.','required'=>true]) ?>
<?= $this->Form->button('Confirmar exclusão',['class'=>'btn btn-danger btn-sm mt-2']) ?>
<?= $this->Form->end() ?>
<?php endif; ?>
</section>
<?php endif; ?>
