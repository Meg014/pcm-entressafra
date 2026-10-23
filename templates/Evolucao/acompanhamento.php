<?php
$this->assign('title','Controle PCM / Curva S');
$fmt=fn($v)=>$v===null?'—':number_format((float)$v,2,',','.').'%';
$query=$this->request->getQueryParams();
?>
<div class="page-heading"><div><p class="eyebrow">PCM · ENTRESSAFRA <?= h($seasonRow->nome) ?></p><h1>Evolução por setor</h1></div><details class="position-relative"><summary class="btn btn-outline-secondary">Administração</summary><div class="panel mt-2 d-grid gap-2">
<?= $this->Html->link('Cadastros de setores e cadernos',['controller'=>'Cadernos','action'=>'index'],['class'=>'btn btn-light']) ?>
<?= $this->Html->link('Pesos e programação oficial',['action'=>'configuracao','?'=>['entressafra_id'=>$season]],['class'=>'btn btn-light']) ?>
<?= $this->Html->link('Análise e versões históricas',['action'=>'analise','?'=>['entressafra_id'=>$season]],['class'=>'btn btn-light']) ?>
<?= $this->Html->link('Demonstração isolada da Elétrica',['action'=>'controle','?'=>['entressafra_id'=>$season,'demonstracao'=>'eletrica']],['class'=>'btn btn-light']) ?>
</div></details></div>
<?php if($demo): ?><div class="alert alert-warning"><strong>DEMONSTRAÇÃO ISOLADA — valores simulados.</strong> Duas posições hipotéticas: 30% e 40% nas O.S. locais da equipe Elétrica. Datas e percentuais desta visualização não são execução oficial. Nenhum dado é gravado ou substituído. <?= $this->Html->link('Encerrar demonstração',['action'=>'controle','?'=>['entressafra_id'=>$season]],['class'=>'btn btn-outline-dark ms-2']) ?></div><?php endif; ?>
<section class="panel mt-3">
<?= $this->Form->create(null,['type'=>'get','class'=>'row g-3 align-items-end']) ?>
<?= $this->Form->hidden('entressafra_id',['value'=>$season]) ?>
<div class="col-md-8"><?= $this->Form->control('area_id',['label'=>$demo?'Escopo da demonstração':'Setor / área de apresentação da curva','options'=>$demo?['eletrica'=>'Elétrica · equipe executante (DEMONSTRAÇÃO)']:[''=>'Consolidado da entressafra']+$areas+['pendentes'=>'Pendências de classificação'],'value'=>$demo?'eletrica':$area,'class'=>'form-select','disabled'=>$demo]) ?></div>
<?php if($demo): ?><?= $this->Form->hidden('demonstracao',['value'=>'eletrica']) ?><?php endif; ?>
<div class="col-md-4"><?= $this->Form->button('Atualizar visão',['class'=>'btn btn-primary w-100']) ?></div>
<?= $this->Form->end() ?>
<p class="small text-secondary mt-3 mb-0">A área da curva vem da classificação principal do caderno ou da O.S. A equipe executante e suas permissões continuam definidas pelo TOTVS. Posição atual: <?= h($report['reference']) ?>.</p>
</section>
<div class="row g-3 my-3"><?php foreach(['total'=>'Total de O.S.','sem_inicio'=>'Sem início','andamento'=>'Em andamento','concluidas'=>'Em 100%'] as $key=>$label): ?><div class="col-6 col-lg-3"><div class="panel h-100"><small><?= h($label) ?></small><strong class="d-block fs-3"><?= h($report['metrics'][$key]) ?></strong></div></div><?php endforeach; ?></div>
<section class="panel mb-4"><div class="d-flex flex-wrap justify-content-between gap-3"><div><p class="mb-1">Evolução física simples — não ponderada</p><strong class="fs-2"><?= h($fmt($report['metrics']['simples'])) ?></strong></div><div class="text-secondary small">Curva S ponderada oficial: <?= $report['official']===null?'Pesos oficiais pendentes':h($fmt($report['official'])) ?><br><?= $report['programado']===null?'Programação pendente':'Programado oficial: '.h($fmt($report['programado'])) ?></div></div>
<p class="small text-secondary mt-2">Média dos últimos acumulados das O.S. únicas deste setor. Sem lançamento: 0%. Não representa a Curva S ponderada oficial. Não é obrigatório atualizar todas as O.S. toda semana.</p>
<h2 class="mt-4">Curva de evolução — <?= h($areaName) ?></h2>
<?php if(!$report['hasHistory']): ?><p class="text-secondary">Aguardando lançamentos reais para construir a evolução.</p><?php endif; ?>
<?php $points=$report['points'];$n=count($points);$xy=fn($i,$v)=>(45+($n>1?$i/($n-1):0)*835).','.(255-$v*2.2);$path=function($key)use($points,$xy){$out=[];foreach($points as $i=>$p)if($p[$key]!==null)$out[]=$xy($i,$p[$key]);return implode(' ',$out);}; ?>
<svg viewBox="0 0 940 300" role="img" aria-label="Evolução física simples não ponderada por semana" style="width:100%;height:auto;max-height:350px">
<?php foreach([0,25,50,75,100] as $v): ?><line x1="45" x2="880" y1="<?= 255-$v*2.2 ?>" y2="<?= 255-$v*2.2 ?>" stroke="#e3e8ec"/><text x="1" y="<?= 259-$v*2.2 ?>" font-size="12"><?= $v ?>%</text><?php endforeach; ?>
<?php foreach(['simples'=>'#7952b3','ponderado'=>'#148356'] as $key=>$color): ?><polyline points="<?= h($path($key)) ?>" fill="none" stroke="<?= $color ?>" stroke-width="3"/><?php foreach($points as $i=>$p): if($p[$key]!==null): [$x,$y]=explode(',',$xy($i,$p[$key])); ?><circle cx="<?= h($x) ?>" cy="<?= h($y) ?>" r="4" fill="<?= $color ?>"><title><?= h($p['referencia'].' · '.$fmt($p[$key])) ?></title></circle><?php endif; endforeach; endforeach; ?>
<text x="45" y="288" font-size="12"><?= h($points[0]['referencia']??'') ?></text><text x="800" y="288" font-size="12"><?= h($n?$points[$n-1]['referencia']:'') ?></text></svg>
<p class="small text-secondary">Roxo: evolução simples. Verde, quando disponível: realizado ponderado aprovado. Histórico das segundas-feiras e posição atual; o último acumulado é mantido até novo lançamento.</p>
<details><summary>Histórico semanal da curva</summary><div class="table-responsive"><table class="table"><thead><tr><th>Referência</th><th>Simples (não ponderada)</th><th>Ponderada oficial</th></tr></thead><tbody><?php foreach($points as $p): ?><tr><td><?= h($p['referencia']) ?></td><td><?= h($fmt($p['simples'])) ?></td><td><?= h($fmt($p['ponderado'])) ?></td></tr><?php endforeach; ?></tbody></table></div></details>
</section>
<section class="panel"><div class="d-flex flex-wrap justify-content-between gap-2"><h2>Relação de O.S. — <?= h($areaName) ?></h2><span class="badge text-bg-light align-self-start">10 por página</span></div>
<?= $this->Form->create(null,['type'=>'get','class'=>'d-flex gap-2 mb-3']) ?><?= $this->Form->hidden('entressafra_id',['value'=>$season]) ?><?= $this->Form->hidden('area_id',['value'=>$area]) ?><?php if($demo): ?><?= $this->Form->hidden('demonstracao',['value'=>'eletrica']) ?><?php endif; ?><?= $this->Form->control('q',['label'=>false,'value'=>$search,'placeholder'=>'Pesquisar O.S. ou descrição','class'=>'form-control','maxlength'=>60]) ?><?= $this->Form->button('Pesquisar',['class'=>'btn btn-outline-primary']) ?><?= $this->Form->end() ?>
<div class="table-responsive"><table class="table"><thead><tr><th>O.S.</th><th>Descrição da atividade</th><th>Equipe executante</th><th>Acumulado</th><th>Última atualização</th></tr></thead><tbody>
<?php foreach($report['details'] as $row): ?><tr data-os-row><td><?= $demo?h($row['os_numero']):$this->Html->link($row['os_numero'],['controller'=>'Atividades','action'=>'view',$row['id']]) ?></td><td><?= h(\App\Service\OsApresentacao::description($row['descricao_atividade'],json_decode($row['snapshot_totvs'],true)??[])) ?></td><td><?= h(\App\Service\OsApresentacao::equipe($row['equipe']??null)) ?></td><td><?= h($fmt($row['percentual'])) ?></td><td><?= $demo?'DEMONSTRAÇÃO':($row['ultima_atualizacao']?h((new DateTimeImmutable($row['ultima_atualizacao'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('d/m/Y H:i')):'Sem lançamento') ?></td></tr><?php endforeach; ?>
<?php if(!$report['details']): ?><tr><td colspan="5">Nenhuma O.S. neste filtro.</td></tr><?php endif; ?></tbody></table></div>
<?= $this->element('simple_pagination',['page'=>$report['page'],'pages'=>$report['pages']]) ?>
</section>
<?php if(!$demo&&$report['pending']): ?><p class="small text-secondary mt-3"><?= h($report['pending']) ?> O.S. sem classificação principal. Continuam recebendo lançamentos e entram uma vez no consolidado. <?= $this->Html->link('Consultar pendências',['controller'=>'Organizacao','action'=>'index','?'=>['entressafra_id'=>$season,'pendentes'=>1]]) ?></p><?php endif; ?>
