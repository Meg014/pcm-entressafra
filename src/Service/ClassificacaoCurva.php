<?php
declare(strict_types=1);
namespace App\Service;

final class ClassificacaoCurva
{
    public function preview(int $userId,int $season,string $type,array $ids,?int $area,string $reason): array
    {
        $user=Access::actor($userId);Access::pcm($user);
        Access::table('Entressafras')->get($season);
        if($area)Access::table('AreasFabrica')->get($area);
        if(!in_array($type,['caderno','atividades'],true)||!$ids||count($ids)>2000)throw new \DomainException('Selecione de 1 a 2.000 registros.');
        foreach($ids as $id)if(!is_scalar($id)||!ctype_digit((string)$id)||(int)$id<1)throw new \DomainException('Identificação inválida.');
        $ids=array_values(array_unique(array_map('intval',$ids)));sort($ids);
        $reason=trim($reason);if($reason===''||mb_strlen($reason)>3000)throw new \DomainException('Informe a justificativa administrativa.');
        $t=Access::table($type==='caderno'?'Cadernos':'Atividades');
        if($type==='caderno'&&count($ids)!==1)throw new \DomainException('Confira um caderno por vez.');
        $rows=$t->find()->where(['id IN'=>$ids,'entressafra_id'=>$season])->orderBy(['id'=>'ASC'])->disableHydration()->all()->toList();
        if(count($rows)!==count($ids))throw new \DomainException('Registro fora da entressafra.');
        $snapshot=[];
        foreach($rows as $row)$snapshot[]=['id'=>$row['id'],'area_curva_id'=>$row['area_curva_id'],'caderno_id'=>$row['caderno_id']??null,'modified'=>(string)($row['modified']??'')];
        $affected=$type==='caderno'?Access::table('Atividades')->find()->select(['id','area_curva_id'])->where(['caderno_id'=>$ids[0]])->orderBy(['id'=>'ASC'])->disableHydration()->all()->toList():[];
        $plan=compact('userId','season','type','ids','area','reason','snapshot','affected');
        $plan['signature']=hash_hmac('sha256',json_encode($plan,JSON_THROW_ON_ERROR),\Cake\Utility\Security::getSalt());
        $plan['rows']=array_map(fn($r)=>['id'=>$r['id'],'nome'=>$r['nome']??$r['os_numero'],'atual'=>$r['area_curva_id'],'caderno_id'=>$r['caderno_id']??null],$rows);
        return $plan;
    }
    public function apply(int $userId,array $plan): int
    {
        $user=Access::actor($userId);Access::pcm($user);
        if(($plan['userId']??null)!==$userId)throw new \DomainException('Prévia de outra conta.');
        $db=Access::table('Atividades')->getConnection();
        return $db->transactional(function()use($userId,$user,$plan,$db){
            if($db->getDriver() instanceof \Cake\Database\Driver\Postgres)$db->execute('SELECT id FROM entressafras WHERE id=? FOR UPDATE',[$plan['season']]);
            $fresh=$this->preview($userId,$plan['season'],$plan['type'],$plan['ids'],$plan['area'],$plan['reason']);
            if(!hash_equals($fresh['signature'],(string)($plan['signature']??'')))throw new \DomainException('Os cadastros mudaram. Prepare e confira uma nova prévia.');
            $t=Access::table($plan['type']==='caderno'?'Cadernos':'Atividades');
            foreach($fresh['snapshot'] as $row){$e=$t->get($row['id']);$before=$e->toArray();$e->area_curva_id=$plan['area'];$t->saveOrFail($e);Access::audit($user,'classificacao_principal_curva',null,$before,$e->toArray(),$plan['reason']);}
            if($plan['type']==='caderno'&&$plan['area']) {
                $links=Access::table('CadernosAreas');$where=['caderno_id'=>$plan['ids'][0],'area_id'=>$plan['area'],'entressafra_id'=>$plan['season']];
                if(!$links->exists($where))$links->saveOrFail(Access::entity($links,$where));
            }
            return count($fresh['snapshot']);
        });
    }
}
