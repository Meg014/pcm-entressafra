<?php
$selectedKey = $selectedEmployee['chave'] ?? '';
$selectedLabel = $selectedEmployee['nome'] ?? $this->request->getData('funcionario_rotulo', '');
if (!is_string($selectedKey)) { $selectedKey = ''; }
if (!is_string($selectedLabel)) { $selectedLabel = ''; }
?>
<div id="funcionario-autocomplete" class="mb-4 position-relative" data-lookup="<?= h($this->Url->build(['controller'=>'Apontamentos','action'=>'funcionarios'])) ?>">
    <label for="funcionario-pesquisa" class="form-label">Pesquisar funcionário por nome ou matrícula *</label>
    <div class="d-flex gap-2"><input id="funcionario-pesquisa" name="funcionario_rotulo" class="form-control" type="text" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="funcionario-resultados" aria-describedby="funcionario-aviso" autocomplete="off" value="<?= h($selectedLabel) ?>" required>
    <button type="button" id="funcionario-limpar" class="btn btn-outline-secondary">Limpar</button></div>
    <?= $this->Form->hidden('funcionario_chave', ['id'=>'funcionario-chave','value'=>$selectedKey]) ?>
    <div id="funcionario-resultados" role="listbox" aria-label="Funcionários encontrados" class="border rounded bg-white mt-1 overflow-auto" style="max-height:300px" hidden></div>
    <p id="funcionario-aviso" role="status" class="small text-secondary mt-2">Digite pelo menos 2 caracteres e selecione um funcionário NORMAL. O nome exibido vem de RA_NOME e pode estar abreviado no TOTVS.</p>
    <div id="funcionario-resumo" class="border-bottom py-2" role="status" aria-live="polite" <?= $selectedEmployee ? '' : 'hidden' ?>>
        <div class="d-flex align-items-center justify-content-between gap-2">
            <div><strong id="funcionario-resumo-nome"><?= h($selectedEmployee['nome'] ?? '') ?></strong><small id="funcionario-resumo-matricula" class="d-block text-secondary"><?= $selectedEmployee ? h('Matrícula '.$selectedEmployee['matricula']) : '' ?></small></div>
            <span class="badge bg-success">✓ Selecionado</span>
        </div>
    </div>
    <noscript><p class="text-danger">Ative o JavaScript para pesquisar e selecionar o funcionário confirmado no TOTVS.</p></noscript>
</div>
<?= $this->Html->script('employee-autocomplete.js?v='.filemtime(WWW_ROOT.'js/employee-autocomplete.js'), ['block'=>true]) ?>
