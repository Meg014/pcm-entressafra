<?php $this->assign('title','Serviços sugeridos'); ?>
<h1>Serviços sugeridos</h1>
<?= $this->Html->link('Adicionar descrição',['action'=>'add'],['class'=>'btn btn-primary mb-3']) ?>
<?= $this->Form->create(null,['type'=>'get']) ?>
<?= $this->Form->control('q',['label'=>'Pesquisar descrição','value'=>$search,'maxlength'=>100,'class'=>'form-control']) ?>
<?= $this->Form->button('Pesquisar',['class'=>'btn btn-secondary my-2']) ?><?= $this->Form->end() ?>
<div class="table-responsive"><table class="table"><thead><tr><th>Descrição</th><th>Situação</th><th>Ações</th></tr></thead><tbody>
<?php foreach($rows as $row): ?><tr><td><?= h($row->descricao) ?></td><td><?= $row->ativo?'Ativa':'Desativada' ?></td><td>
<?= $this->Html->link('Editar',['action'=>'edit',$row->id],['class'=>'btn btn-sm btn-outline-primary']) ?>
<?= $this->Form->postLink($row->ativo?'Desativar':'Reativar',['action'=>'toggle',$row->id,'_method'=>'POST'],['data'=>['ativo'=>$row->ativo?'0':'1'],'class'=>'btn btn-sm btn-outline-secondary']) ?>
</td></tr><?php endforeach; ?>
</tbody></table></div>
<?= $this->Paginator->prev('Anterior') ?> <?= $this->Paginator->numbers() ?> <?= $this->Paginator->next('Próxima') ?>
