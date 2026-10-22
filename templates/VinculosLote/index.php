<?php $this->assign('title','Vincular O.S. em Lote'); ?>
<h1>Vincular O.S. em Lote</h1>
<p class="text-secondary">Prepare a prévia, confira a organização dos cadernos e confirme a quantidade. Um serviço pode abranger vários cadernos.</p>
<div id="bulk-links" data-api="<?= h($this->Url->build(['action'=>'api'])) ?>" data-token="<?= h($token) ?>">
<section class="panel form-panel mb-4">
<?= $this->Form->create(null,['id'=>'bulk-form']) ?>
<div class="row g-3">
<div class="col-md-4"><?php if(isset($periodoSelecionado)): ?><?= $this->Form->hidden('entressafra_id',['value'=>$periodoSelecionado->id]) ?><?php else: ?><?= $this->Form->control('entressafra_id',['label'=>'Entressafra','options'=>$seasons,'empty'=>'Selecione','class'=>'form-select','required'=>true]) ?><?php endif; ?></div>
<div class="col-md-4"><?= $this->Form->control('setor_id',['label'=>'Setor executante desta prévia','options'=>$sectors,'empty'=>'Selecione','class'=>'form-select','required'=>true]) ?></div>
<div class="col-md-4"><label for="bulk-caderno" class="form-label">Caderno de destino</label><select id="bulk-caderno" name="caderno_id" class="form-select" required><option value="">Selecione</option><?php foreach ($notebooks as $notebook): ?><option value="<?= h($notebook->id) ?>" data-setores="<?= h(implode(',',array_map(fn($s)=>$s->id,$notebook->setores))) ?>" data-entressafra="<?= h($notebook->entressafra_id) ?>"><?= h($notebook->nome) ?></option><?php endforeach; ?></select></div>
<div class="col-12"><label for="bulk-services" class="form-label">Serviços TOTVS — selecione um ou mais (até 20)</label><select id="bulk-services" class="form-select" multiple size="5" required aria-describedby="bulk-services-help"></select><p id="bulk-services-help" class="small text-secondary">Escolha entressafra, setor e caderno para carregar os serviços. Use Ctrl/Cmd para seleção múltipla.</p></div>
<?php foreach (['cc'=>'Centro de custo contém','bem'=>'Bem/equipamento contém','busca'=>'Número da O.S. ou serviço contém'] as $field=>$label): ?><div class="col-md-4"><?= $this->Form->control($field,['label'=>$label,'class'=>'form-control','maxlength'=>60]) ?></div><?php endforeach; ?>
</div>
<p class="small text-secondary mt-3">Somente família 010, L / término N, origem em 2026 e serviço ENTRESSAFRA. A área e a filial vêm do setor. A prévia não cria vínculos.</p>
<button id="bulk-preview" class="btn btn-primary" type="submit">Preparar prévia</button>
<button id="bulk-new" class="btn btn-outline-secondary" type="button" hidden>Nova pesquisa / outro caderno</button>
<?= $this->Form->end() ?>
</section>
<div id="bulk-message" class="alert alert-info" role="status" aria-live="polite">Selecione os filtros para começar.</div>
<section id="bulk-results" class="panel" hidden>
<h2 class="h5" id="bulk-title">Prévia da vinculação</h2>
<p id="bulk-scope" class="small text-secondary"></p>
<div id="bulk-counts" class="d-flex gap-3 flex-wrap mb-3" aria-live="polite"></div>
<div class="d-flex gap-2 flex-wrap align-items-center mb-3">
<input id="bulk-search" type="search" maxlength="60" class="form-control" style="max-width:340px" aria-label="Filtrar a prévia" placeholder="Filtrar O.S., serviço, bem ou C.C.">
<button id="bulk-all" type="button" class="btn btn-outline-primary">Selecionar todas as elegíveis</button>
<button id="bulk-none" type="button" class="btn btn-outline-secondary">Limpar seleção</button>
</div>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Selecionar</th><th>O.S. / filial</th><th>Código / serviço TOTVS</th><th>Área / C.C.</th><th>Bem</th><th>Abertura</th><th>Situação</th><th>Caderno vinculado / resultado</th></tr></thead><tbody id="bulk-rows"></tbody></table></div>
<div class="d-flex flex-wrap gap-2 align-items-center my-3"><button id="bulk-prev" type="button" class="btn btn-outline-secondary">Anterior</button><span id="bulk-pages"></span><button id="bulk-next" type="button" class="btn btn-outline-secondary">Próxima</button></div>
<div class="d-flex flex-wrap gap-2"><button id="bulk-confirm" type="button" class="btn btn-primary">Confirmar vinculação</button><button id="bulk-resume" type="button" class="btn btn-outline-primary" hidden>Retomar</button><button id="bulk-stop" type="button" class="btn btn-outline-secondary" hidden>Parar após este lote</button></div>
<p class="small text-secondary mt-3">As seleções permanecem entre páginas. Cada item é revalidado no TOTVS antes de salvar. Falhas revertem somente o item afetado; vínculos já concluídos permanecem. Atualizar a página permite retomar a operação.</p>
</section>
<noscript>Ative o JavaScript para preparar a prévia e confirmar a vinculação em lote.</noscript>
</div>
<?= $this->Html->script('bulk-links.js?v='.filemtime(WWW_ROOT.'js/bulk-links.js'),['block'=>true]) ?>
