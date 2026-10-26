<?php
// Reuse the existing accessible employee widget with unique IDs and field names.
ob_start();
echo $this->element('employee_autocomplete',['employeeSearchLabel'=>$value('funcionario_rotulo'),'selectedEmployee'=>$value('funcionario_chave')!==''?['chave'=>$value('funcionario_chave'),'nome'=>$value('funcionario_rotulo'),'matricula'=>'']:null]);
$widget=ob_get_clean();
$widget=str_replace('id="funcionario-', 'id="row-'.$index.'-funcionario-', $widget);
$widget=str_replace(['for="funcionario-','aria-controls="funcionario-','aria-describedby="funcionario-'],['for="row-'.$index.'-funcionario-','aria-controls="row-'.$index.'-funcionario-','aria-describedby="row-'.$index.'-funcionario-'],$widget);
$widget=str_replace(['name="funcionario_rotulo"','name="funcionario_chave"'],['name="'.($fieldGroup??'apontamentos').'['.$index.'][funcionario_rotulo]"','name="'.($fieldGroup??'apontamentos').'['.$index.'][funcionario_chave]"'],$widget);
$widget=str_replace('Pesquisar funcionário por nome ou matrícula *','Funcionário *',$widget);
$widget=str_replace('Digite pelo menos 2 caracteres e selecione um funcionário NORMAL. O nome exibido vem de RA_NOME e pode estar abreviado no TOTVS.','Pesquise nome ou matrícula e selecione um funcionário NORMAL.',$widget);
$widget=str_replace('>Limpar</button>',' title="Limpar funcionário" aria-label="Limpar funcionário">×</button>',$widget);
echo $widget;
?>