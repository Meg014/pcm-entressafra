<div class="os-identificacao">
<strong>O.S. <?= h($order['numero']) ?></strong><?php if(($order['tipo_planejamento']??'')===\App\Service\Planejamento::CORRETIVA): ?> <small class="badge bg-light text-dark">Corretiva não programada</small><?php endif; ?>
<div>Descrição: <?= h($order['descricao']??'Descrição não informada') ?></div>
<div><?= h($order['rotulo_setor']??'Setor') ?>: <?= h($order['setor_apresentacao']??'Não definido') ?></div>
</div>
