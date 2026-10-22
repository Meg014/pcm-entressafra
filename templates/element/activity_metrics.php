<small class="d-block"><?= h($m['quantidade']??0) ?> apontamentos recebidos · <?= number_format(($m['minutos']??0)/60,2,',','.') ?> h válidas</small>
<small class="d-block">Pendentes: <?= number_format(($m['pendentes']??0)/60,2,',','.') ?> h · Tratadas: <?= number_format(($m['tratados']??0)/60,2,',','.') ?> h</small>
<small class="d-block">Último recebido: <?= !empty($m['recebido'])?h((new \DateTimeImmutable($m['recebido'],new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('America/Sao_Paulo'))->format('d/m/Y H:i')):'Nenhum' ?></small>
<small class="d-block">Execução física: <?= h($percentage) ?>%</small>
