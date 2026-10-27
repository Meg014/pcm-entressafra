<?php foreach (\App\Service\EntryPeriods::stored($entry) as $period=>[$from,$to]): ?>
<div><?= $period==='manha'?'Manhã':'Tarde' ?>: <?= h($from->setTimezone('America/Sao_Paulo')->format('H:i')) ?> às <?= h($to->setTimezone('America/Sao_Paulo')->format('H:i')) ?></div>
<?php endforeach; ?>
