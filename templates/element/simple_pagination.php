<?php
// Query-only URL arrays lose positional route arguments (notebook/activity id).
$paginationRoute=['controller'=>$this->request->getParam('controller'),'action'=>$this->request->getParam('action')];
$paginationRoute=array_merge($paginationRoute,(array)$this->request->getParam('pass',[]));
$paginationUrl=fn(int $number)=>$paginationRoute+['?'=>['pagina'=>$number]+$this->request->getQueryParams()];
?>
<nav class="d-flex flex-wrap gap-3 align-items-center mt-3" aria-label="Paginação">
<span>Página <?= h($page) ?> de <?= h($pages) ?> · 10 registros por página</span>
<?php if($page>1): ?><?= $this->Html->link('Anterior',$paginationUrl($page-1)) ?><?php endif; ?>
<?php if($page<$pages): ?><?= $this->Html->link('Próxima',$paginationUrl($page+1)) ?><?php endif; ?>
</nav>
