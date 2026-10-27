<?php
$this->assign('title','Registros da O.S. '.$activity->os_numero);
$admin=$currentUser->perfil==='ADMIN_PCM';
$status=static fn($row):string=>$row->controle_pcm==='lancado'?'Lançado manualmente no TOTVS':($row->controle_pcm==='conferido'?'Conferido pelo PCM · pendente no TOTVS':'Pendente');
$quantity=static fn($value):string=>str_replace('.',',',str_contains((string)$value,'.')?rtrim(rtrim((string)$value,'0'),'.'):(string)$value);
?>
<div class="page-heading mb-3"><h1>O.S. <?= h($activity->os_numero) ?> — <?= h($presentation['setor_apresentacao']) ?></h1><?= $this->Html->link('Voltar à fila',['action'=>'index'],['class'=>'btn btn-light']) ?></div>
<section class="panel mb-4"><div><strong>Descrição da atividade:</strong> <?= h($presentation['descricao']) ?></div><div><strong>Equipamento:</strong> <?= h($activity->bem) ?></div><div><strong>Percentual atual de evolução:</strong> <?= h($completion['percentual_atual']) ?>%</div></section>
<section class="panel mb-4" id="horas"><h2>Apontamentos de horas</h2>
<?php if($rows->count()): ?>
<?= $this->Form->create(null,['id'=>'horas-controle']) ?><?= $this->Form->hidden('categoria',['value'=>'horas','id'=>'horas-categoria']) ?>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Selecionar</th><th>Funcionário</th><th>Data</th><th>Períodos</th><th>Horas</th><th>Serviço realizado</th><th>Situação</th><?php if($admin): ?><th>Ação</th><?php endif; ?></tr></thead><tbody>
<?php foreach($rows as $r): $start=$r->inicio->setTimezone('America/Sao_Paulo');$end=$r->fim->setTimezone('America/Sao_Paulo'); ?>
<tr class="<?= $r->controle_pcm==='lancado'?'pcm-tratado':'' ?>"><td><?php if(!$r->cancelado): ?><input type="checkbox" name="selecionados[]" value="<?= h($r->id) ?>" aria-label="Selecionar apontamento <?= h($r->id) ?>"><?= $this->Form->hidden('revisoes.'.$r->id,['value'=>$r->revisao_pcm,'id'=>'horas-revisao-'.$r->id]) ?><?php endif; ?></td><td><?= h($r->funcionario_nome) ?><small class="d-block"><?= h($r->funcionario_matricula) ?> · <?= $this->Html->link('#'.$r->id,['controller'=>'Apontamentos','action'=>'view',$r->id]) ?></small></td><td><?= h($start->format('d/m/Y')) ?></td><td><?= $this->element('entry_periods',['entry'=>$r]) ?></td><td><?= number_format($r->minutos/60,2,',','.') ?>h</td><td class="text-break"><?= h($r->descricao) ?></td><td><?= h($r->cancelado?'Cancelado':$status($r)) ?><?php if($r->confirmado_em): ?><small class="d-block">Confirmado em <?= h($r->confirmado_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i')) ?></small><?php endif; ?></td><?php if($admin): ?><td><?php if(!$r->cancelado&&$r->controle_pcm!=='lancado'): ?><?= $this->Html->link('Editar hora',['controller'=>'Apontamentos','action'=>'edit',$r->id],['class'=>'btn btn-sm btn-outline-primary']) ?><?php endif; ?></td><?php endif; ?></tr>
<?php endforeach; ?></tbody></table></div>
<?= $this->element('pcm_confirmation',['category'=>'horas','label'=>'apontamentos de horas']) ?><?= $this->Form->end() ?>
<?= $this->element('operation_pagination') ?>
<?php else: ?><p class="text-secondary mb-0">Nenhum apontamento de horas registrado.</p><?php endif; ?>
</section>
<section class="panel mb-4" id="insumos"><h2>Insumos utilizados</h2>
<?php if($supplies->count()): ?>
<?= $this->Form->create(null,['id'=>'insumos-controle']) ?><?= $this->Form->hidden('categoria',['value'=>'insumos','id'=>'insumos-categoria']) ?>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Selecionar</th><th>Funcionário</th><th>Data</th><th>Material</th><th>Quantidade</th><th>Situação</th></tr></thead><tbody>
<?php foreach($supplies as $r): ?><tr class="<?= $r->controle_pcm==='lancado'?'pcm-tratado':'' ?>"><td><input type="checkbox" name="selecionados[]" value="<?= h($r->id) ?>" aria-label="Selecionar insumo <?= h($r->id) ?>"><?= $this->Form->hidden('revisoes.'.$r->id,['value'=>$r->revisao_pcm,'id'=>'insumos-revisao-'.$r->id]) ?></td><td><?= h($r->funcionario_nome) ?><small class="d-block"><?= h($r->funcionario_matricula) ?> · Insumo #<?= h($r->id) ?></small></td><td><?= h($r->data->format('d/m/Y')) ?></td><td class="text-break"><?= h($r->material) ?></td><td><?= h($quantity($r->quantidade)) ?></td><td><?= h($status($r)) ?><?php if($r->confirmado_em): ?><small class="d-block">Confirmado em <?= h($r->confirmado_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i')) ?></small><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?= $this->element('pcm_confirmation',['category'=>'insumos','label'=>'insumos']) ?><?= $this->Form->end() ?>
<?= $this->element('pcm_section_pagination',['page'=>$supplyPage,'pages'=>$supplyPages,'total'=>$supplyTotal,'queryKey'=>'insumos_pagina','anchor'=>'insumos']) ?>
<?php else: ?><p class="text-secondary mb-0">Nenhum insumo registrado.</p><?php endif; ?>
</section>
<section class="panel mb-4" id="historico"><h2>Histórico de recebimento e tratamento</h2>
<?php $actions=['criacao'=>'Recebimento de apontamento','correcao'=>'Correção de apontamento','confirmacao_totvs'=>'Confirmação de apontamento','reabertura_totvs'=>'Reabertura de apontamento','insumo_criacao'=>'Recebimento de insumo','insumo_confirmacao_totvs'=>'Confirmação de insumo','insumo_reabertura_totvs'=>'Reabertura de insumo']; ?>
<?php foreach($history as $audit): ?><details class="border-bottom py-3"><summary><?= h($audit->created->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i')) ?> · <?= h($audit->usuario_nome) ?> · <?= h($actions[$audit->acao]??$audit->acao) ?> · <?= h($audit->insumo_id?'Insumo #'.$audit->insumo_id:'Apontamento #'.$audit->apontamento_id) ?></summary><p class="mt-2"><?= h($audit->motivo?:'Registro recebido') ?></p><div class="row"><div class="col-md-6"><strong>Antes</strong><pre class="small text-wrap text-break"><?= h(\App\Service\OsApresentacao::audit($audit->antes)?:'Sem registro anterior') ?></pre></div><div class="col-md-6"><strong>Depois</strong><pre class="small text-wrap text-break"><?= h(\App\Service\OsApresentacao::audit($audit->depois)) ?></pre></div></div></details><?php endforeach; ?>
<?php if(!$history->count()): ?><p class="text-secondary mb-0">Nenhum histórico registrado.</p><?php endif; ?>
<?= $this->element('pcm_section_pagination',['page'=>$historyPage,'pages'=>$historyPages,'total'=>$historyTotal,'queryKey'=>'historico_pagina','anchor'=>'historico']) ?>
</section>
<p class="small text-secondary">As confirmações são independentes por categoria e registram somente o lançamento manual no TOTVS. Nenhuma escrita automática é realizada.</p>
<?= $this->Html->link('Evolução e histórico da O.S.',['controller'=>'Evolucao','action'=>'historico',$activity->id]) ?>
