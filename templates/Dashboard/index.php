<?php $this->assign('title', 'Visão geral'); ?>
<div class="page-heading"><div><p class="eyebrow">PLANEJAMENTO INDUSTRIAL</p><h1>Visão geral da entressafra</h1><p class="text-secondary mb-0">Organize as entressafras, os setores e seus cadernos de manutenção.</p></div><?= $this->Html->link('Cadastrar entressafra', ['controller' => 'Entressafras', 'action' => 'add'], ['class' => 'btn btn-primary']) ?></div>
<section class="panel mt-4">
    <div class="row g-4 align-items-center">
        <div class="col-lg-7"><p class="eyebrow">ENTRESSAFRA SELECIONADA</p>
        <?php if ($selecionada): ?>
            <h2 class="mb-2"><?= h($selecionada->nome) ?> <span class="status status-<?= h($selecionada->status) ?>"><?= h(\App\Model\Table\EntressafrasTable::STATUS[$selecionada->status]) ?></span></h2>
            <p class="text-secondary mb-0"><?= h($selecionada->data_inicio->format('d/m/Y')) ?> a <?= h($selecionada->data_fim->format('d/m/Y')) ?> · <?= $cadernosSelecionados ?> caderno(s) nesta entressafra</p>
        <?php else: ?><h2>Comece pela sua primeira entressafra</h2><p class="text-secondary mb-0">Cadastre a entressafra 2026/2027 e depois organize os setores e cadernos.</p><?php endif; ?>
        </div>
        <div class="col-lg-5"><?php if(isset($periodoSelecionado)): ?><p>Período definido na sessão.</p><?php endif; ?></div>
    </div>
</section>
<div class="row g-4 mt-1">
    <div class="col-md-4"><section class="panel metric"><span class="eyebrow">ENTRESSAFRAS ATIVAS</span><strong><?= $ativas->count() ?></strong><div class="text-secondary"><?php if ($ativas->isEmpty()): ?>Nenhuma entressafra ativa.<?php else: ?><?php foreach ($ativas as $ativa): ?><?= $this->Html->link($ativa->nome, ['controller' => 'Entressafras', 'action' => 'view', $ativa->id], ['class' => 'd-block']) ?><?php endforeach; ?><?php endif; ?></div></section></div>
    <div class="col-md-4"><section class="panel metric"><span class="eyebrow">SETORES CADASTRADOS</span><strong><?= $totalSetores ?></strong><span class="text-secondary">Total geral de setores</span></section></div>
    <div class="col-md-4"><section class="panel metric"><span class="eyebrow">CADERNOS CADASTRADOS</span><strong><?= $totalCadernos ?></strong><span class="text-secondary">Total de todas as entressafras</span></section></div>
</div>
<section class="mt-5"><h2 class="mb-3">Acesso aos cadastros</h2><div class="row g-4">
<?php foreach (['Entressafras' => 'Defina o período e acompanhe o status de cada entressafra.', 'Setores' => 'Cadastre setores executantes e seus mapeamentos de área TOTVS.', 'Cadernos' => 'Organize atividades e associe vários setores ao caderno da entressafra.'] as $name => $description): ?>
<div class="col-md-4"><div class="panel h-100 d-flex flex-column"><h3><?= h($name) ?></h3><p class="text-secondary flex-grow-1"><?= h($description) ?></p><?= $this->Html->link('Acessar ' . mb_strtolower($name) . ' →', ['controller' => $name, 'action' => 'index'], ['class' => 'fw-semibold']) ?></div></div>
<?php endforeach; ?>
</div></section>
