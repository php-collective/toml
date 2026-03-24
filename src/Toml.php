<?php

declare(strict_types=1);

namespace PhpCollective\Toml;

use PhpCollective\Toml\Ast\Document;
use PhpCollective\Toml\Encoder\Encoder;
use PhpCollective\Toml\Encoder\EncoderOptions;
use PhpCollective\Toml\Exception\ParseException;
use PhpCollective\Toml\Parser\Parser;
use PhpCollective\Toml\Parser\ParseResult;

final class Toml
{
    /**
     * Decode TOML string to PHP array.
     *
     * @throws \PhpCollective\Toml\Exception\ParseException on invalid TOML
     *
     * @return array<string, mixed>
     */
    public static function decode(string $input): array
    {
        $parser = new Parser();
        $doc = $parser->parse($input);
        $normalizer = new Normalizer();
        $value = $normalizer->normalize($doc);
        $errors = [...$parser->getErrors(), ...$normalizer->getErrors()];

        if ($errors !== []) {
            $error = $errors[0];

            throw new ParseException($error->message, $error->span, $error->hint);
        }

        return $value;
    }

    /**
     * Decode TOML file to PHP array.
     *
     * @throws \PhpCollective\Toml\Exception\ParseException
     *
     * @return array<string, mixed>
     */
    public static function decodeFile(string $path): array
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new ParseException("Cannot read file: {$path}");
        }

        return self::decode($content);
    }

    /**
     * Parse without throwing - returns result with errors.
     * For tooling/IDE use.
     */
    public static function tryParse(string $input): ParseResult
    {
        $parser = new Parser();
        $doc = $parser->parse($input);
        $normalizer = new Normalizer();
        $value = $normalizer->normalize($doc);
        $errors = [...$parser->getErrors(), ...$normalizer->getErrors()];

        $value = $errors === [] ? $value : null;

        return new ParseResult($doc, $errors, $value);
    }

    /**
     * Parse to AST for analysis or round-trip editing.
     */
    public static function parse(string $input, bool $preserveTrivia = false): Document
    {
        $parser = new Parser($preserveTrivia);

        return $parser->parse($input);
    }

    /**
     * Encode PHP array to TOML string.
     *
     * @param array<string, mixed> $data
     * @param \PhpCollective\Toml\Encoder\EncoderOptions|null $options
     */
    public static function encode(array $data, ?EncoderOptions $options = null): string
    {
        $encoder = new Encoder($options ?? new EncoderOptions());

        return $encoder->encode($data);
    }

    /**
     * Encode AST document (for round-trip preservation).
     */
    public static function encodeDocument(Document $doc, ?EncoderOptions $options = null): string
    {
        $encoder = new Encoder($options ?? new EncoderOptions());

        return $encoder->encodeDocument($doc);
    }
}
