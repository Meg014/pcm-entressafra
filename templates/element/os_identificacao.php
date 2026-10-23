<div class="os-identificacao">
<strong>O.S. <?= h($order['numero']) ?></strong>
<div>Descrição: <?= h($order['descricao']??'Descrição não informada') ?></div>
<div><?= h($order['rotulo_setor']??'Setor') ?>: <?= h($order['setor_apresentacao']??'Não definido') ?></div>
</div>
