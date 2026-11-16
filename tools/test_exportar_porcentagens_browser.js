// Run against an isolated, authenticated HTTP fixture; never seeds operational data.
const {chromium}=require(process.env.PCM_PLAYWRIGHT_PATH||'../tmp/export-download-browser/node_modules/playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');

(async()=>{
 const browser=await chromium.launch({executablePath:process.env.PCM_CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 try{
  const context=await browser.newContext({acceptDownloads:true,viewport:{width:1440,height:1000}});
  const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
  const base=process.env.PCM_EXPORT_TEST_URL||'http://127.0.0.1:8879/pcm-entressafra';
  await page.goto(base+'/lancar-porcentagem');
  const area=await page.locator('#area-id option').evaluateAll(options=>(options.find(o=>o.textContent==='MOENDA')||options.find(o=>o.value))?.value);
  assert.ok(area,'Setor disponível');
  await page.goto(base+'/lancar-porcentagem?area_id='+area+'&pagina=2');
  const button=page.locator('#porcentagens-exportar');await button.waitFor();
  const endpoint=new URL(await button.getAttribute('data-export-url'),page.url());
  assert.equal(endpoint.pathname,'/pcm-entressafra/exportar-porcentagens');
  assert.ok(endpoint.searchParams.get('entressafra_id'));
  const before=page.url();
  // Unsaved edits must neither be saved nor block the download via beforeunload.
  const input=page.locator('[data-percentage]').first();await input.fill('17');await input.dispatchEvent('input');
  const draftBefore=await page.evaluate(()=>JSON.stringify(sessionStorage));
  const responsePromise=page.waitForResponse(r=>r.url().includes('/exportar-porcentagens?'));
  const downloadPromise=page.waitForEvent('download');await button.click();
  const response=await responsePromise;const download=await downloadPromise;
  assert.equal(response.status(),200);
  assert.equal(response.headers()['content-type'],'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  assert.match(response.headers()['content-disposition'],/attachment;.*\.xlsx/);
  const requested=new URL(response.url());assert.equal(requested.searchParams.get('area_id'),area);
  assert.equal(requested.searchParams.get('entressafra_id'),endpoint.searchParams.get('entressafra_id'));
  assert.equal(requested.searchParams.has('pagina'),false);assert.equal(requested.searchParams.has('os'),false);
  assert.equal(await download.failure(),null);
  const target=path.resolve(__dirname,'../tmp/export-download-browser.xlsx');await download.saveAs(target);
  assert.equal(fs.readFileSync(target).subarray(0,2).toString(),'PK');
  assert.equal(page.url(),before);assert.equal(await page.evaluate(()=>JSON.stringify(sessionStorage)),draftBefore);
  await page.route('**/exportar-porcentagens?**',route=>route.fulfill({status:503,contentType:'text/plain',body:'Exportação indisponível: falta a coluna atividades.tipo_planejamento. Migration 20261106100000_AddTipoPlanejamento.php.'}));
  await button.click();await page.locator('#porcentagens-exportar-erro').waitFor({state:'visible'});
  assert.match(await page.locator('#porcentagens-exportar-erro').textContent(),/20261106100000_AddTipoPlanejamento/);
  await page.unroute('**/exportar-porcentagens?**');
  await page.route('**/exportar-porcentagens?**',route=>route.fulfill({status:200,contentType:'text/html',body:'<html>Login</html>'}));
  await button.click();await page.waitForFunction(()=>!document.querySelector('#porcentagens-exportar').disabled);
  assert.match(await page.locator('#porcentagens-exportar-erro').textContent(),/sessão/);
  assert.equal(page.url(),before);assert.deepEqual(errors,[]);
  console.log(JSON.stringify({status:'OK',prefix:'/pcm-entressafra/',download:download.suggestedFilename(),http:response.status(),draftPreserved:true,pageUnchanged:true,plainErrorVisible:true,htmlRejected:true,consoleErrors:errors},null,2));
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
