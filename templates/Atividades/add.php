<?php $this->assign('title', 'Vincular O.S.'); ?>
<h1>Vincular O.S. ao caderno</h1><p class="text-secondary">A O.S. será consultada e confirmada no TOTVS antes do vínculo.</p>
<section class="panel form-panel"><?= $this->Form->create(null) ?>
<div class="mb-3"><?php if(isset($periodoSelecionado)): ?><?= $this->Form->hidden('entressafra_id',['value'=>$periodoSelecionado->id]) ?><?php else: ?><?= $this->Form->control('entressafra_id',['label'=>'Entressafra','options'=>$seasons,'empty'=>'Selecione','class'=>'form-select','required'=>true]) ?><?php endif; ?></div>
<div class="mb-3"><?= $this->Form->control('caderno_id', ['label' => 'Caderno de destino', 'options' => $notebooks, 'empty' => 'Selecione', 'class' => 'form-select','required'=>true]) ?></div>
<div class="mb-3"><?= $this->Form->control('setor_id',['label'=>'Filtrar por setor executante','options'=>$sectors,'empty'=>'Todos os setores autorizados do caderno','class'=>'form-select','value'=>$this->request->getData('setor_id') ?? (is_string($this->request->getQuery('setor_id')) ? $this->request->getQuery('setor_id') : null)]) ?></div>
<p class="small text-secondary">Sem filtro de setor, a consulta considera os setores associados ao caderno para os quais você tem autorização. A área executante é a da O.S., não a área física da fábrica.</p>
<div class="mb-3"><?= $this->Form->control('pesquisa', ['label' => 'Pesquisar número ou serviço da O.S.', 'class' => 'form-control', 'maxlength' => 60, 'value'=>$this->request->getData('pesquisa') ?? (is_string($this->request->getQuery('pesquisa')) ? mb_substr($this->request->getQuery('pesquisa'),0,60) : '')]) ?></div>
<p class="small text-secondary">Somente L / término N, origem em 2026 e serviço contendo ENTRESSAFRA, incluindo códigos antigos e atuais.</p>
<?= $this->Form->button('Pesquisar no TOTVS', ['name' => 'acao', 'value' => 'pesquisar', 'class' => 'btn btn-outline-primary', 'formnovalidate' => true]) ?>
<?php if ($matches): ?>
<div class="my-4"><?php $choices = []; foreach ($matches as $item) { $choices[$item['chave']] = 'O.S. '.$item['numero'].' · Descrição: '.\App\Service\OsApresentacao::description(null,$item).' · Equipe executante: '.\App\Service\OsApresentacao::equipe($item['codigo_area']); } ?><?= $this->Form->control('os_chave', ['label' => 'O.S. encontrada', 'options' => $choices, 'empty' => 'Selecione', 'class' => 'form-select']) ?></div>
<?= $this->Form->button('Vincular ao caderno', ['name' => 'acao', 'value' => 'vincular', 'class' => 'btn btn-primary']) ?>
<?php else: ?><p class="text-secondary mt-3 mb-0">Pesquise para selecionar uma O.S. Não é permitido digitar dados cadastrais manualmente.</p><?php endif; ?>
<?= $this->Form->end() ?></section>
