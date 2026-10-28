<?php
$this->Html->script('curva-semanal-layout.js?v='.filemtime(WWW_ROOT.'js/curva-semanal-layout.js'), ['block'=>true]);
$format=static fn($v)=>$v===null?'—':number_format((float)$v,0,',','.').'%';
$short=$format;
$date=static fn($v)=>(new DateTimeImmutable($v))->format('d/m/Y');
$simulated=!empty($weekly['simulation']);
$latest=$weekly['latest'];$points=$weekly['points'];$delta=$latest['diferenca']??null;
$days=$weekly['display_days']??['total'=>$weekly['days']['total'],'decorridos'=>$weekly['days']['realizados'],'restantes'=>$weekly['days']['restantes'],'data'=>null];
$total=$days['total'];$elapsed=$days['decorridos'];$remaining=$days['restantes'];
$fraction=$total?100*$elapsed/$total:0;
$zone=new DateTimeZone('America/Sao_Paulo');
$origin=(new DateTimeImmutable($weekly['graph_start'],$zone))->getTimestamp();
$end=(new DateTimeImmutable($weekly['graph_end'],$zone))->getTimestamp();
$width=max(1060,count($points)*76+160);
$x=static fn(array $p)=>64+((new DateTimeImmutable($p['data']??($p['fim'].'T23:59:59'),$zone))->getTimestamp()-$origin)/max(1,$end-$origin)*($width-108);
$y=static fn($v)=>330-$v*2.7;
$sets=['programado'=>$weekly['programmed_graph'],'realizado'=>$points,'tendencia'=>$points];
$colors=['programado'=>'#173e70','realizado'=>'#148356','tendencia'=>'#d87912'];
$names=['programado'=>'Programado','realizado'=>'Realizado','tendencia'=>'Tendência (estimativa)'];
if($simulated){$names['realizado']='Realizado simulado';$names['tendencia']='Tendência simulada (estimativa)';}
$path=static function($key)use($sets,$x,$y){$segments=[];$connected=false;foreach($sets[$key] as $p){if($p[$key]===null){$connected=false;continue;}$segments[]=($connected?'L':'M').$x($p).','.$y($p[$key]);$connected=true;}return implode(' ',$segments);};
?>
<section class="panel curve-sheet mb-4" id="curva-semanal" <?= $simulated?'data-simulation="true"':'' ?>>
<div class="curve-heading"><div><div class="curve-kicker">Controle de entressafra</div><h2>Curva S — <?= h($areaName) ?></h2><?php if($simulated): ?><p class="curve-simulation-mark">SIMULAÇÃO — DADOS FICTÍCIOS</p><?php endif; ?><p class="curve-subtitle">Programado automático — modelo histórico · referência estimada</p></div></div>
<div class="curve-days">
<div class="curve-days-total"><small>TOTAL DE DIAS</small><strong><?= h($total??'—') ?></strong><span>operacionais</span></div>
<div><div class="curve-day-titles"><span>DIAS CORRIDOS</span><span>DIAS RESTANTES</span></div>
<?php if($total!==null&&$total>0): ?>
<div class="curve-day-bar" role="img" aria-label="<?= h($elapsed.' dias corridos e '.$remaining.' dias restantes, de '.$total.' dias operacionais') ?>" data-total="<?= h($total) ?>" data-elapsed="<?= h($elapsed) ?>" data-remaining="<?= h($remaining) ?>">
<div class="curve-day-part elapsed <?= $fraction<4?'is-narrow':'' ?>" style="width:<?= h($fraction) ?>%"><?php if($elapsed): ?><strong><?= h($elapsed) ?></strong><?php endif; ?></div>
<div class="curve-day-part remaining <?= 100-$fraction<4?'is-narrow':'' ?>" style="width:<?= h(100-$fraction) ?>%"><?php if($remaining): ?><strong><?= h($remaining) ?></strong><?php endif; ?></div>
</div>
<?php if(!$elapsed||!$remaining): ?><div class="curve-zero"><?= !$elapsed?'0 dias corridos':'0 dias restantes' ?></div><?php endif; ?>
<?php else: ?><div class="curve-warning"><?= h($weekly['days']['motivo']??'Calendário sem dias operacionais.') ?></div><?php endif; ?>
<p class="curve-day-date">Calendário operacional do setor<?= $days['data']?' · posição em '.h($date($days['data'])):'' ?>.</p></div></div>
<div class="curve-metrics">
<?php foreach(['programado'=>'Programado','realizado'=>'Realizado'] as $key=>$label): ?><div class="curve-metric"><small><?= h($label.($simulated&&$key==='realizado'?' simulado':'')) ?></small><strong class="<?= $key==='programado'?'planned':'actual' ?>"><?= h($format($latest[$key]??null)) ?></strong></div><?php endforeach; ?>
<div class="curve-metric"><small>Diferença<?= $simulated?' simulada':'' ?></small><strong class="<?= $delta!==null&&$delta<0?'text-danger':'' ?>"><?= $delta===null?'—':h(($delta>0?'+':'').number_format($delta,2,',','.').' p.p.') ?></strong><span class="curve-state"><?= $delta===null?'Sem classificação':(abs($delta)<1e-9?'Dentro do programado':($delta<0?'Atrasado':'Adiantado')) ?></span></div>
<div class="curve-metric"><small>Evolução semanal<?= $simulated?' simulada':'' ?></small><strong><?= ($latest['evolucao_semanal']??null)===null?'—':h(($latest['evolucao_semanal']>0?'+':'').number_format($latest['evolucao_semanal'],2,',','.').' p.p.') ?></strong></div>
</div>
<?php if($latest): ?><p class="curve-reference">Indicadores da semana <?= h($latest['semana']) ?> · <?= h($date($latest['inicio']).' a '.$date($latest['fim'])) ?> · análise em <?= h($date($latest['referencia'])) ?> · <?= $simulated?'posição final simulada — sem fechamento real':($latest['versao_id']?'apuração consolidada':'aguardando apuração') ?>.</p><?php endif; ?>
<?php foreach($weekly['days']['warnings'] as $warning): ?><p class="curve-warning"><?= h($warning) ?></p><?php endforeach; ?>
<div class="curve-chart-scroll" tabindex="0" role="region" aria-label="Gráfico semanal; use a rolagem horizontal para consultar todas as semanas">
<svg class="curve-chart" style="min-width:<?= h($width) ?>px" viewBox="0 0 <?= h($width) ?> 450" role="img" aria-label="<?= $simulated?'SIMULAÇÃO — DADOS FICTÍCIOS: programado, realizado simulado e tendência simulada':'Curva S: programado, realizado e tendência estimada' ?>">
<?php if($simulated): ?><text x="64" y="22" font-size="12" font-weight="700" fill="#86601e">SIMULAÇÃO — DADOS FICTÍCIOS</text><?php endif; ?>
<?php foreach([0,20,40,60,80,100] as $value): ?><line x1="64" x2="<?= $width-44 ?>" y1="<?= $y($value) ?>" y2="<?= $y($value) ?>" stroke="#e3e8ec"/><text x="12" y="<?= $y($value)+4 ?>" font-size="12" fill="#70828d"><?= $value ?>%</text><?php endforeach; ?>
<?php foreach($colors as $key=>$color): ?><path data-series="<?= h($key) ?>" d="<?= h($path($key)) ?>" fill="none" stroke="<?= h($color) ?>" stroke-width="3" <?= $key==='tendencia'?'stroke-dasharray="8 5"':'' ?>/><?php endforeach; ?>
<?php foreach(['realizado','programado','tendencia'] as $key): foreach($points as $i=>$p): if($p[$key]===null)continue;$cx=$x($p);$cy=$y($p[$key]);$id='curve-label-'.$key.'-'.$i; ?>
<circle class="curve-point" data-point-series="<?= h($key) ?>" data-value="<?= h($p[$key]) ?>" tabindex="0" cx="<?= h($cx) ?>" cy="<?= h($cy) ?>" r="3.5" fill="<?= h($colors[$key]) ?>"><title><?= h($names[$key].' · Semana '.$p['semana'].' · '.$date($p['inicio']).' a '.$date($p['fim']).' · '.$format($p[$key])) ?></title></circle>
<line id="<?= h($id) ?>" class="curve-label-connector" x1="<?= h($cx) ?>" y1="<?= h($cy) ?>" x2="<?= h($cx) ?>" y2="<?= h($cy-14) ?>" stroke="<?= h($colors[$key]) ?>" visibility="hidden"/>
<text class="curve-point-label" data-point-label data-point-x="<?= h($cx) ?>" data-point-y="<?= h($cy) ?>" data-connector="<?= h($id) ?>" x="<?= h($cx) ?>" y="<?= h($cy-14) ?>" text-anchor="middle" fill="<?= h($colors[$key]) ?>"><?= h($short($p[$key])) ?></text>
<?php endforeach; endforeach; ?>
<?php foreach($points as $i=>$p): $cx=$x($p);$axisX=64+$i/max(1,count($points)-1)*($width-108); ?><g data-week="<?= h($p['semana']) ?>"><title><?= h('Semana '.$p['semana'].' · '.$date($p['inicio']).' a '.$date($p['fim']).' · análise '.$date($p['referencia'])) ?></title><line x1="<?= h($cx) ?>" x2="<?= h($axisX) ?>" y1="335" y2="363" stroke="#b8c7d0"/><text x="<?= h($axisX) ?>" y="382" text-anchor="middle" class="curve-axis-week">Semana <?= h($p['semana']) ?></text><text x="<?= h($axisX) ?>" y="399" text-anchor="middle" class="curve-axis-date"><?= h((new DateTimeImmutable($p['inicio']))->format('d/m/y')) ?></text><text x="<?= h($axisX) ?>" y="414" text-anchor="middle" class="curve-axis-date">a <?= h((new DateTimeImmutable($p['fim']))->format('d/m/y')) ?></text></g><?php endforeach; ?>
</svg></div>
<p class="curve-scroll-hint">Todas as semanas estão disponíveis. Deslize o gráfico na horizontal em telas menores; passe o mouse sobre os pontos para ver os valores.</p>
<div class="curve-legend"><span><i class="curve-swatch planned"></i>Programado</span><span><i class="curve-swatch actual"></i><?= $simulated?'Realizado simulado':'Realizado' ?></span><span><i class="curve-swatch trend"></i><?= $simulated?'Tendência simulada (estimativa)':'Tendência (estimativa)' ?></span></div>
<div class="curve-history"><h3><?= $simulated?'Semanas simuladas — dados fictícios':'Histórico semanal' ?></h3><div class="table-responsive"><table class="table"><thead><tr><th>Semana</th><th>Período</th><th>Programado</th><th><?= $simulated?'Realizado simulado':'Realizado' ?></th><th><?= $simulated?'Diferença simulada':'Diferença' ?></th></tr></thead><tbody>
<?php foreach($points as $p): ?><tr><td>Semana <?= h($p['semana']) ?><small class="d-block text-secondary">Análise <?= h($date($p['referencia'])) ?></small></td><td><?= h($date($p['inicio']).' a '.$date($p['fim'])) ?></td><td><?= h($format($p['versao_id']?$p['programado_apurado']:$p['programado'])) ?><?php if($p['origem_programado_apurado']==='legado'): ?><small class="d-block text-secondary">Fechamento legado</small><?php endif; ?></td><td><?php if($simulated): ?><?= h($format($p['realizado'])) ?><small class="d-block text-secondary">Dado fictício</small><?php elseif(!$p['versao_id']): ?><span class="text-secondary">Aguardando apuração</span><?php else: ?><?= h($format($p['realizado'])) ?><small class="d-block text-secondary"><?= h($p['quantidade']??0) ?> O.S. na apuração</small><?php endif; ?></td><td><?= $p['diferenca']===null?'—':h(($p['diferenca']>0?'+':'').number_format($p['diferenca'],2,',','.').' p.p.') ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<details class="curve-method"><summary>Como funciona o cálculo</summary>
<p>Percentuais são arredondados somente na exibição. As posições das linhas e as diferenças em pontos percentuais usam os valores originais, sem arredondamento prévio.</p>
<?php if($simulated): ?><p><strong>SIMULAÇÃO — DADOS FICTÍCIOS.</strong> O realizado é gerado em memória e não representa lançamentos nem fechamentos. A tendência reproduz projeções de uma semana à frente: usa a mesma média móvel do sistema, somente com as semanas simuladas anteriores, a partir de três apurações. A barra de dias e o programado continuam usando o calendário real. As diferenças desta visualização comparam o realizado fictício ao programado atual.</p><?php else: ?>
<p>O realizado utiliza somente os registros apurados; semanas sem fechamento aguardam apuração.</p>
<?php endif; ?>
<p>Programado automático — modelo histórico: referência estimada, não um cronograma aprovado pelos setores. Não utiliza pesos, quantidades planejadas ou metas semanais manuais.</p>
<p>Fechamento às terças-feiras · terça a segunda · horário de Brasília · média simples, todas as O.S. com peso igual. Sem lançamento: 0%.</p>
<p><?= h($weekly['days']['regra']??$weekly['days']['motivo']) ?>. A barra de dias usa o mesmo calendário operacional do programado, até a data atual. Os indicadores e diferenças usam o corte semanal, preservando o histórico.</p>
<p>Tendência: média móvel das três últimas variações semanais disponíveis, com no mínimo duas variações (três apurações). Intervalos entre posições são normalizados para sete dias. Projeção limitada a 0%–100%, sem representar execução real.</p>
<p>A linha azul usa o calendário atual em todo o período. <?= $simulated?'Nesta simulação, tabela e indicadores são fictícios; os fechamentos reais permanecem preservados.':'A tabela e os indicadores preservam o programado gravado em cada fechamento, inclusive versões legadas; mudanças posteriores no calendário não reescrevem o histórico.' ?></p>
<p>A área da curva vem da classificação principal do caderno ou da O.S. A equipe executante e suas permissões continuam definidas pelo TOTVS.</p>
</details>
<?php if($currentUser->perfil==='ADMIN_PCM'): ?><div class="curve-actions"><?= $this->Html->link('Calendário e apuração semanal',['action'=>'programacaoSemanal','?'=>['area_id'=>$area]],['class'=>'btn btn-outline-primary btn-sm']) ?></div><?php endif; ?>
</section>
