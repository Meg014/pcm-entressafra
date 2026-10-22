<?php
declare(strict_types=1);
namespace App\Service;

final class SetoresApresentacao
{
    public function configuration(int $season,int $area): array
    {
        PeriodoContexto::season($season);$s=Access::table('Entressafras')->get($season);$a=Access::table('AreasFabrica')->get($area);
        $calendar=Access::table('CalendariosOperacionais')->find()->where(['entressafra_id'=>$season,'area_fabrica_id'=>$area])->first();
        $end=$calendar?->data_fim?->format('Y-m-d');
        if(!$end) {
            $end=$s->prazo_padrao?->format('Y-m-d')??$s->data_fim->format('Y-m-d');
            if($s->nome==='2026/2027')$end=mb_strtolower(trim($a->nome))==='moenda'?'2027-04-10':'2027-03-26';
        }
        $ids=Access::table('CadernosAreas')->find()->where(['entressafra_id'=>$season,'area_id'=>$area])->all()->extract('caderno_id')->toList();
        return ['data_inicio'=>$calendar?->data_inicio?->format('Y-m-d')??'','data_fim'=>$end,'cadernos'=>array_map('intval',$ids),'calendario'=>$calendar];
    }
    public function save(int $userId,int $season,int $area,array $input): void
    {
        $user=Access::actor($userId);Access::pcm($user);PeriodoContexto::writable($season);Access::table('AreasFabrica')->get($area);
        $ids=$input['cadernos']??[];if(!is_array($ids)||count($ids)>2000)throw new \DomainException('Seleção de cadernos inválida.');
        foreach($ids as $id)if(!is_scalar($id)||!ctype_digit((string)$id)||(int)$id<1)throw new \DomainException('Caderno inválido.');
        $ids=array_values(array_unique(array_map('intval',$ids)));sort($ids);
        if($ids&&Access::table('Cadernos')->find()->where(['id IN'=>$ids,'entressafra_id'=>$season])->count()!==count($ids))throw new \DomainException('Caderno de outra entressafra.');
        $db=Access::table('CadernosAreas')->getConnection();
        $db->transactional(function()use($db,$user,$season,$area,$ids,$input){
            if($db->getDriver() instanceof \Cake\Database\Driver\Postgres)$db->execute('SELECT id FROM entressafras WHERE id=? FOR UPDATE',[$season]);
            $before=$this->configuration($season,$area);$removed=array_values(array_diff($before['cadernos'],$ids));
            if($removed&&Access::table('Cadernos')->find()->where(['id IN'=>$removed,'area_curva_id'=>$area])->count())throw new \DomainException('Este setor é a classificação principal de um caderno removido. Confira e altere a classificação principal antes de retirar a associação.');
            $start=$input['data_inicio']??'';$end=$input['data_fim']??'';
            if(!is_string($start)||!is_string($end))throw new \DomainException('Datas inválidas.');
            (new Calendarios())->save((int)$user->id,$season,['area_fabrica_id'=>$area,'data_inicio'=>$start,'data_fim'=>$end,'inicio_confirmado'=>trim($start)===''?'0':'1']);
            $links=Access::table('CadernosAreas');
            if($removed)$links->deleteAll(['entressafra_id'=>$season,'area_id'=>$area,'caderno_id IN'=>$removed]);
            foreach(array_diff($ids,$before['cadernos']) as $id)$links->saveOrFail(Access::entity($links,['caderno_id'=>$id,'area_id'=>$area,'entressafra_id'=>$season]));
            Access::audit($user,'setor_apresentacao_configurado',null,['area_id'=>$area,'entressafra_id'=>$season,'data_inicio'=>$before['data_inicio'],'data_fim'=>$before['data_fim'],'cadernos'=>$before['cadernos']],['area_id'=>$area,'entressafra_id'=>$season,'data_inicio'=>$start,'data_fim'=>$end,'cadernos'=>$ids],'Datas e associações conferidas no cadastro do setor. Classificação principal e equipe executante preservadas.');
        });
    }
}
