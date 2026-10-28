<?php
$this->assign('title','Controle PCM / Curva S');
if($simulation)$this->assign('title','SIMULAÇÃO — DADOS FICTÍCIOS · Curva S ELÉTRICA');
$this->Html->css('curva-semanal.css?v='.filemtime(WWW_ROOT.'css/curva-semanal.css'), ['block'=>true]);
$fmt=fn($v)=>$v===null?'—':number_format((float)$v,0,',','.').'%';
$query=$this->request->getQueryParams();
?>
<div class="page-heading"><div><p class="eyebrow">PCM · ENTRESSAFRA <?= h($seasonRow->nome) ?></p><h1>Evolução por setor</h1></div><details class="position-relative"><summary class="btn btn-outline-secondary">Administração</summary><div class="panel mt-2 d-grid gap-2">
<?= $this->Html->link('Cadastros de setores e cadernos',['controller'=>'Cadernos','action'=>'index'],['class'=>'btn btn-light']) ?>
<?php if($currentUser->perfil==='ADMIN_PCM'): ?><?= $this->Html->link('Calendário e apuração semanal',['action'=>'programacaoSemanal','?'=>['entressafra_id'=>$season]],['class'=>'btn btn-light']) ?><?php endif; ?>
<?= $this->Html->link('Análise e versões históricas',['action'=>'analise','?'=>['entressafra_id'=>$season]],['class'=>'btn btn-light']) ?>
</div></details></div>
<?php if($currentUser->perfil==='ADMIN_PCM'&&$electricalArea!==null): ?>
<div class="d-flex flex-wrap gap-2 my-3" data-simulation-controls>
<?php if(!$simulation): ?><?= $this->Html->link('Simular evolução',['action'=>'controle','?'=>['entressafra_id'=>$season,'area_id'=>$electricalArea,'simular'=>'eletrica']],['class'=>'btn btn-outline-primary','title'=>'Simular todas as semanas da ELÉTRICA, sem gravar dados']) ?>
<?php else: ?><?= $this->Html->link('Voltar aos dados reais',['action'=>'controle','?'=>['entressafra_id'=>$season,'area_id'=>$electricalArea]],['class'=>'btn btn-primary','data-return-real'=>true]) ?><?php endif; ?>
</div>
<?php endif; ?>
<?php if($simulation): ?><div class="alert alert-warning" role="status" data-simulation-banner><strong>SIMULAÇÃO — DADOS FICTÍCIOS</strong><br>ELÉTRICA · Realizado, tendência e diferenças do gráfico e da tabela são simulados para todas as semanas. Programado e barra de dias permanecem reais. Nenhum lançamento, fechamento ou auditoria é gravado. Os dados reais das O.S. abaixo permanecem em consulta.</div><?php endif; ?>
<?php if($demo): ?><div class="alert alert-warning"><strong>DEMONSTRAÇÃO ISOLADA — valores simulados.</strong> Duas posições hipotéticas: 30% e 40% nas O.S. locais da equipe Elétrica. Datas e percentuais desta visualização não são execução oficial. Nenhum dado é gravado ou substituído. <?= $this->Html->link('Encerrar demonstração',['action'=>'controle','?'=>['entressafra_id'=>$season]],['class'=>'btn btn-outline-dark ms-2']) ?></div><?php endif; ?>
<section class="panel mt-3">
<?= $this->Form->create(null,['type'=>'get','class'=>'row g-3 align-items-end']) ?>
<?= $this->Form->hidden('entressafra_id',['value'=>$season]) ?>
<div class="col-md-8"><?= $this->Form->control('area_id',['label'=>$demo?'Escopo da demonstração':'Setor / área de apresentação da curva','options'=>$demo?['eletrica'=>'Elétrica · equipe executante (DEMONSTRAÇÃO)']:[''=>'Consolidado da entressafra']+$areas+['pendentes'=>'Pendências de classificação'],'value'=>$demo?'eletrica':$area,'class'=>'form-select','disabled'=>$demo]) ?></div>
<?php if($demo): ?><?= $this->Form->hidden('demonstracao',['value'=>'eletrica']) ?><?php endif; ?>
<div class="col-md-4"><?= $this->Form->button('Atualizar visão',['class'=>'btn btn-primary w-100']) ?></div>
<?= $this->Form->end() ?>
</section>
<div class="curve-overview"><?php foreach(['total'=>'Total de O.S.','sem_inicio'=>'Sem início','andamento'=>'Em andamento','concluidas'=>'Em 100%'] as $key=>$label): ?><span><strong><?= h($report['metrics'][$key]) ?></strong><?= h($label) ?></span><?php endforeach; ?><span class="current" title="Média simples dos últimos acumulados; sem lançamento: 0%. Todas as O.S. têm peso igual."><?= $simulation?'Evolução atual real':'Evolução atual' ?> <strong><?= h($fmt($report['metrics']['simples'])) ?></strong> · <?= h($report['reference']) ?></span></div>
<?php if(!$demo): ?>
<?php if($weekly): ?><?= $this->element('curva_semanal',compact('weekly','areaName','area')) ?>
<?php else: ?><section class="panel mb-4"><h2>Curva S semanal</h2><p>Selecione um setor para visualizar sua programação, os fechamentos das terças-feiras e a tendência.</p></section><?php endif; ?>
<?php endif; ?>
<?php if($demo): $demoPoints=$report['points']; ?>
<section class="panel mb-4"><h2>Evolução demonstrativa — valores simulados</h2>
<svg viewBox="0 0 940 300" role="img" aria-label="Demonstração isolada de evolução: 30% e 40%" style="width:100%;height:auto;max-height:350px">
<?php foreach([0,25,50,75,100] as $value): ?><line x1="45" x2="880" y1="<?= 255-$value*2.2 ?>" y2="<?= 255-$value*2.2 ?>" stroke="#e3e8ec"/><text x="1" y="<?= 259-$value*2.2 ?>" font-size="12"><?= $value ?>%</text><?php endforeach; ?>
<polyline points="45,189 880,167" fill="none" stroke="#7952b3" stroke-width="3"/>
<?php foreach($demoPoints as $i=>$point): ?><text x="<?= $i?800:45 ?>" y="288" font-size="12"><?= h($point['referencia']) ?></text><?php endforeach; ?>
</svg><p class="small text-secondary">Demonstração isolada, sem apuração ou gravação de percentuais reais.</p></section>
<?php endif; ?>
<section class="panel"><div class="d-flex flex-wrap justify-content-between gap-2"><h2>Relação de O.S. — <?= h($areaName) ?><?= $simulation?' (dados reais)':'' ?></h2><span class="badge text-bg-light align-self-start">10 por página</span></div>
<?= $this->Form->create(null,['type'=>'get','class'=>'d-flex gap-2 mb-3']) ?><?= $this->Form->hidden('entressafra_id',['value'=>$season]) ?><?= $this->Form->hidden('area_id',['value'=>$area]) ?><?php if($demo): ?><?= $this->Form->hidden('demonstracao',['value'=>'eletrica']) ?><?php endif; ?><?= $this->Form->control('q',['label'=>false,'value'=>$search,'placeholder'=>'Pesquisar O.S. ou descrição','class'=>'form-control','maxlength'=>60]) ?><?= $this->Form->button('Pesquisar',['class'=>'btn btn-outline-primary']) ?><?= $this->Form->end() ?>
<div class="table-responsive"><table class="table"><thead><tr><th>O.S.</th><th>Descrição da atividade</th><th>Equipe executante</th><th>Acumulado</th><th>Última atualização</th></tr></thead><tbody>
<?php foreach($report['details'] as $row): ?><tr data-os-row><td><?= $demo?h($row['os_numero']):$this->Html->link($row['os_numero'],['controller'=>'Atividades','action'=>'view',$row['id']]) ?></td><td><?= h(\App\Service\OsApresentacao::description($row['descricao_atividade'],json_decode($row['snapshot_totvs'],true)??[])) ?></td><td><?= h(\App\Service\OsApresentacao::equipe($row['equipe']??null)) ?></td><td><?= h($fmt($row['percentual'])) ?></td><td><?= $demo?'DEMONSTRAÇÃO':($row['ultima_atualizacao']?h((new DateTimeImmutable($row['ultima_atualizacao'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y H:i')):'Sem lançamento') ?></td></tr><?php endforeach; ?>
<?php if(!$report['details']): ?><tr><td colspan="5">Nenhuma O.S. neste filtro.</td></tr><?php endif; ?></tbody></table></div>
<?= $this->element('simple_pagination',['page'=>$report['page'],'pages'=>$report['pages']]) ?>
</section>
<?php if(!$demo&&$report['pending']): ?><p class="small text-secondary mt-3"><?= h($report['pending']) ?> O.S. sem classificação principal. Continuam recebendo lançamentos e entram uma vez no consolidado. <?= $this->Html->link('Consultar pendências',['controller'=>'Organizacao','action'=>'index','?'=>['entressafra_id'=>$season,'pendentes'=>1]]) ?></p><?php endif; ?>
