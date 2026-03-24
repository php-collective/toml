<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Conformance;

use PhpCollective\Toml\Toml;
use PHPUnit\Framework\TestCase;

final class SpecEdgeCaseTest extends TestCase
{
    public function testParsesQuotedDottedKeysIndependently(): void
    {
        $result = Toml::decode(<<<'TOML'
"a.b" = 1
a.b = 2
TOML);

        $this->assertSame([
            'a.b' => 1,
            'a' => ['b' => 2],
        ], $result);
    }

    public function testParsesNestedInlineTables(): void
    {
        $result = Toml::decode('point = { x = 1, meta = { y = 2 } }');

        $this->assertSame([
            'point' => [
                'x' => 1,
                'meta' => ['y' => 2],
            ],
        ], $result);
    }

    public function testRejectsKeyRedefinitionAfterDottedKeyCreatesImplicitTable(): void
    {
        $result = Toml::tryParse(<<<'TOML'
a.b = 1
a = 2
TOML);

        $this->assertFalse($result->isValid());
        $this->assertSame("Cannot redefine table 'a' as a key", $result->getErrors()[0]->message);
    }

    public function testRejectsInlineTableExtensionBySection(): void
    {
        $result = Toml::tryParse(<<<'TOML'
point = { x = 1 }
[point]
y = 2
TOML);

        $this->assertFalse($result->isValid());
        $this->assertSame("Cannot redefine key 'point' as a table", $result->getErrors()[0]->message);
    }
}
