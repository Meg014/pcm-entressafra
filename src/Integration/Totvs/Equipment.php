<?php
declare(strict_types=1);
namespace App\Integration\Totvs;

/** Exact branch lookup, using the existing SELECT-only connection. */
final class Equipment
{
    public function __construct(private ?\Closure $reader = null) {}

    public static function sql(): string
    {
        $slots = implode(',', array_map(static fn($i) => ':b'.$i, range(0, 99)));
        return "SELECT RTRIM(T9_CODBEM) AS codigo, RTRIM(T9_FILIAL) AS filial, RTRIM(T9_NOME) AS nome FROM DADOSP12.dbo.ST9010 WHERE D_E_L_E_T_ <> '*' AND T9_FILIAL = :filial AND T9_CODBEM IN ($slots)";
    }

    public function find(array $codes, string $branch): array
    {
        $result = [];
        foreach (array_chunk(array_values(array_unique($codes)), 100) as $chunk) {
            $params = ['filial'=>$branch];
            foreach (range(0, 99) as $i) $params['b'.$i] = $chunk[$i] ?? '';
            try {
                $rows = $this->reader ? ($this->reader)(self::sql(), $params) : \Cake\Datasource\ConnectionManager::get('protheus')->execute(self::sql(), $params)->fetchAll('assoc');
            } catch (\Throwable) {
                throw new \DomainException('Não foi possível consultar os equipamentos no TOTVS.');
            }
            $matches = [];
            foreach ($rows as $row) {
                $code = rtrim((string)$row['codigo']);
                if (rtrim((string)$row['filial']) !== $branch || !in_array($code, $chunk, true)) continue;
                $matches[$code][] = trim((string)$row['nome']);
            }
            foreach ($matches as $code=>$names) {
                if (count($names) !== 1 || $names[0] === '') continue;
                $result[$code] = mb_check_encoding($names[0], 'UTF-8') ? $names[0] : mb_convert_encoding($names[0], 'UTF-8', 'Windows-1252');
            }
        }
        return $result;
    }
}
