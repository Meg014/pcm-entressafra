<?php
declare(strict_types=1);
namespace App\Service;

/** Informational evolution only; never authorizes or blocks hours or supplies. */
final class OrderProgress
{
    public static function status(int $activityId): array
    {
        $activity=Access::table('Atividades')->get($activityId);
        try { [$reference]=(new OperacaoRapida())->reference((int)$activity->entressafra_id); }
        catch (\DomainException) { return ['percentual_atual'=>0.0]; }
        return ['percentual_atual'=>(new Evolucao())->state($activityId,$reference)['percentual']];
    }
}
