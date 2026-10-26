param([string]$Browser='C:\Program Files\Google\Chrome\Application\chrome.exe')
$ErrorActionPreference='Stop'
$project=Split-Path $PSScriptRoot -Parent
$preview=Join-Path $project 'tmp/batch-preview.html'
if(!(Test-Path -LiteralPath $preview)){throw 'Gere a prévia: $env:BATCH_PREVIEW=1; php vendor/phpunit/phpunit/phpunit --filter testBatchFormAndSingleSubmission'}
$html=[IO.File]::ReadAllText($preview)
$webroot=([uri](Join-Path $project 'webroot/')).AbsoluteUri
$html=$html.Replace('src="/js/', 'src="'+$webroot+'js/').Replace('href="/css/', 'href="'+$webroot+'css/')
$mock=@"
<script>window.errors=[];window.addEventListener('error',e=>window.errors.push(e.message));window.fetch=async()=>({ok:true,redirected:false,json:async()=>({results:[{chave:'0001',nome:'Funcionário de teste',matricula:'0001',situacao:'NORMAL',ativo:true},{chave:'0002',nome:'Bloqueado',matricula:'0002',situacao:'FÉRIAS',ativo:false}]})});</script>
"@
$html=$html.Replace('</head>',$mock+'</head>')
$test=[IO.File]::ReadAllText((Join-Path $PSScriptRoot 'test_apontamentos_batch_browser.js'))
$html=$html.Replace('</body>','<script>'+$test+'</script></body>')
$page=Join-Path $project 'tmp/batch-browser-test.html'
[IO.File]::WriteAllText($page,$html,[Text.UTF8Encoding]::new($false))
foreach($size in @('1440,1000','820,1180','390,844')){
 $dom=& $Browser --headless --log-level=3 --disable-gpu --no-sandbox --allow-file-access-from-files "--user-data-dir=$project/tmp/chrome-batch-test" "--window-size=$size" --virtual-time-budget=3000 --dump-dom ([uri]$page).AbsoluteUri 2> (Join-Path $project 'tmp/chrome-batch-stderr.log') | Out-String
 $output=$dom -join "`n"
 $match=[regex]::Match($output,'<pre id="browser-test-results">(.*?)</pre>')
 if(!$match.Success){throw "Navegador não retornou resultado: $size"}
 $result=[System.Net.WebUtility]::HtmlDecode($match.Groups[1].Value)|ConvertFrom-Json
 if(($result.results -match '^FAIL:') -or $result.errors.Count){throw ($result|ConvertTo-Json -Depth 5)}
 Write-Output "$size : $($result.results.Count) verificações OK"
}