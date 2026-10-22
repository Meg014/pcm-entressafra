<?php
declare(strict_types=1);
namespace App\Service;
final class PasswordPolicy
{
    public const MESSAGE = 'A senha deve ter pelo menos 6 caracteres e no máximo 72 bytes.';
    public static function accepts(string $password): bool
    {
        return mb_check_encoding($password, 'UTF-8')
            && mb_strlen($password, 'UTF-8') >= 6
            && strlen($password) <= 72
            && !str_contains($password, "\0");
    }
}
