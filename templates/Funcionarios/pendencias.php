<?php $this->assign('title','Pendências de identificação'); ?>
<h1>Pendências de identificação de funcionários</h1>
<p class="text-secondary">Somente matrículas NORMAL duplicadas ou vazias na família 010. Os demais funcionários não são bloqueados por estas pendências.</p>
<section class="panel mt-4">
<div class="table-responsive"><table class="table"><thead><tr><th>Matrícula</th><th>Registros NORMAL</th><th>Pendência</th></tr></thead><tbody>
<?php foreach($groups as $group): ?><tr><td><?= h($group['matricula'] ?: '(vazia)') ?></td><td><?= h($group['quantidade']) ?></td><td><?= h(trim((string)$group['matricula'])===''?'Matrícula ausente. Solicite a conferência do PCM.':\App\Integration\Totvs\Employees::AMBIGUOUS) ?></td></tr><?php endforeach; ?>
<?php if(!$groups): ?><tr><td colspan="3"><?= $unavailable?'Consulta indisponível. Tente novamente.':'Nenhuma pendência encontrada.' ?></td></tr><?php endif; ?>
</tbody></table></div>
<?php if($hasMore): ?><p class="text-secondary">Exibindo as primeiras 50 pendências. A validação individual consulta toda a fonte, independentemente deste limite.</p><?php endif; ?>
<p class="small text-secondary mb-0">Consulta somente leitura. Nenhum registro é unido, alterado ou excluído. Nomes e outros dados pessoais não são exibidos nesta lista.</p>
</section>
