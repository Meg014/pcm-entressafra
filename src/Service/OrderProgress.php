<?php
declare(strict_types=1);
namespace App\Service;

/** Informational evolution only; never authorizes or blocks hours or supplies. */
final class OrderProgress
{
    public static function status(int $activityId): array
    {
        $activity=Access::table('Atividades')->get($activityId);
        $season=Access::table('Entressafras')->get((int)$activity->entressafra_id);
        $reference=min((new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d'),$season->data_fim->format('Y-m-d'));
        return ['percentual_atual'=>(new Evolucao())->state($activityId,$reference)['percentual']];
    }
}
