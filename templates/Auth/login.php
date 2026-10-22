<?php $this->assign('title', 'Entrar'); ?>
<section class="panel mx-auto" style="max-width:460px"><p class="eyebrow">ACESSO AO PCM</p><h1>Entrar no sistema</h1><p class="text-secondary">Use sua conta do Controle de Entressafra.</p>
<?= $this->Form->create(null) ?>
<div class="mb-3"><?= $this->Form->control('login', ['label' => 'Login', 'class' => 'form-control', 'autocomplete' => 'username', 'maxlength' => 100, 'required' => true]) ?></div>
<div class="mb-4"><?= $this->Form->control('senha', ['label' => 'Senha', 'type' => 'password', 'class' => 'form-control', 'autocomplete' => 'current-password', 'required' => true, 'value' => '']) ?></div>
<?= $this->Form->button('Entrar', ['class' => 'btn btn-primary w-100']) ?><?= $this->Form->end() ?>
</section>
