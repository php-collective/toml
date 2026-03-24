<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Conformance;

use PhpCollective\Toml\Toml;
use PHPUnit\Framework\TestCase;

final class CollectionsAndCommentsTest extends TestCase
{
    public function testParsesArrayWithInteriorComment(): void
    {
        $result = Toml::decode(<<<'TOML'
numbers = [1, # first
2]
TOML);

        $this->assertSame(['numbers' => [1, 2]], $result);
    }

    public function testParsesNestedDottedKeysInsideInlineTable(): void
    {
        $result = Toml::decode('value = { nested.key = 1, plain = 2 }');

        $this->assertSame([
            'value' => [
                'nested' => ['key' => 1],
                'plain' => 2,
            ],
        ], $result);
    }

    public function testParsesMultilineBasicStringWithLineEndingBackslash(): void
    {
        $result = Toml::decode(<<<'TOML'
value = """
line1\
    line2"""
TOML);

        $this->assertSame(['value' => 'line1line2'], $result);
    }
}
