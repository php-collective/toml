<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Conformance;

use DateTimeImmutable;
use PhpCollective\Toml\Toml;
use PHPUnit\Framework\TestCase;

final class SupportedSyntaxTest extends TestCase
{
    public function testParsesEmptyQuotedKey(): void
    {
        $result = Toml::decode("\"\" = 1\n");

        $this->assertSame(['' => 1], $result);
    }

    public function testParsesSpaceSeparatedOffsetDateTime(): void
    {
        $result = Toml::decode("event = 1979-05-27 07:32:00Z\n");

        $this->assertArrayHasKey('event', $result);
        $this->assertInstanceOf(DateTimeImmutable::class, $result['event']);
        $this->assertSame('1979-05-27T07:32:00+00:00', $result['event']->format('c'));
    }

    public function testParsesMultilineArrayWithTrailingComma(): void
    {
        $result = Toml::decode(<<<'TOML'
values = [
  1,
  2,
]
TOML);

        $this->assertSame(['values' => [1, 2]], $result);
    }

    public function testRejectsInlineTableTrailingComma(): void
    {
        $result = Toml::tryParse("point = { x = 1, }\n");

        $this->assertFalse($result->isValid());
        $this->assertSame('Trailing comma not allowed in inline table', $result->getErrors()[0]->message);
    }

    public function testRejectsMultilineInlineTable(): void
    {
        $result = Toml::tryParse(<<<'TOML'
point = {
  x = 1,
  y = 2
}
TOML);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
    }
}
