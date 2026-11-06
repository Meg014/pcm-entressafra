<?php
declare(strict_types=1);
namespace App\Service;

/** Single planning boundary shared by SQL, ORM and write validation. */
final class Planejamento
{
    public const PROGRAMADA = 'PROGRAMADA';
    public const CORRETIVA = 'CORRETIVA_NAO_PROGRAMADA';
    public const CODIGOS = ['CNPECA','CNPECD','CNPEDT','CNPEEL','CNPETR','CNPEIN','CNPELB','CNPEMC','CNPEMO'];

    public static function corretiva(array $source): bool
    {
        $name = mb_strtoupper(\Cake\Utility\Text::transliterate((string)($source['servico'] ?? '')), 'UTF-8');
        $name = preg_replace('/\s+/u', ' ', trim($name));
        return str_contains($name, 'CORRETIVA NAO PROGRAMADA ENTRESSAFRA')
            || in_array(strtoupper(trim((string)($source['codigo_servico'] ?? ''))), self::CODIGOS, true);
    }

    public static function sql(string $alias = 'a'): string
    {
        return $alias.".tipo_planejamento = '".self::PROGRAMADA."'";
    }

    public static function exigir($activity): void
    {
        if ($activity->tipo_planejamento === self::CORRETIVA) {
            throw new \DomainException('Corretiva não programada não possui porcentagem, caderno planejado ou Curva S.');
        }
    }
}
