<?php $this->assign('title','Insumos registrados'); $admin=$currentUser->perfil==='ADMIN_PCM'; ?>
<div class="page-heading mb-3"><h1>Insumos registrados</h1><?= $this->Html->link('Lançar insumos',['action'=>'add'],['class'=>'btn btn-primary']) ?></div>
<section class="panel">
<?= $this->Form->create(null,['type'=>'get','class'=>'row g-2 align-items-end mb-3']) ?>
<div class="col-sm-6 col-lg-2"><?= $this->Form->control('data_inicial',['label'=>'Data inicial','type'=>'date','value'=>$filters['data_inicial'],'class'=>'form-control']) ?></div>
<div class="col-sm-6 col-lg-2"><?= $this->Form->control('data_final',['label'=>'Data final','type'=>'date','value'=>$filters['data_final'],'class'=>'form-control']) ?></div>
<div class="col-sm-4 col-lg-2"><?= $this->Form->control('os',['label'=>'Número da O.S.','type'=>'text','inputmode'=>'numeric','maxlength'=>60,'value'=>$filters['os'],'class'=>'form-control']) ?></div>
<div class="col-sm-8 col-lg-3"><?= $this->Form->control('material',['label'=>'Material','type'=>'text','maxlength'=>500,'value'=>$filters['material'],'class'=>'form-control']) ?></div>
<div class="col-lg-3 d-flex gap-2"><?= $this->Form->button('Filtrar',['class'=>'btn btn-primary']) ?><?= $this->Html->link('Limpar filtros',['action'=>'index'],['class'=>'btn btn-light']) ?></div>
<?= $this->Form->end() ?>
<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>O.S.</th><th>Funcionário</th><th>Data</th><th>Material</th><th>Quantidade</th><th>Unidade</th><th>Registrado em</th><th>Situação</th><th>Ações</th></tr></thead><tbody>
<?php foreach($rows as $row):
    $employeeLabel = $row->funcionario_nome;
    if ((json_decode((string)$row->snapshot, true)['funcionario']['situacao_codigo'] ?? '') === 'F') $employeeLabel .= ' · Férias (TOTVS)';
?><tr><td><?= h($row->atividade->os_numero) ?></td><td><?= h($employeeLabel) ?><small class="d-block text-secondary"><?= h($row->funcionario_matricula) ?></small></td><td><?= h($row->data->format('d/m/Y')) ?></td><td><?= h($row->material) ?></td><td><?= h(str_replace('.',',',(string)$row->quantidade)) ?></td><td><?= h($row->unidade ?: 'Não informada') ?></td><td><?= h($row->created->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i')) ?></td><td><?= $row->cancelado?'Cancelado':($row->controle_pcm==='lancado'?'Lançado manualmente no TOTVS':'Pendente') ?></td><td>
<?php if($admin && !$row->cancelado): ?>
<button type="button" class="btn btn-outline-primary btn-sm" data-edit-supply data-url="<?= h($this->Url->build(['action'=>'edit',$row->id,'_method'=>'POST','?'=>$this->request->getQueryParams()])) ?>" data-os="<?= h($row->atividade->os_numero) ?>" data-employee="<?= h($employeeLabel.' · '.$row->funcionario_matricula) ?>" data-date="<?= h($row->data->format('d/m/Y')) ?>" data-material="<?= h($row->material) ?>" data-quantity="<?= h($row->quantidade) ?>" data-unit="<?= h($row->unidade) ?>" data-revision="<?= h($row->revisao_pcm) ?>" data-confirmed="<?= $row->controle_pcm==='lancado'?'1':'0' ?>">Editar</button>
<?php endif; ?></td></tr><?php endforeach; ?>
<?php if(!$rows->count()): ?><tr><td colspan="9">Nenhum insumo registrado.</td></tr><?php endif; ?>
</tbody></table></div><ul class="pagination"><?= $this->Paginator->prev('Anterior') ?><?= $this->Paginator->numbers() ?><?= $this->Paginator->next('Próxima') ?></ul></section>
<?php if($admin): ?>
<dialog id="insumo-edicao" class="rounded border-0 shadow p-3" style="width:min(520px,95vw)">
<h2 class="h5">Editar insumo</h2>
<?= $this->Form->create(null,['id'=>'insumo-edicao-form']) ?>
<div class="mb-2"><strong>O.S.:</strong> <span data-edit-os></span><br><strong>Funcionário:</strong> <span data-edit-employee></span><br><strong>Data do insumo:</strong> <span data-edit-date></span></div>
<?= $this->Form->hidden('revisao_pcm',['id'=>'edit-revision']) ?>
<?= $this->Form->control('material',['label'=>'Material','type'=>'text','id'=>'edit-material','maxlength'=>500,'required'=>true,'class'=>'form-control mb-2']) ?>
<div class="row g-2"><div class="col-6"><?= $this->Form->control('quantidade',['label'=>'Quantidade','type'=>'text','id'=>'edit-quantity','inputmode'=>'decimal','maxlength'=>19,'required'=>true,'class'=>'form-control']) ?></div><div class="col-6"><?= $this->Form->control('unidade',['label'=>'Unidade','type'=>'select','id'=>'edit-unit','options'=>\App\Service\Insumos::UNITS,'empty'=>'Selecione','required'=>true,'class'=>'form-select']) ?></div></div>
<?= $this->Form->control('motivo',['label'=>'Justificativa','type'=>'textarea','id'=>'edit-reason','rows'=>2,'maxlength'=>3000,'required'=>true,'placeholder'=>'Ex.: Complementação da unidade de medida','class'=>'form-control mb-2']) ?>
<div id="edit-confirmed" class="alert alert-warning py-2" hidden><p class="small mb-2">Este insumo já foi confirmado no TOTVS. A edição local o devolverá à fila PCM para nova conferência. O TOTVS não será alterado.</p><?= $this->Form->control('ciencia_totvs',['label'=>'Estou ciente e confirmo a devolução para conferência PCM.','type'=>'checkbox','id'=>'edit-awareness','value'=>'1']) ?></div>
<div class="d-flex gap-2 justify-content-end"><button type="button" id="edit-cancel" class="btn btn-light">Cancelar</button><?= $this->Form->button('Salvar',['class'=>'btn btn-primary']) ?></div>
<?= $this->Form->end() ?></dialog>
<?= $this->Html->script('insumos-edicao.js?v='.filemtime(WWW_ROOT.'js/insumos-edicao.js'),['block'=>true]) ?>
<?php endif; ?>
