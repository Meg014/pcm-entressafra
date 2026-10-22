<?php
declare(strict_types=1);
namespace App\Test\TestCase\Service;
use App\Service\PasswordPolicy;
use PHPUnit\Framework\TestCase;
class PasswordPolicyTest extends TestCase
{
    public function testCharacterMinimumAndByteMaximum(): void
    {
        $generated = bin2hex(random_bytes(40));
        $this->assertFalse(PasswordPolicy::accepts(''));
        $this->assertFalse(PasswordPolicy::accepts(substr($generated, 0, 5)));
        $this->assertTrue(PasswordPolicy::accepts(substr($generated, 0, 6)));
        $this->assertTrue(PasswordPolicy::accepts(substr($generated, 0, 72)));
        $this->assertFalse(PasswordPolicy::accepts(substr($generated, 0, 73)));
        $character = mb_chr(random_int(0x4100, 0x4200), 'UTF-8');
        $this->assertFalse(PasswordPolicy::accepts(str_repeat($character, 3)));
        $this->assertTrue(PasswordPolicy::accepts(str_repeat($character, 6)));
        $this->assertFalse(PasswordPolicy::accepts(substr($generated, 0, 6) . "\0"));
    }
}
