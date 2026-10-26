<?php
$url=fn(int $number)=>['controller'=>'FilaPcm','action'=>'view',$activity->id,'?'=>[$queryKey=>$number]+$this->request->getQueryParams(),'#'=>$anchor];
?>
<nav class="d-flex flex-wrap gap-3 align-items-center mt-3" aria-label="Paginação de <?= h($anchor) ?>">
<span class="small text-secondary"><?= h($total) ?> registros · Página <?= h($page) ?> de <?= h($pages) ?></span>
<?php if($page>1): ?><?= $this->Html->link('Anterior',$url($page-1)) ?><?php endif; ?>
<?php if($page<$pages): ?><?= $this->Html->link('Próxima',$url($page+1)) ?><?php endif; ?>
</nav>
