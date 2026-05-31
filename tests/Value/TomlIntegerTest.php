<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Value;

use PhpCollective\Toml\Ast\Value\IntegerBase;
use PhpCollective\Toml\Value\TomlInteger;
use PHPUnit\Framework\TestCase;

final class TomlIntegerTest extends TestCase
{
    public function testDefaultsToDecimalLiteral(): void
    {
        $this->assertSame('255', (new TomlInteger(255))->toTomlLiteral());
    }

    public function testHexadecimalLiteral(): void
    {
        $this->assertSame('0xFF', (new TomlInteger(255, IntegerBase::Hexadecimal))->toTomlLiteral());
        $this->assertSame('0x0', (new TomlInteger(0, IntegerBase::Hexadecimal))->toTomlLiteral());
    }

    public function testOctalLiteral(): void
    {
        $this->assertSame('0o755', (new TomlInteger(493, IntegerBase::Octal))->toTomlLiteral());
    }

    public function testBinaryLiteral(): void
    {
        $this->assertSame('0b1010', (new TomlInteger(10, IntegerBase::Binary))->toTomlLiteral());
    }

    public function testNegativeValueFallsBackToDecimal(): void
    {
        // TOML hex/octal/binary literals are unsigned; negatives stay decimal.
        $this->assertSame('-255', (new TomlInteger(-255, IntegerBase::Hexadecimal))->toTomlLiteral());
    }
}
