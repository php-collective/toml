<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Value;

use InvalidArgumentException;
use PhpCollective\Toml\Value\LocalDate;
use PhpCollective\Toml\Value\LocalDateTime;
use PhpCollective\Toml\Value\LocalTime;
use PHPUnit\Framework\TestCase;

final class TemporalValueTest extends TestCase
{
    public function testLocalDateRejectsInvalidLiteral(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid TOML local date');

        new LocalDate('2024-02-30');
    }

    public function testLocalTimeRejectsInvalidLiteral(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid TOML local time');

        new LocalTime('25:00:00');
    }

    public function testLocalDateTimeRejectsInvalidLiteral(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid TOML local datetime');

        new LocalDateTime('2024-03-15T25:00:00');
    }
}
