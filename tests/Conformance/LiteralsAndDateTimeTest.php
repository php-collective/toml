<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Conformance;

use DateTimeImmutable;
use PhpCollective\Toml\Toml;
use PHPUnit\Framework\TestCase;

final class LiteralsAndDateTimeTest extends TestCase
{
    public function testParsesSignedZeroInteger(): void
    {
        $result = Toml::decode("value = +0\n");

        $this->assertSame(['value' => 0], $result);
    }

    public function testParsesUnderscoredFloat(): void
    {
        $result = Toml::decode("value = 1_2.3_4\n");

        $this->assertSame(['value' => 12.34], $result);
    }

    public function testRejectsFloatWithTrailingDot(): void
    {
        $result = Toml::tryParse("value = 1.\n");

        $this->assertFalse($result->isValid());
        $this->assertSame('Invalid token: `1.`', $result->getErrors()[0]->message);
    }

    public function testParsesLowercaseDateTimeSeparator(): void
    {
        $result = Toml::decode("value = 1979-05-27t07:32:00Z\n");

        $this->assertInstanceOf(DateTimeImmutable::class, $result['value']);
        $this->assertSame('1979-05-27T07:32:00+00:00', $result['value']->format('c'));
    }

    public function testParsesOffsetDateTimeWithoutSeconds(): void
    {
        $result = Toml::decode("value = 1979-05-27T07:32Z\n");

        $this->assertInstanceOf(DateTimeImmutable::class, $result['value']);
        $this->assertSame('1979-05-27T07:32:00+00:00', $result['value']->format('c'));
    }

    public function testRejectsInvalidUnicodeEscape(): void
    {
        $result = Toml::tryParse("value = \"\\uZZZZ\"\n");

        $this->assertFalse($result->isValid());
        $this->assertSame('Invalid token: `"\\uZZZZ"`', $result->getErrors()[0]->message);
    }

    public function testRejectsInvalidSurrogateEscape(): void
    {
        $result = Toml::tryParse("value = \"\\uD800\"\n");

        $this->assertFalse($result->isValid());
        $this->assertSame('Invalid token: `"\\uD800"`', $result->getErrors()[0]->message);
    }
}
