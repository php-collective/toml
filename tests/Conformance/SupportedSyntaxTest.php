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

    public function testParsesInlineTableTrailingComma(): void
    {
        $result = Toml::decode("point = { x = 1, }\n");

        $this->assertSame(['point' => ['x' => 1]], $result);
    }

    public function testParsesMultilineInlineTable(): void
    {
        $result = Toml::decode(<<<'TOML'
point = {
  x = 1,
  y = 2
}
TOML);

        $this->assertSame(['point' => ['x' => 1, 'y' => 2]], $result);
    }

    public function testParsesMultilineInlineTableWithTrailingComma(): void
    {
        $result = Toml::decode(<<<'TOML'
point = {
  x = 1,
  y = 2,
}
TOML);

        $this->assertSame(['point' => ['x' => 1, 'y' => 2]], $result);
    }

    public function testParsesMultilineInlineTableWithComments(): void
    {
        $result = Toml::decode(<<<'TOML'
point = {
  x = 1, # x coordinate
  y = 2, # y coordinate
}
TOML);

        $this->assertSame(['point' => ['x' => 1, 'y' => 2]], $result);
    }
}
