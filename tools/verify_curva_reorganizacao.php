<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';require dirname(__DIR__).'/config/bootstrap.php';
use App\Service\{Access,ListLinks,ClassificacaoCurva,Curva,Evolucao};
use App\Integration\Totvs\{ListOrders,Orders,Gateway};
$db=Access::table('Atividades')->getConnection();
if($db->execute('SELECT current_database() AS db')->fetch('assoc')['db']!=='pcm_controle_entressafra')exit(2);
$tables=['entressafras','setores','cadernos','cadernos_setores','atividades','apontamentos','evolucao_semanas','evolucao_registros','evolucao_lotes','evolucao_versoes','evolucao_pesos','evolucao_metas','auditorias','areas_fabrica','calendarios_operacionais','vinculos_lotes','vinculos_itens'];
$fingerprint=function()use($db,$tables){$out=[];foreach($tables as $table){$rows=array_map(fn($r)=>json_encode($r,JSON_THROW_ON_ERROR),$db->execute('SELECT * FROM '.$table)->fetchAll('assoc'));sort($rows);$out[$table]=hash('sha256',implode("\n",$rows));}return $out;};
$before=$fingerprint();$stage='preparação';$failed=false;$db->begin();
try {
    $tag='__curva_'.bin2hex(random_bytes(6));$stamp=gmdate('Y-m-d H:i:s');$t=Access::table('Usuarios');$user=$t->saveOrFail(Access::entity($t,['nome'=>$tag,'login'=>$tag,'perfil'=>'ADMIN_PCM','ativo'=>true,'senha_hash'=>password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT),'created'=>$stamp,'modified'=>$stamp]));
    $t=Access::table('Entressafras');$season=$t->saveOrFail($t->newEntity(['nome'=>$tag,'data_inicio'=>'2026-10-05','data_fim'=>'2027-04-10','status'=>'planejamento']));
    $electric=Access::table('Setores')->find()->where(['area_totvs'=>'ELETRI','filial_totvs'=>'01'])->firstOrFail();
    $t=Access::table('Cadernos');$book=$t->saveOrFail($t->newEntity(['nome'=>$tag,'entressafra_id'=>$season->id,'setor_id'=>$electric->id,'ativo'=>true]));
    $stage='SELECT real em blocos';$gateway=new Gateway();$mechanical=$gateway->orders('00','MECANI','01')[0]??null;
    if(!$mechanical)throw new RuntimeException();
    $s=new ListLinks(new ListOrders());$numbers='005300 '.$mechanical['numero'].' 005300';$token=$s->create((int)$user->id,(int)$book->id,$numbers);$s->prepare((int)$user->id,$token);$preview=$s->summary((int)$user->id,$token);
    if($preview['filters']['repetidas']!==1||($preview['counts']['elegivel']??0)!==2)throw new RuntimeException();
    $stage='vínculos sem duplicidade e equipes independentes';$s->select((int)$user->id,$token,[],true);$s->start((int)$user->id,$token,2,'Teste isolado em transação revertida.');$s->process((int)$user->id,$token);$s->process((int)$user->id,$token);
    if(($s->summary((int)$user->id,$token)['counts']['sucesso']??0)!==2)throw new RuntimeException();
    $area=Access::table('AreasFabrica')->find()->where(['nome'=>'Moenda'])->firstOrFail();$classification=new ClassificacaoCurva();$classification->apply((int)$user->id,$classification->preview((int)$user->id,(int)$season->id,'caderno',[(int)$book->id],(int)$area->id,'Conferência isolada em rollback.'));
    $stage='histórico, média e pesos pendentes';$e=new Evolucao();$activities=Access::table('Atividades')->find()->where(['entressafra_id'=>$season->id])->all();
    foreach($activities as $a){$electrical=$a->area_executante_totvs==='ELETRI';foreach(['2026-10-12'=>$electrical?30:60,'2026-10-19'=>$electrical?40:80] as $reference=>$value)$e->saveBatch((int)$user->id,(int)$season->id,$reference,[['atividade_id'=>(int)$a->id,'percentual'=>$value,'revisao'=>0]],bin2hex(random_bytes(16)));}
    $report=(new Curva())->report((int)$season->id,(string)$area->id);if($report['metrics']['total']!==2||$report['metrics']['simples']!==60.0||$report['official']!==null)throw new RuntimeException();
    $points=array_column($report['points'],'simples','referencia');if($points['2026-10-12']!==45.0||$points['2026-10-19']!==60.0)throw new RuntimeException();
    $stage='simulação sem escrita';$inside=$fingerprint();$demo=(new Curva())->report((int)$season->id,demo:true);if(array_column($demo['points'],'simples')!==[30.0,40.0]||$inside!==$fingerprint())throw new RuntimeException();
}catch(Throwable){$failed=true;}finally{$db->rollback();}
if($before!==$fingerprint()){$failed=true;$stage='preservação de dados';}
if($failed){fwrite(STDERR,'Falha em '.$stage.'; detalhes sensíveis omitidos.'.PHP_EOL);exit(1);}
echo 'OK PostgreSQL/TOTVS: lista parametrizada real; repetidas; equipes Mecânica e Elétrica na curva Moenda; médias 45% → 60%; pesos pendentes; demonstração 30% → 40% sem escrita; rollback e hashes de 17 tabelas preservados.',PHP_EOL;
