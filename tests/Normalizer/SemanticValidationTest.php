<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Normalizer;

use PhpCollective\Toml\Toml;
use PHPUnit\Framework\TestCase;

/**
 * Tests for TOML semantic validation (duplicate keys, table redefinition, etc.)
 */
final class SemanticValidationTest extends TestCase
{
    public function testDuplicateKeyProducesError(): void
    {
        $result = Toml::tryParse(<<<'TOML'
name = "value1"
name = "value2"
TOML);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Duplicate key', $result->getErrors()[0]->message);
    }

    public function testDuplicateTableProducesError(): void
    {
        $result = Toml::tryParse(<<<'TOML'
[server]
host = "localhost"

[server]
port = 8080
TOML);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Duplicate table', $result->getErrors()[0]->message);
    }

    public function testCannotRedefineKeyAsTable(): void
    {
        $result = Toml::tryParse(<<<'TOML'
name = "value"

[name]
key = 1
TOML);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Cannot redefine', $result->getErrors()[0]->message);
    }

    public function testCannotRedefineTableAsKeyAtRootLevel(): void
    {
        // After [server], try to redefine 'server' at root level using a new section
        $result = Toml::tryParse(<<<'TOML'
server = "value"

[server]
host = "localhost"
TOML);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Cannot redefine', $result->getErrors()[0]->message);
    }

    public function testCannotRedefineArrayTableAsRegularTable(): void
    {
        $result = Toml::tryParse(<<<'TOML'
[[products]]
name = "Hammer"

[products]
count = 1
TOML);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Cannot redefine', $result->getErrors()[0]->message);
    }

    public function testDottedKeyDuplicateDetection(): void
    {
        $result = Toml::tryParse(<<<'TOML'
a.b = 1
a.b = 2
TOML);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Duplicate key', $result->getErrors()[0]->message);
    }

    public function testImplicitTableCanBeExtended(): void
    {
        // This should be valid - a.b creates implicit table [a]
        $result = Toml::tryParse(<<<'TOML'
a.b = 1
a.c = 2
TOML);

        $this->assertTrue($result->isValid());
        $this->assertSame(['a' => ['b' => 1, 'c' => 2]], $result->getValue());
    }

    public function testSubTableUnderExplicitTable(): void
    {
        // Valid: define [a] then [a.b]
        $result = Toml::tryParse(<<<'TOML'
[a]
key = 1

[a.b]
key = 2
TOML);

        $this->assertTrue($result->isValid());
        $this->assertSame([
            'a' => [
                'key' => 1,
                'b' => ['key' => 2],
            ],
        ], $result->getValue());
    }

    public function testInlineTableCannotBeExtended(): void
    {
        $result = Toml::tryParse(<<<'TOML'
point = { x = 1 }
point.y = 2
TOML);

        $this->assertFalse($result->isValid());
    }

    public function testArrayOfTablesWithSubtables(): void
    {
        $result = Toml::tryParse(<<<'TOML'
[[fruits]]
name = "apple"

[[fruits.varieties]]
name = "red delicious"

[[fruits.varieties]]
name = "granny smith"

[[fruits]]
name = "banana"

[[fruits.varieties]]
name = "plantain"
TOML);

        $this->assertTrue($result->isValid());
        $value = $result->getValue();
        $this->assertIsArray($value);
        $this->assertArrayHasKey('fruits', $value);
        $this->assertIsArray($value['fruits']);

        $this->assertCount(2, $value['fruits']);
        $this->assertIsArray($value['fruits'][0]);
        $this->assertSame('apple', $value['fruits'][0]['name']);
        $this->assertArrayHasKey('varieties', $value['fruits'][0]);
        $this->assertIsArray($value['fruits'][0]['varieties']);
        $this->assertCount(2, $value['fruits'][0]['varieties']);
        $this->assertIsArray($value['fruits'][1]);
        $this->assertSame('banana', $value['fruits'][1]['name']);
        $this->assertArrayHasKey('varieties', $value['fruits'][1]);
        $this->assertIsArray($value['fruits'][1]['varieties']);
        $this->assertCount(1, $value['fruits'][1]['varieties']);
    }
}
