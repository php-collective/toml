<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Parser;

use PhpCollective\Toml\Toml;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IntegerRangeTest extends TestCase
{
    #[DataProvider('validIntegerProvider')]
    public function testValidIntegerInRange(string $input, int $expected): void
    {
        $result = Toml::decode('x = ' . $input);
        $this->assertSame($expected, $result['x']);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function validIntegerProvider(): array
    {
        return [
            'decimal max' => ['9223372036854775807', PHP_INT_MAX],
            'decimal min' => ['-9223372036854775808', PHP_INT_MIN],
            'decimal grouped max' => ['9_223_372_036_854_775_807', PHP_INT_MAX],
            'positive sign' => ['+99', 99],
            'hex max' => ['0x7FFFFFFFFFFFFFFF', PHP_INT_MAX],
            'octal' => ['0o777', 511],
            'binary' => ['0b1010', 10],
        ];
    }

    /**
     * Out-of-range integers must be rejected, not silently saturated to PHP_INT_MAX/MIN.
     */
    #[DataProvider('overflowIntegerProvider')]
    public function testOutOfRangeIntegerIsRejected(string $input): void
    {
        $parseResult = Toml::tryParse('x = ' . $input);
        $this->assertFalse(
            $parseResult->isValid(),
            'Expected out-of-range integer to be rejected: ' . $input,
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function overflowIntegerProvider(): array
    {
        return [
            'decimal just over max' => ['9223372036854775808'],
            'decimal just under min' => ['-9223372036854775809'],
            'decimal far over max' => ['99999999999999999999999999'],
            'hex over max' => ['0x8000000000000000'],
            'hex all ones' => ['0xFFFFFFFFFFFFFFFF'],
            'octal over max' => ['0o1777777777777777777777'],
            'binary over max' => ['0b' . str_repeat('1', 64)],
        ];
    }
}
