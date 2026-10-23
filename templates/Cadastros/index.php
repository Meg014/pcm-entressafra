<?php $this->assign('title', $cadastro); ?>
<div class="page-heading">
    <div><p class="eyebrow">CADASTROS</p><h1><?= h($cadastro) ?></h1><p class="text-secondary mb-0">Organize a base do planejamento da entressafra.</p></div>
    <?= $this->Html->link('Cadastrar ' . mb_strtolower($singular), ['action' => 'add'], ['class' => 'btn btn-primary']) ?>
</div>
<section class="panel mt-4">
    <?= $this->Form->create(null, ['type' => 'get', 'class' => 'row g-3 align-items-end mb-4']) ?>
    <div class="col-md"><?= $this->Form->control('q', ['label' => 'Pesquisar por nome', 'value' => $search, 'class' => 'form-control', 'maxlength' => 150]) ?></div>
    <?php if ($cadastro === 'Cadernos'): ?>
        <div class="col-md"><?php if(isset($periodoSelecionado)): ?><?= $this->Form->hidden('entressafra_id',['value'=>$periodoSelecionado->id]) ?><?php else: ?><?= $this->Form->control('entressafra_id', ['label' => 'Entressafra', 'options' => $entressafras, 'empty' => 'Todas', 'value' => $this->request->getQuery('entressafra_id'), 'class' => 'form-select']) ?><?php endif; ?></div>
        <div class="col-md"><?= $this->Form->control('area_curva_id', ['label' => 'Setor principal da Curva S', 'options' => $areasPrincipais, 'empty' => 'Todos', 'value' => $this->request->getQuery('area_curva_id'), 'class' => 'form-select']) ?></div>
    <?php endif; ?>
    <div class="col-md-auto d-flex gap-2"><?= $this->Form->button('Filtrar', ['class' => 'btn btn-outline-primary']) ?><?= $this->Html->link('Limpar', ['action' => 'index'], ['class' => 'btn btn-light']) ?></div>
    <?= $this->Form->end() ?>
    <div class="table-responsive"><table class="table align-middle">
        <thead><tr><th><?= $this->Paginator->sort('nome', 'Nome') ?></th>
            <?php if ($cadastro === 'Entressafras'): ?><th>Período</th><th>Status</th>
            <?php elseif ($cadastro === 'Cadernos'): ?><th>Entressafra</th><th>Setor principal da Curva S</th><th>Situação</th>
            <?php else: ?><th>Situação</th><?php endif; ?><th class="text-end">Ações</th></tr></thead>
        <tbody>
        <?php foreach ($registros as $item): ?>
        <tr><td class="fw-semibold"><?= h(\App\Service\OsApresentacao::texto($item->nome)) ?></td>
            <?php if ($cadastro === 'Entressafras'): ?>
                <td><?= h($item->data_inicio->format('d/m/Y')) ?> a <?= h($item->data_fim->format('d/m/Y')) ?></td>
                <td><span class="status status-<?= h($item->status) ?>"><?= h(\App\Model\Table\EntressafrasTable::STATUS[$item->status]) ?></span></td>
            <?php else: ?>
                <?php if ($cadastro === 'Cadernos'): ?><td><?= h($item->entressafra->nome) ?></td><td><?= h($item->setor_principal?->nome??'Pendente') ?></td><?php endif; ?>
                <td><span class="status <?= $item->ativo ? 'status-ativa' : '' ?>"><?= $item->ativo ? 'Ativo' : 'Inativo' ?></span></td>
            <?php endif; ?>
            <td class="text-end text-nowrap"><?php if($cadastro==='Cadernos'&&in_array($currentUser->perfil,['ADMIN_PCM','PCM'],true)): ?><?= $this->Html->link('Vincular O.S.',['controller'=>'Organizacao','action'=>'lista',$item->id],['class'=>'btn btn-sm btn-primary']) ?> <?php endif; ?><?= $this->Html->link('Visualizar', ['action' => 'view', $item->id], ['class' => 'btn btn-sm btn-light']) ?> <?= $this->Html->link('Editar', ['action' => 'edit', $item->id], ['class' => 'btn btn-sm btn-outline-primary']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if ($registros->items()->isEmpty()): ?><tr><td colspan="5" class="text-center text-secondary py-5">Nenhum registro encontrado. Cadastre um novo item ou ajuste os filtros.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center mt-3">
        <small class="text-secondary"><?= $this->Paginator->counter('Página {{page}} de {{pages}} · {{count}} registro(s)') ?></small>
        <nav aria-label="Paginação"><ul class="pagination mb-0"><?= $this->Paginator->prev('Anterior') ?><?= $this->Paginator->numbers() ?><?= $this->Paginator->next('Próxima') ?></ul></nav>
    </div>
</section>

<?php if($cadastro==='Cadernos'&&in_array($currentUser->perfil,['ADMIN_PCM','PCM'],true)): ?><details class="panel mt-3"><summary>Ferramentas avançadas</summary><?= $this->Html->link('Vincular O.S. por serviço — ferramenta excepcional',['controller'=>'VinculosLote','action'=>'index'],['class'=>'btn btn-outline-secondary mt-2']) ?><?= $this->Html->link('Classificação principal dos cadernos',['controller'=>'Organizacao','action'=>'index'],['class'=>'btn btn-outline-secondary mt-2']) ?></details><?php endif; ?>
