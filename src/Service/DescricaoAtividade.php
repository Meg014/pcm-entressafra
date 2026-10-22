<?php
declare(strict_types=1);
namespace App\Service;

final class DescricaoAtividade
{
    public function save(int $actor, int $id, string $description, string $source): void
    {
        $user=Access::actor($actor);Access::pcm($user);
        PeriodoContexto::activity($id);
        $table=Access::table('Atividades');
        $table->getConnection()->transactional(function()use($user,$table,$id,$description,$source){
            $activity=$table->get($id);Access::activity($user,$activity);
            PeriodoContexto::writable((int)$activity->entressafra_id);
            $description=trim($description);$source=trim($source);
            if($description===''||mb_strlen($description)>10000||$source===''||mb_strlen($source)>255)
                throw new \DomainException('Informe a descrição específica (até 10.000 caracteres) e sua fonte (até 255 caracteres).');
            $before=['atividade_id'=>$id,'descricao_atividade'=>$activity->descricao_atividade,'descricao_origem'=>$activity->descricao_origem];
            $activity->set('descricao_atividade',$description);$activity->set('descricao_origem',$source);
            $table->saveOrFail($activity);
            Access::audit($user,'descricao_atividade_atualizada',null,$before,['atividade_id'=>$id,'descricao_atividade'=>$description,'descricao_origem'=>$source],'Conferência administrativa da descrição específica da O.S.');
        });
    }
}
