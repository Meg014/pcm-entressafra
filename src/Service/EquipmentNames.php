<?php
declare(strict_types=1);
namespace App\Service;

use App\Integration\Totvs\Equipment;

/** Presentation only: keys and equipment codes are never persisted or replaced. */
final class EquipmentNames
{
    public function __construct(private ?Equipment $source = null) {}

    public function resolve(array $items): array
    {
        $names = $groups = [];
        foreach ($items as $key=>$item) {
            $code = trim((string)$item['bem']);
            $branch = trim((string)$item['filial']);
            $names[$key] = $code;
            $snapshot = $item['snapshot'] ?? [];
            foreach ([$snapshot['ultima_sincronizacao'] ?? [], $snapshot] as $data) {
                if (trim((string)($data['bem'] ?? $data['T9_CODBEM'] ?? '')) !== $code ||
                    trim((string)($data['filial'] ?? $data['T9_FILIAL'] ?? '')) !== $branch) continue;
                $name = trim((string)($data['bem_nome'] ?? $data['T9_NOME'] ?? ''));
                if ($name !== '') { $names[$key] = $name; break; }
            }
            if ($names[$key] === $code && $code !== '') $groups[$branch][$code][] = $key;
        }
        foreach ($groups as $branch=>$codes) {
            try { $found = ($this->source ?? new Equipment())->find(array_map('strval', array_keys($codes)), (string)$branch); }
            catch (\DomainException) { continue; }
            foreach ($found as $code=>$name) foreach ($codes[$code] as $key) $names[$key] = $name;
        }
        return $names;
    }
}
