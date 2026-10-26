<?php
declare(strict_types=1);
namespace App\Test\Support;
use App\Service\Access;

/** Explicit evolution fixture; only used by tests that need a completed order. */
final class CompletedOrder
{
    public static function set(int $id,int $actor,float $percentage=100): void
    {
        $activity=Access::table('Atividades')->get($id);$db=Access::table('Atividades')->getConnection();
        $season=Access::table('Entressafras')->get($activity->entressafra_id);
        $reference=$season->data_inicio->format('Y-m-d');
        $week=$db->execute('SELECT id FROM evolucao_semanas WHERE entressafra_id=? AND referencia=?',[$season->id,$reference])->fetch('assoc');
        if(!$week){$db->insert('evolucao_semanas',['entressafra_id'=>$season->id,'referencia'=>$reference]);$week=$db->execute('SELECT id FROM evolucao_semanas WHERE entressafra_id=? AND referencia=?',[$season->id,$reference])->fetch('assoc');}
        $db->execute('DELETE FROM evolucao_registros WHERE atividade_id=?',[$id]);
        $db->insert('evolucao_registros',['atividade_id'=>$id,'semana_id'=>$week['id'],'percentual'=>$percentage,'revisao'=>1,'usuario_id'=>$actor,'observacoes'=>'Fixture de conclusão','motivo'=>'Fixture de teste','created'=>gmdate('Y-m-d H:i:s')]);
    }
}
