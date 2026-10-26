<?php $this->assign('title','Insumos registrados'); ?>
<div class="page-heading mb-3"><h1>Insumos registrados</h1><?= $this->Html->link('Lançar insumos',['action'=>'add'],['class'=>'btn btn-primary']) ?></div>
<section class="panel"><div class="table-responsive"><table class="table"><thead><tr><th>O.S.</th><th>Funcionário</th><th>Data</th><th>Material</th><th>Quantidade</th><th>Registrado em</th></tr></thead><tbody>
<?php foreach($rows as $row): ?><tr><td><?= h($row->atividade->os_numero) ?></td><td><?= h($row->funcionario_nome) ?><small class="d-block text-secondary"><?= h($row->funcionario_matricula) ?></small></td><td><?= h($row->data->format('d/m/Y')) ?></td><td><?= h($row->material) ?></td><td><?= h(str_replace('.',',',(string)$row->quantidade)) ?></td><td><?= h($row->created->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i')) ?></td></tr><?php endforeach; ?>
<?php if(!$rows->count()): ?><tr><td colspan="6">Nenhum insumo registrado.</td></tr><?php endif; ?>
</tbody></table></div><ul class="pagination"><?= $this->Paginator->prev('Anterior') ?><?= $this->Paginator->numbers() ?><?= $this->Paginator->next('Próxima') ?></ul></section>
