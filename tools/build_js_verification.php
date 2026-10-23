<?php
declare(strict_types=1);
$root=dirname(__DIR__);$scripts=['employee'=>file_get_contents($root.'/webroot/js/employee-autocomplete.js'),'order'=>file_get_contents($root.'/webroot/js/order-autocomplete.js'),'progress'=>file_get_contents($root.'/webroot/js/porcentagem-rapida.js'),'evolution'=>file_get_contents($root.'/webroot/js/evolucao.js')];
$html='<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Verificação JavaScript isolada</title><body><pre id="result">Executando</pre><script>';
foreach(['test_autocomplete_js.js','test_porcentagem_rapida_js.js','test_evolucao_js.js','test_order_selection_race_js.js','test_lista_os_js.js','test_bulk_links_js.js','test_os_sem_caderno_js.js','test_porcentagens_lote_js.js'] as $file)$html.=file_get_contents(__DIR__.'/'.$file)."\n";
$scripts['lista']=file_get_contents($root.'/webroot/js/lista-os.js');
$scripts['bulk']=file_get_contents($root.'/webroot/js/bulk-links.js');
$scripts['percentBatch']=file_get_contents($root.'/webroot/js/porcentagens-lote.js');
$scripts['pending']=file_get_contents($root.'/webroot/js/os-sem-caderno.js');
$html.='const sources='.json_encode($scripts,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR).';';
$html.= <<<'JS'
(async()=>{try{const cases=[testListaOs(sources.lista)];cases.push(testPorcentagemRapida(sources.progress));cases.push(await testAutocomplete(sources.employee,sources.order));cases.push(await testAutocomplete(sources.employee,sources.order,true));cases.push(testEvolucao(sources.evolution));cases.push(await testOrderSelectionRace(sources.order));cases.push(await testBulkLinks(sources.bulk));cases.push(await testOsSemCaderno(sources.pending));cases.push(await testPorcentagensLote(sources.percentBatch));document.getElementById('result').textContent=JSON.stringify({status:'OK',cases});}catch(error){document.getElementById('result').textContent=JSON.stringify({status:'FAIL',error:String(error)});}})();
JS;
$html.='</script></body></html>';
file_put_contents($root.'/tmp/curva-js-verification.html',$html);echo 'Harness JavaScript isolado preparado em tmp/curva-js-verification.html.',PHP_EOL;
