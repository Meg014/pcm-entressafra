<?php
declare(strict_types=1);
namespace App\Service;

final class EntryPeriods
{
    public const FIELDS = ['inicio_manha', 'fim_manha', 'inicio_tarde', 'fim_tarde'];

    public static function intervals(array $input): array
    {
        if (!array_intersect(self::FIELDS, array_keys($input))) {
            return [TimeEntries::interval((string)($input['inicio'] ?? ''), (string)($input['fim'] ?? ''))];
        }
        $periods = []; $days = [];
        foreach (['manha', 'tarde'] as $period) {
            $start = $input['inicio_'.$period] ?? '';
            $end = $input['fim_'.$period] ?? '';
            if (($start === '' || $start === null) && ($end === '' || $end === null)) { continue; }
            if (!is_string($start) || !is_string($end) || !$start || !$end) {
                throw new \DomainException('Preencha início e fim juntos em cada período.');
            }
            $periods[] = TimeEntries::interval($start, $end);
            $days[] = substr($start, 0, 10);
        }
        if (!$periods) { throw new \DomainException('Preencha pelo menos um período completo.'); }
        if (count($periods) === 2 && ($days[0] !== $days[1] || $periods[0][1] > $periods[1][0])) {
            throw new \DomainException('Os períodos devem estar na mesma data, em ordem e sem sobreposição.');
        }
        return $periods;
    }

    public static function stored(object $row): array
    {
        $result = [];
        foreach (['manha', 'tarde'] as $period) {
            if ($row->{'inicio_'.$period}) { $result[$period] = [$row->{'inicio_'.$period}, $row->{'fim_'.$period}]; }
        }
        return $result ?: ['manha' => [$row->inicio, $row->fim]];
    }
}
