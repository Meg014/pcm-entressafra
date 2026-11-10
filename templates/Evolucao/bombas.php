<?php
$this->assign('title','Gerenciar O.S. de Bombas');
$this->Html->css('curva-semanal.css?v='.filemtime(WWW_ROOT.'css/curva-semanal.css'),['block'=>true]);
$this->Html->script('mecanica-bombas.js?v='.filemtime(WWW_ROOT.'js/mecanica-bombas.js'),['block'=>true]);
?>
<div class="page-heading"><div><p class="eyebrow">MECÂNICA · ENTRESSAFRA SELECIONADA</p><h1>Gerenciar O.S. de Bombas</h1></div>
<?= $this->Html->link('Voltar às curvas',['action'=>'controle','?'=>['entressafra_id'=>$season,'area_id'=>$area]],['class'=>'btn btn-outline-secondary']) ?></div>
<section class="panel mt-3">
<p>Marque as O.S. de Bombas. Todas as outras O.S. programadas da Mecânica entram automaticamente em Redutores. Para remover uma O.S. de Bombas, desmarque-a e salve.</p>
<p><strong><?= h($counts['bombas']) ?></strong> em Bombas · <strong><?= h($counts['redutores']) ?></strong> em Redutores · <strong><?= h($counts['geral']) ?></strong> na Mecânica Geral (classificação salva).</p>
<?php if($errors): ?><div class="alert alert-danger" role="alert"><strong>Nenhuma alteração foi salva.</strong><ul><?php foreach($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?= $this->Form->create(null,['id'=>'bombas-form']) ?>
<?= $this->Form->hidden('base',['value'=>$base]) ?>
<?= $this->Form->hidden('grupo_manual',['value'=>'bombas']) ?>
<?php if(($versions[0]['grupo_manual']??null)==='redutores'): ?><p class="alert alert-info">A última versão foi salva pela regra antiga de Redutores. As O.S. do seu complemento estão pré-selecionadas como Bombas. Ao salvar, uma nova versão de Bombas será criada, preservando a classificação e o histórico anteriores.</p><?php endif; ?>
<label for="bombas-search" class="form-label">Pesquisar O.S. pelo número ou filial</label>
<input type="search" id="bombas-search" class="form-control mb-3" placeholder="Ex.: 000123 ou 01/000123" autocomplete="off">
<label class="d-block mb-2"><input type="checkbox" id="bombas-only-selected"> Mostrar somente O.S. selecionadas para Bombas</label>
<div class="table-responsive mecanica-orders"><table class="table"><thead><tr><th>Bombas</th><th>O.S.</th><th>Filial</th><th>Descrição</th></tr></thead><tbody>
<?php foreach($orders as $order): ?>
<tr data-bomba-row data-search="<?= h(mb_strtolower($order['os_numero'].' '.$order['os_filial'].' '.$order['os_filial'].'/'.$order['os_numero'])) ?>"><td><input type="checkbox" name="ordens[]" value="<?= h($order['id']) ?>" <?= in_array((int)$order['id'],$selected,true)?'checked':'' ?> aria-label="<?= h('Bombas: O.S. '.$order['os_numero'].' filial '.$order['os_filial']) ?>"></td><td><?= h($order['os_numero']) ?></td><td><?= h($order['os_filial']) ?></td><td><?= h($order['descricao_atividade']??'') ?></td></tr>
<?php endforeach; ?>
<?php if(!$orders): ?><tr><td colspan="4">Não há O.S. programadas na Mecânica desta entressafra.</td></tr><?php endif; ?>
</tbody></table></div>
<p id="bombas-selection-count" class="text-secondary mt-2" aria-live="polite"></p>
<?= $this->Form->control('lista',['type'=>'textarea','label'=>'Adicionar uma lista de O.S. a Bombas','value'=>$pasted,'rows'=>5,'class'=>'form-control','maxlength'=>100000,'placeholder'=>"000123\n000456; 01/000789"]) ?>
<p class="text-secondary">Separe por linha, vírgula ou ponto e vírgula. Preserve os zeros à esquerda. Se o número existir em mais de uma filial, use filial/número ou selecione a linha correta. A lista colada será adicionada às O.S. marcadas; qualquer erro impede o salvamento inteiro.</p>
<input type="hidden" name="selecao_json" id="bombas-selection-json" value="">
<input type="hidden" name="lista_completa" value="1">
<?= $this->Form->button('Salvar alterações',['class'=>'btn btn-primary']) ?>
<?= $this->Form->end() ?>
</section>
<section class="panel mt-3"><h2>Histórico de alterações</h2><p>As versões anteriores são preservadas e usadas pela consulta com data de corte.</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Versão</th><th>Salvo em (Brasília)</th><th>Responsável</th><th>Lista manual da versão</th></tr></thead><tbody>
<?php foreach($versions as $version): $members=json_decode($version['conteudo'],true,512,JSON_THROW_ON_ERROR); ?><tr><td><?= h($version['id']) ?></td><td><?= h((new DateTimeImmutable($version['created'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y H:i:s')) ?></td><td><?= h($version['usuario_nome']) ?></td><td><details><summary><?= h(count($members)) ?> O.S. de <?= h(($version['grupo_manual']??'redutores')==='bombas'?'Bombas':'Redutores (legado)') ?></summary><?php foreach($members as $member): ?><span class="d-block"><?= h($member['os_filial'].'/'.$member['os_numero']) ?></span><?php endforeach; ?></details></td></tr><?php endforeach; ?>
<?php if(!$versions): ?><tr><td colspan="4">Sem alterações registradas. Todas as O.S. programadas estão em Redutores.</td></tr><?php endif; ?>
</tbody></table></div></section>
