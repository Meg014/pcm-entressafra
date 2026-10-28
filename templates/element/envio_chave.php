<?php
// Preserve a valid form identity across validation errors, never render submitted arrays.
$submissionKey=$this->request->getData('envio_chave');
if(!is_string($submissionKey)||!preg_match('/^[a-f0-9]{32}$/D',$submissionKey))$submissionKey=bin2hex(random_bytes(16));
?>
<?= $this->Form->hidden('envio_chave',['value'=>$submissionKey]) ?>
