<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\PublicApi;

use PhpCollective\Toml\Ast\Document;
use PhpCollective\Toml\Exception\EncodeException;
use PhpCollective\Toml\Exception\ParseException;
use PhpCollective\Toml\Exception\TomlException;
use PhpCollective\Toml\Toml;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ContractsTest extends TestCase
{
    public function testTryParseReturnsDocumentEvenWhenInvalid(): void
    {
        $result = Toml::tryParse("key = \"unterminated\n");

        $this->assertFalse($result->isValid());
        $this->assertNull($result->getValue());
        $this->assertInstanceOf(Document::class, $result->getDocument());
        $this->assertNotEmpty($result->getErrors());
    }

    public function testDecodeFileThrowsParseExceptionForInvalidToml(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'toml-invalid-');
        $this->assertNotFalse($path);
        file_put_contents($path, "key = \"unterminated\n");

        try {
            $this->expectException(ParseException::class);
            Toml::decodeFile($path);
        } finally {
            @unlink($path);
        }
    }

    public function testParseExceptionExtendsTomlException(): void
    {
        try {
            Toml::decode("key = \"unterminated\n");
            $this->fail('Expected ParseException was not thrown.');
        } catch (ParseException $exception) {
            $this->assertInstanceOf(TomlException::class, $exception);
        }
    }

    public function testEncodeUnsupportedObjectThrowsEncodeException(): void
    {
        $this->expectException(EncodeException::class);

        Toml::encode([
            'object' => new stdClass(),
        ]);
    }

    public function testEncodeExceptionExtendsTomlException(): void
    {
        try {
            Toml::encode(['object' => new stdClass()]);
            $this->fail('Expected EncodeException was not thrown.');
        } catch (EncodeException $exception) {
            $this->assertInstanceOf(TomlException::class, $exception);
        }
    }

    public function testEncodeDocumentFallsBackToNormalizedOutputWithoutTrivia(): void
    {
        $document = Toml::parse("title = \"Example\"\n\n[server]\nhost = \"localhost\"\n");

        $encoded = Toml::encodeDocument($document);

        $this->assertSame("title = \"Example\"\n\n[server]\nhost = \"localhost\"", $encoded);
    }
}
