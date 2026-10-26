<?php
declare(strict_types=1);
namespace App\Service;

final class OrderCompletion
{
    public const MESSAGE = 'Os apontamentos só podem ser lançados quando a O.S. estiver com 100% de evolução.';

    public static function status(int $activityId): array
    {
        $today=(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        $percentage=(new Evolucao())->state($activityId,$today)['percentual'];
        return ['percentual_atual'=>$percentage,'liberada_apontamento'=>$percentage===100.0];
    }
}
