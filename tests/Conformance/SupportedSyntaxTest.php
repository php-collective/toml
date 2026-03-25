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

    public function testParsesTabBeforeKey(): void
    {
        $result = Toml::decode("\tkey = 1\n");

        $this->assertSame(['key' => 1], $result);
    }

    public function testParsesTabAroundEqualsSign(): void
    {
        $result = Toml::decode("key\t=\t\"value\"\n");

        $this->assertSame(['key' => 'value'], $result);
    }

    public function testParsesTabIndentationInMultilineArray(): void
    {
        $result = Toml::decode("values = [\n\t1,\n\t2,\n]\n");

        $this->assertSame(['values' => [1, 2]], $result);
    }

    public function testParsesTabIndentationInMultilineInlineTable(): void
    {
        $result = Toml::decode("point = {\n\tx = 1,\n\ty = 2,\n}\n");

        $this->assertSame(['point' => ['x' => 1, 'y' => 2]], $result);
    }

    public function testParsesMixedTabAndSpaceIndentation(): void
    {
        $result = Toml::decode("values = [\n \t 1,\n\t \t2,\n]\n");

        $this->assertSame(['values' => [1, 2]], $result);
    }

    public function testParsesTabAfterTableHeader(): void
    {
        $result = Toml::decode("[server]\t\nhost = \"localhost\"\n");

        $this->assertSame(['server' => ['host' => 'localhost']], $result);
    }

    public function testParsesTabInComment(): void
    {
        $result = Toml::decode("key = 1 #\ttab in comment\n");

        $this->assertSame(['key' => 1], $result);
    }

    public function testParsesTabBetweenArrayElements(): void
    {
        $result = Toml::decode("values = [1,\t2,\t3]\n");

        $this->assertSame(['values' => [1, 2, 3]], $result);
    }

    public function testParsesTabBetweenInlineTableEntries(): void
    {
        $result = Toml::decode("point = {x = 1,\ty = 2}\n");

        $this->assertSame(['point' => ['x' => 1, 'y' => 2]], $result);
    }

    public function testParsesTabInTableHeaderSpacing(): void
    {
        $result = Toml::decode("[\tserver\t]\nport = 80\n");

        $this->assertSame(['server' => ['port' => 80]], $result);
    }

    public function testParsesTabInArrayTableHeaderSpacing(): void
    {
        $result = Toml::decode("[[\tproducts\t]]\nname = \"item\"\n");

        $this->assertSame(['products' => [['name' => 'item']]], $result);
    }
}
