<?php $this->assign('title', \App\Service\OsApresentacao::texto($registro->nome)); ?>
<div class="page-heading"><div><p class="eyebrow"><?= h(mb_strtoupper($singular)) ?></p><h1><?= h(\App\Service\OsApresentacao::texto($registro->nome)) ?></h1></div><div class="d-flex gap-2"><?= $this->Html->link('Voltar', ['action' => 'index'], ['class' => 'btn btn-light']) ?><?= $this->Html->link('Editar cadastro', ['action' => 'edit', $registro->id], ['class' => 'btn btn-primary']) ?></div></div>
<section class="panel mt-4 form-panel"><dl class="row details">
<?php if($cadastro==='Cadernos'): ?><dt class="col-sm-4">Setor principal da Curva S</dt><dd class="col-sm-8"><?= h($areaCurvaNome??'Pendente de definição administrativa') ?></dd><?php endif; ?>
<?php if ($cadastro === 'Entressafras'): ?>
    <dt class="col-sm-4">Status</dt><dd class="col-sm-8"><?= h(\App\Model\Table\EntressafrasTable::STATUS[$registro->status]) ?></dd>
    <dt class="col-sm-4">Período</dt><dd class="col-sm-8"><?= h($registro->data_inicio->format('d/m/Y')) ?> a <?= h($registro->data_fim->format('d/m/Y')) ?></dd>
    <dt class="col-sm-4">Observações</dt><dd class="col-sm-8 text-break preserve-lines"><?= h($registro->observacoes ?: 'Não informado') ?></dd>
<?php else: ?>
    <?php if ($cadastro === 'Cadernos'): ?>
        <dt class="col-sm-4">Entressafra</dt><dd class="col-sm-8"><?= $this->Html->link($registro->entressafra->nome, ['controller' => 'Entressafras', 'action' => 'view', $registro->entressafra_id]) ?></dd>
        <dt class="col-sm-4">Equipes identificadas pelo TOTVS</dt><dd class="col-sm-8"><?php foreach ($registro->setores as $sector): ?><span class="d-inline-block me-3"><?= $this->Html->link(\App\Service\OsApresentacao::equipe($sector->nome), ['controller' => 'Setores', 'action' => 'view', $sector->id]) ?></span><?php endforeach; ?></dd>
        <dt class="col-sm-4">Área física de referência</dt><dd class="col-sm-8"><?= h($registro->area_fisica?:'Não informada') ?></dd>
    <?php endif; ?>
    <dt class="col-sm-4">Situação</dt><dd class="col-sm-8"><?= $registro->ativo ? 'Ativo' : 'Inativo' ?></dd>
    <dt class="col-sm-4">Descrição</dt><dd class="col-sm-8 text-break preserve-lines"><?= h($registro->descricao ?: 'Não informado') ?></dd>
<?php endif; ?>
<dt class="col-sm-4">Criado em</dt><dd class="col-sm-8"><?= h($registro->created->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i')) ?></dd>
<dt class="col-sm-4">Atualizado em</dt><dd class="col-sm-8"><?= h($registro->modified->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i')) ?></dd>
</dl>
<?php if($cadastro==='Cadernos'&&in_array($currentUser->perfil,['PCM','ADMIN_PCM'],true)): ?><div class="d-flex flex-wrap gap-2 mt-3">
<?= $this->Html->link('Vincular O.S.',['controller'=>'Organizacao','action'=>'lista',$registro->id],['class'=>'btn btn-primary']) ?>
</div><?php endif; ?>
<?php if ($totalCadernos !== null): ?>
<div class="border-top pt-4"><?= $this->Html->link('Ver cadernos vinculados (' . $totalCadernos . ')', ['controller' => 'Cadernos', 'action' => 'index', '?' => [$cadastro === 'Entressafras' ? 'entressafra_id' : 'setor_id' => $registro->id]], ['class' => 'btn btn-outline-primary']) ?></div>
<?php endif; ?>
</section>
