<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Encoder;

use DateTimeInterface;
use PhpCollective\Toml\Ast\Document;
use PhpCollective\Toml\Ast\Key;
use PhpCollective\Toml\Ast\KeyStyle;
use PhpCollective\Toml\Ast\KeyValue;
use PhpCollective\Toml\Ast\Node;
use PhpCollective\Toml\Ast\Table;
use PhpCollective\Toml\Ast\Trivia;
use PhpCollective\Toml\Ast\Value\ArrayValue;
use PhpCollective\Toml\Ast\Value\BoolValue;
use PhpCollective\Toml\Ast\Value\FloatValue;
use PhpCollective\Toml\Ast\Value\InlineTable;
use PhpCollective\Toml\Ast\Value\IntegerBase;
use PhpCollective\Toml\Ast\Value\IntegerValue;
use PhpCollective\Toml\Ast\Value\LocalDate;
use PhpCollective\Toml\Ast\Value\LocalDateTime;
use PhpCollective\Toml\Ast\Value\LocalTime;
use PhpCollective\Toml\Ast\Value\OffsetDateTime;
use PhpCollective\Toml\Ast\Value\StringStyle;
use PhpCollective\Toml\Ast\Value\StringValue;
use PhpCollective\Toml\Ast\Value\Value;
use PhpCollective\Toml\Exception\EncodeException;
use PhpCollective\Toml\Normalizer;
use PhpCollective\Toml\Support\TemporalValidator;
use PhpCollective\Toml\TomlVersion;
use PhpCollective\Toml\Value\LocalDateTime as ValueLocalDateTime;
use PhpCollective\Toml\Value\LocalTime as ValueLocalTime;
use PhpCollective\Toml\Value\TomlValue;

final class Encoder
{
    public function __construct(
        private readonly EncoderOptions $options = new EncoderOptions(),
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function encode(array $data): string
    {
        $lines = [];
        $this->encodeTable($data, [], $lines);

        return implode($this->options->newline, $lines);
    }

    public function encodeDocument(Document $doc): string
    {
        $normalizer = new Normalizer();
        $normalized = $normalizer->normalize($doc);
        $errors = $normalizer->getErrors();
        if ($errors !== []) {
            throw new EncodeException($errors[0]->message);
        }

        if ($this->options->documentFormatting === DocumentFormattingMode::Normalized || !$this->documentHasTrivia($doc)) {
            return $this->encode($normalized);
        }

        $this->assertDocumentSupportsVersion($doc);

        return $this->encodeAstItems($doc->items);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string> $path
     * @param array<string> $lines
     */
    private function encodeTable(array $data, array $path, array &$lines): void
    {
        $keys = array_keys($data);
        if ($this->options->sortKeys) {
            sort($keys);
        }

        // First pass: scalar values
        foreach ($keys as $key) {
            $value = $data[$key];
            if ($value === null && $this->options->skipNulls) {
                continue;
            }
            if (!is_array($value) || $this->isInlineArray($value)) {
                $lines[] = $this->encodeKey((string)$key) . ' = ' . $this->encodeValue($value);
            }
        }

        // Second pass: tables and array of tables
        foreach ($keys as $key) {
            $value = $data[$key];
            if (is_array($value) && !$this->isInlineArray($value)) {
                $newPath = [...$path, (string)$key];

                if ($this->isArrayOfTables($value)) {
                    foreach ($value as $item) {
                        $lines[] = '';
                        $lines[] = '[[' . $this->encodePath($newPath) . ']]';
                        $this->encodeTable($item, $newPath, $lines);
                    }
                } else {
                    $lines[] = '';
                    $lines[] = '[' . $this->encodePath($newPath) . ']';
                    $this->encodeTable($value, $newPath, $lines);
                }
            }
        }
    }

    private function encodeValue(mixed $value): string
    {
        if ($value === null) {
            throw new EncodeException('TOML does not support null values');
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value)) {
            return (string)$value;
        }

        if (is_float($value)) {
            if (is_infinite($value)) {
                return $value > 0 ? 'inf' : '-inf';
            }
            if (is_nan($value)) {
                return 'nan';
            }
            $str = (string)$value;
            if (!str_contains($str, '.') && !str_contains($str, 'e') && !str_contains($str, 'E')) {
                $str .= '.0';
            }

            return $str;
        }

        if (is_string($value)) {
            return $this->encodeString($value);
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:s.uP');
        }

        if ($value instanceof ValueLocalDateTime) {
            return $this->normalizeLocalDateTimeLiteral($value->value);
        }

        if ($value instanceof ValueLocalTime) {
            return $this->normalizeLocalTimeLiteral($value->value);
        }

        if ($value instanceof TomlValue) {
            return $value->toTomlLiteral();
        }

        if (is_array($value)) {
            if ($this->isInlineArray($value)) {
                return $this->encodeArray($value);
            }

            return $this->encodeInlineTable($value);
        }

        throw new EncodeException('Cannot encode value of type ' . gettype($value));
    }

    /**
     * @param array<\PhpCollective\Toml\Ast\Table|\PhpCollective\Toml\Ast\KeyValue> $items
     */
    private function encodeAstItems(array $items): string
    {
        $output = '';
        $count = count($items);

        foreach ($items as $index => $item) {
            $encoded = $this->encodeAstItem($item);
            $output .= $encoded;

            if ($index < $count - 1 && !$this->endsWithNewline($encoded)) {
                $output .= $this->options->newline;
            }
        }

        return $output;
    }

    private function encodeAstItem(Node $item): string
    {
        $output = $this->encodeTrivia($item->getLeadingTrivia());

        if ($item instanceof Table) {
            $header = $this->isReusableTableHeader($item)
                ? $item->rawHeader
                : $this->encodeAstTableHeader($item);
            $output .= $header;
            $output .= $this->encodeTrivia($item->getTrailingTrivia());

            if ($item->items !== []) {
                if ($item->getTrailingTrivia() === []) {
                    $output .= $this->options->newline;
                }
                $output .= $this->encodeAstItems($item->items);
            }

            return $output;
        }

        if ($item instanceof KeyValue) {
            $output .= $this->isReusableKeyValue($item)
                ? $item->raw
                : $this->encodeAstKeyValue($item);
            $output .= $this->encodeTrivia($item->getTrailingTrivia());

            return $output;
        }

        throw new EncodeException('Unsupported AST node for document encoding');
    }

    private function encodeAstKeyValue(KeyValue $item): string
    {
        if ($this->isOriginalKey($item->key) && $item->rawPrefix !== '') {
            return $item->rawPrefix . $this->encodeAstValue($item->value);
        }

        if ($item->rawAssignmentSeparator !== '') {
            return $this->encodeAstKey($item->key) . $item->rawAssignmentSeparator . $this->encodeAstValue($item->value);
        }

        return $this->encodeAstKey($item->key) . ' = ' . $this->encodeAstValue($item->value);
    }

    private function encodeAstTableHeader(Table $table): string
    {
        // Use raw prefix/suffix if available and table type unchanged
        if (
            $table->originalIsArrayTable === $table->isArrayTable
            && $table->rawHeaderPrefix !== ''
            && $table->rawHeaderSuffix !== ''
        ) {
            return $table->rawHeaderPrefix . $this->encodeAstKey($table->key) . $table->rawHeaderSuffix;
        }

        return $table->isArrayTable
            ? '[[' . $this->encodeAstKey($table->key) . ']]'
            : '[' . $this->encodeAstKey($table->key) . ']';
    }

    private function encodeAstKey(Key $key): string
    {
        if ($this->isOriginalKey($key) && $key->raw !== '') {
            return $key->raw;
        }

        $parts = [];

        foreach ($key->parts as $index => $part) {
            $style = $key->styles[$index] ?? KeyStyle::Bare;
            $parts[] = match ($style) {
                KeyStyle::Bare => preg_match('/^[a-zA-Z0-9_-]+$/', $part) ? $part : $this->encodeString($part),
                KeyStyle::Basic => $this->encodeString($part),
                KeyStyle::Literal => "'" . $part . "'",
            };
        }

        if (count($key->rawSeparators) === count($parts) - 1) {
            $output = $parts[0] ?? '';

            foreach ($key->rawSeparators as $index => $separator) {
                $output .= $separator . $parts[$index + 1];
            }

            return $output;
        }

        return implode('.', $parts);
    }

    private function encodeAstValue(Value $value): string
    {
        return match (true) {
            $value instanceof StringValue => $this->encodeAstStringValue($value),
            $value instanceof IntegerValue => $this->encodeAstIntegerValue($value),
            $value instanceof FloatValue => $this->isReusableFloat($value) ? $value->raw : $this->encodeValue($value->value),
            $value instanceof BoolValue => $this->isReusableBool($value) ? $value->raw : ($value->value ? 'true' : 'false'),
            $value instanceof OffsetDateTime => $this->isReusableOffsetDateTime($value) ? $value->raw : $value->value->format('Y-m-d\TH:i:s.uP'),
            $value instanceof LocalDateTime => $this->isReusableLocalDateTime($value) ? $value->raw : $this->normalizeLocalDateTimeLiteral($value->value),
            $value instanceof LocalDate => $this->isReusableLocalDate($value) ? $value->raw : $value->value,
            $value instanceof LocalTime => $this->isReusableLocalTime($value) ? $value->raw : $this->normalizeLocalTimeLiteral($value->value),
            $value instanceof ArrayValue => $this->encodeAstArray($value),
            $value instanceof InlineTable => $this->encodeAstInlineTable($value),
            default => throw new EncodeException('Unsupported AST value for document encoding'),
        };
    }

    private function encodeAstStringValue(StringValue $value): string
    {
        if ($this->isReusableString($value)) {
            return $value->raw;
        }

        return match ($value->style) {
            StringStyle::Basic => $this->encodeString($value->value),
            StringStyle::Literal => "'" . $value->value . "'",
            StringStyle::MultiLineBasic => $this->encodeMultilineBasicString($value->value),
            StringStyle::MultiLineLiteral => "'''\n" . $value->value . "'''",
        };
    }

    private function encodeAstIntegerValue(IntegerValue $value): string
    {
        if ($this->isReusableInteger($value)) {
            return $value->raw;
        }

        $number = $value->value;
        $sign = $number < 0 ? '-' : '';
        $absolute = abs($number);

        return match ($value->base) {
            IntegerBase::Decimal => (string)$number,
            IntegerBase::Hexadecimal => $sign . '0x' . strtoupper(dechex($absolute)),
            IntegerBase::Octal => $sign . '0o' . decoct($absolute),
            IntegerBase::Binary => $sign . '0b' . decbin($absolute),
        };
    }

    private function encodeAstArray(ArrayValue $value): string
    {
        if ($this->isReusableArray($value)) {
            return $value->raw;
        }

        $multiline = $this->isMultilineArray($value);
        $indent = $multiline ? $this->inferArrayIndentation($value) : null;

        // For shape changes on inline arrays, use simple formatting
        if (
            !$multiline
            && ($this->arrayHasSyntheticItems($value) || $this->collectionShapeChanged($value->originalItemCount, count($value->items)))
        ) {
            $style = $value->singleLineStyle ?? $this->inferSingleLineArrayStyle($value);
            if ($style !== null) {
                return $this->formatSingleLineArrayWithStyle($value, $style);
            }

            return '[' . implode(', ', array_map(fn (Value $item) => $this->encodeAstValue($item), $value->items)) . ']';
        }

        // For shape changes on multiline arrays, preserve multiline style with inferred indent
        if (
            $multiline
            && ($this->arrayHasSyntheticItems($value) || $this->collectionShapeChanged($value->originalItemCount, count($value->items)))
        ) {
            return $this->formatMultilineArray($value, $indent ?? '  ');
        }

        $output = '[';

        if ($value->items === []) {
            return $output . $this->encodeTrivia($value->openingTrivia) . ']';
        }

        foreach ($value->items as $index => $item) {
            $leadingTrivia = $item->getLeadingTrivia();
            if ($leadingTrivia !== []) {
                $output .= $this->encodeTrivia($leadingTrivia);
            } else {
                $output .= $this->defaultArrayItemPrefix($value, $index, $multiline, $indent);
            }

            $output .= $this->encodeAstValue($item);

            $trailingTrivia = $item->getTrailingTrivia();
            if ($trailingTrivia !== []) {
                $output .= $this->encodeTrivia($trailingTrivia);
            }

            if ($index < count($value->items) - 1 || $value->hasTrailingComma) {
                $output .= ',';
            }
        }

        $output .= $this->encodeTrivia($value->closingTrivia);

        return $output . ']';
    }

    /**
     * Format a multiline array with consistent indentation for new/changed items.
     */
    private function formatMultilineArray(ArrayValue $value, string $indent): string
    {
        $output = '[';
        $newline = $this->options->newline;

        if ($value->items === []) {
            return $output . $newline . ']';
        }

        foreach ($value->items as $index => $item) {
            $leadingTrivia = $item->getLeadingTrivia();

            // Use existing trivia if available, otherwise use formatter-style indent
            if ($leadingTrivia !== [] && !$this->isSyntheticNode($item)) {
                $output .= $this->encodeTrivia($leadingTrivia);
            } else {
                $output .= $newline . $indent;
            }

            $output .= $this->encodeAstValue($item);

            $trailingTrivia = $item->getTrailingTrivia();
            if ($trailingTrivia !== [] && !$this->isSyntheticNode($item)) {
                $output .= $this->encodeTrivia($trailingTrivia);
            }

            // Always add comma for consistency in multiline arrays
            if ($index < count($value->items) - 1 || $value->hasTrailingComma) {
                $output .= ',';
            }
        }

        // Add closing bracket on new line if original had closing trivia with newline
        if ($value->closingTrivia !== [] && $this->triviaContainsNewline($value->closingTrivia)) {
            $output .= $this->encodeTrivia($value->closingTrivia);
        } else {
            $output .= $newline;
        }

        return $output . ']';
    }

    private function encodeAstInlineTable(InlineTable $value): string
    {
        $this->assertInlineTableSupportsVersion($value);

        if ($this->isReusableInlineTable($value)) {
            return $value->raw;
        }

        // For shape changes, use formatter-style with inferred spacing
        if ($this->inlineTableHasSyntheticItems($value) || $this->collectionShapeChanged($value->originalItemCount, count($value->items))) {
            $style = $value->singleLineStyle ?? $this->inferSingleLineInlineTableStyle($value);
            if ($style !== null) {
                return $this->formatInlineTableWithStyle($value, $style);
            }

            return $this->formatInlineTable($value);
        }

        $output = '{';

        if ($value->items === []) {
            return $output . $this->encodeTrivia($value->openingTrivia) . '}';
        }

        foreach ($value->items as $index => $item) {
            $leadingTrivia = $item->getLeadingTrivia();
            if ($leadingTrivia !== []) {
                $output .= $this->encodeTrivia($leadingTrivia);
            } else {
                $output .= $index === 0 ? ($value->openingTrivia !== [] ? $this->encodeTrivia($value->openingTrivia) : ' ') : ' ';
            }

            $output .= $this->encodeAstKeyValue($item);

            $trailingTrivia = $item->getTrailingTrivia();
            if ($trailingTrivia !== []) {
                if (!($index < count($value->items) - 1 && $this->triviaIsOnlyWhitespace($trailingTrivia))) {
                    $output .= $this->encodeTrivia($trailingTrivia);
                }
            } elseif ($index === count($value->items) - 1 && $value->closingTrivia === []) {
                $output .= ' ';
            }

            if ($index < count($value->items) - 1 || $value->hasTrailingComma) {
                $output .= ',';
            }
        }

        $output .= $this->encodeTrivia($value->closingTrivia);

        return $output . '}';
    }

    /**
     * Format an inline table with canonical spacing for changed items.
     * Uses consistent `{ key = value, key2 = value2 }` formatting.
     */
    private function formatInlineTable(InlineTable $value): string
    {
        $this->assertInlineTableSupportsVersion($value);

        if ($value->items === []) {
            return '{}';
        }

        // Use canonical formatting: { key = value, key2 = value2 }
        return '{ ' . implode(', ', array_map(
            fn (KeyValue $item) => $this->encodeAstKey($item->key) . ' = ' . $this->encodeAstValue($item->value),
            $value->items,
        )) . ' }';
    }

    /**
     * @param \PhpCollective\Toml\Ast\Value\ArrayValue $value
     * @param array{opening:string, beforeComma:string, afterComma:string, closing:string, trailingComma:bool} $style
     */
    private function formatSingleLineArrayWithStyle(ArrayValue $value, array $style): string
    {
        if ($value->items === []) {
            return '[' . $style['opening'] . $style['closing'] . ']';
        }

        $output = '[' . $style['opening'];

        foreach ($value->items as $index => $item) {
            if ($index > 0) {
                $output .= $style['beforeComma'] . ',' . $style['afterComma'];
            }

            $output .= $this->encodeAstValue($item);
        }

        if ($style['trailingComma']) {
            $output .= $style['beforeComma'] . ',';
        }

        return $output . $style['closing'] . ']';
    }

    /**
     * @param \PhpCollective\Toml\Ast\Value\InlineTable $value
     * @param array{opening:string, afterComma:string, closing:string} $style
     */
    private function formatInlineTableWithStyle(InlineTable $value, array $style): string
    {
        $this->assertInlineTableSupportsVersion($value);

        if ($value->items === []) {
            return '{' . $style['opening'] . $style['closing'] . '}';
        }

        $output = '{' . $style['opening'];

        foreach ($value->items as $index => $item) {
            if ($index > 0) {
                $output .= ',' . $style['afterComma'];
            }

            $output .= $this->encodeAstKeyValue($item);
        }

        if ($value->hasTrailingComma) {
            $output .= ',';
        }

        return $output . $style['closing'] . '}';
    }

    private function assertInlineTableSupportsVersion(InlineTable $value, bool $sourceAware = false): void
    {
        if ($this->options->version !== TomlVersion::V10) {
            return;
        }

        if ($value->hasTrailingComma) {
            throw new EncodeException($sourceAware
                ? 'Source-aware TOML 1.0 output cannot preserve inline table trailing commas'
                : 'TOML 1.0 does not allow trailing commas in inline tables');
        }

        if (
            $this->triviaContainsNewline($value->openingTrivia)
            || $this->triviaContainsNewline($value->closingTrivia)
        ) {
            throw new EncodeException($sourceAware
                ? 'Source-aware TOML 1.0 output cannot preserve multiline inline tables'
                : 'TOML 1.0 does not allow multiline inline tables');
        }

        foreach ($value->items as $item) {
            if ($this->triviaContainsNewline($item->getLeadingTrivia()) || $this->triviaContainsNewline($item->getTrailingTrivia())) {
                throw new EncodeException($sourceAware
                    ? 'Source-aware TOML 1.0 output cannot preserve multiline inline tables'
                    : 'TOML 1.0 does not allow multiline inline tables');
            }
        }
    }

    private function normalizeLocalDateTimeLiteral(string $value): string
    {
        if ($this->options->version === TomlVersion::V11) {
            return $value;
        }

        return preg_replace('/^(.+[Tt ])(\d{2}:\d{2})$/', '$1$2:00', $value) ?? $value;
    }

    private function normalizeLocalTimeLiteral(string $value): string
    {
        if ($this->options->version === TomlVersion::V11) {
            return $value;
        }

        return preg_match('/^\d{2}:\d{2}$/', $value) === 1
            ? $value . ':00'
            : $value;
    }

    private function encodeMultilineBasicString(string $value): string
    {
        $escaped = str_replace(
            ['\\', '"""', "\t", "\x08", "\x0C", "\r"],
            ['\\\\', '\"""', '\\t', '\\b', '\\f', '\\r'],
            $value,
        );

        return "\"\"\"\n{$escaped}\"\"\"";
    }

    /**
     * @param array<\PhpCollective\Toml\Ast\Trivia> $trivia
     */
    private function encodeTrivia(array $trivia): string
    {
        return implode('', array_map(static fn (Trivia $item) => $item->value, $trivia));
    }

    private function documentHasTrivia(Document $doc): bool
    {
        foreach ($doc->items as $item) {
            if ($item->getLeadingTrivia() !== [] || $item->getTrailingTrivia() !== []) {
                return true;
            }

            if ($item instanceof KeyValue && $this->astValueHasTrivia($item->value)) {
                return true;
            }

            if ($item instanceof Table) {
                foreach ($item->items as $child) {
                    if ($child->getLeadingTrivia() !== [] || $child->getTrailingTrivia() !== []) {
                        return true;
                    }

                    if ($this->astValueHasTrivia($child->value)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function assertDocumentSupportsVersion(Document $doc): void
    {
        if ($this->options->version !== TomlVersion::V10) {
            return;
        }

        foreach ($doc->items as $item) {
            if ($item instanceof KeyValue) {
                $this->assertAstValueSupportsVersion($item->value);

                continue;
            }

            foreach ($item->items as $child) {
                $this->assertAstValueSupportsVersion($child->value);
            }
        }
    }

    private function assertAstValueSupportsVersion(Value $value): void
    {
        if ($this->options->version !== TomlVersion::V10) {
            return;
        }

        if ($value instanceof OffsetDateTime) {
            $literal = $value->raw !== '' ? $value->raw : $value->value->format('Y-m-d\TH:i:s.uP');
            if (!TemporalValidator::isValidOffsetDateTime($literal, TomlVersion::V10)) {
                throw new EncodeException('Source-aware TOML 1.0 output cannot preserve offset datetimes without seconds');
            }

            return;
        }

        if ($value instanceof LocalDateTime) {
            $literal = $value->raw !== '' ? $value->raw : $value->value;
            if (!TemporalValidator::isValidLocalDateTime($literal, TomlVersion::V10)) {
                throw new EncodeException('Source-aware TOML 1.0 output cannot preserve local datetimes without seconds');
            }

            return;
        }

        if ($value instanceof LocalTime) {
            $literal = $value->raw !== '' ? $value->raw : $value->value;
            if (!TemporalValidator::isValidLocalTime($literal, TomlVersion::V10)) {
                throw new EncodeException('Source-aware TOML 1.0 output cannot preserve local times without seconds');
            }

            return;
        }

        if ($value instanceof ArrayValue) {
            foreach ($value->items as $item) {
                $this->assertAstValueSupportsVersion($item);
            }

            return;
        }

        if (!$value instanceof InlineTable) {
            return;
        }

        $this->assertInlineTableSupportsVersion($value, sourceAware: true);

        foreach ($value->items as $item) {
            $this->assertAstValueSupportsVersion($item->value);
        }
    }

    private function astValueHasTrivia(Value $value): bool
    {
        if ($value->getLeadingTrivia() !== [] || $value->getTrailingTrivia() !== []) {
            return true;
        }

        if ($value instanceof ArrayValue) {
            if ($value->openingTrivia !== [] || $value->closingTrivia !== [] || $value->hasTrailingComma) {
                return true;
            }

            foreach ($value->items as $item) {
                if ($this->astValueHasTrivia($item)) {
                    return true;
                }
            }
        }

        if ($value instanceof InlineTable) {
            if ($value->openingTrivia !== [] || $value->closingTrivia !== []) {
                return true;
            }

            foreach ($value->items as $item) {
                if ($item->getLeadingTrivia() !== [] || $item->getTrailingTrivia() !== []) {
                    return true;
                }

                if ($this->astValueHasTrivia($item->value)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function arrayHasSyntheticItems(ArrayValue $value): bool
    {
        foreach ($value->items as $item) {
            if ($this->isSyntheticNode($item)) {
                return true;
            }
        }

        return false;
    }

    private function inlineTableHasSyntheticItems(InlineTable $value): bool
    {
        foreach ($value->items as $item) {
            if ($this->isSyntheticNode($item)) {
                return true;
            }
        }

        return false;
    }

    private function isSyntheticNode(Node $node): bool
    {
        return $node->getSpan()->length() === 0;
    }

    private function isReusableKeyValue(KeyValue $item): bool
    {
        return $item->raw !== '' && $this->isOriginalKey($item->key) && $this->isReusableValue($item->value);
    }

    private function isReusableTableHeader(Table $table): bool
    {
        return $table->rawHeader !== ''
            && $table->originalIsArrayTable === $table->isArrayTable
            && $this->isOriginalKey($table->key);
    }

    private function isOriginalKey(Key $key): bool
    {
        return $key->originalParts === $key->parts
            && $key->originalStyles === $key->styles;
    }

    private function isReusableValue(Value $value): bool
    {
        return match (true) {
            $value instanceof StringValue => $this->isReusableString($value),
            $value instanceof IntegerValue => $this->isReusableInteger($value),
            $value instanceof FloatValue => $this->isReusableFloat($value),
            $value instanceof BoolValue => $this->isReusableBool($value),
            $value instanceof OffsetDateTime => $this->isReusableOffsetDateTime($value),
            $value instanceof LocalDateTime => $this->isReusableLocalDateTime($value),
            $value instanceof LocalDate => $this->isReusableLocalDate($value),
            $value instanceof LocalTime => $this->isReusableLocalTime($value),
            $value instanceof ArrayValue => $this->isReusableArray($value),
            $value instanceof InlineTable => $this->isReusableInlineTable($value),
            default => false,
        };
    }

    private function isReusableString(StringValue $value): bool
    {
        return $value->raw !== ''
            && $value->originalValue === $value->value
            && $value->originalStyle === $value->style;
    }

    private function isReusableInteger(IntegerValue $value): bool
    {
        return $value->raw !== ''
            && $value->originalValue === $value->value
            && $value->originalBase === $value->base;
    }

    private function isReusableFloat(FloatValue $value): bool
    {
        if ($value->raw === '') {
            return false;
        }

        // NaN is not equal to itself, so handle it specially
        if (is_nan($value->value)) {
            return $value->originalValue !== null && is_nan($value->originalValue);
        }

        return $value->originalValue === $value->value;
    }

    private function isReusableBool(BoolValue $value): bool
    {
        return $value->raw !== '' && $value->originalValue === $value->value;
    }

    private function isReusableOffsetDateTime(OffsetDateTime $value): bool
    {
        return $value->raw !== '' && $value->originalComparable === $value->value->format('Y-m-d\TH:i:s.uP');
    }

    private function isReusableLocalDateTime(LocalDateTime $value): bool
    {
        return $value->raw !== '' && $value->originalValue === $value->value;
    }

    private function isReusableLocalDate(LocalDate $value): bool
    {
        return $value->raw !== '' && $value->originalValue === $value->value;
    }

    private function isReusableLocalTime(LocalTime $value): bool
    {
        return $value->raw !== '' && $value->originalValue === $value->value;
    }

    private function isReusableArray(ArrayValue $value): bool
    {
        if (
            $value->raw === ''
            || $this->arrayHasSyntheticItems($value)
            || $this->collectionShapeChanged($value->originalItemCount, count($value->items))
        ) {
            return false;
        }

        foreach ($value->items as $item) {
            if (!$this->isReusableValue($item)) {
                return false;
            }
        }

        return true;
    }

    private function isReusableInlineTable(InlineTable $value): bool
    {
        if (
            $value->raw === ''
            || $this->inlineTableHasSyntheticItems($value)
            || $this->collectionShapeChanged($value->originalItemCount, count($value->items))
        ) {
            return false;
        }

        foreach ($value->items as $item) {
            if (!$this->isReusableKeyValue($item)) {
                return false;
            }
        }

        return true;
    }

    private function collectionShapeChanged(?int $originalItemCount, int $currentItemCount): bool
    {
        return $originalItemCount !== null && $originalItemCount !== $currentItemCount;
    }

    private function defaultArrayItemPrefix(ArrayValue $value, int $index, bool $multiline, ?string $indent): string
    {
        if ($index === 0) {
            if ($value->openingTrivia !== []) {
                return $this->encodeTrivia($value->openingTrivia);
            }

            return '';
        }

        if ($multiline) {
            return $this->options->newline . ($indent ?? '');
        }

        return ' ';
    }

    private function isMultilineArray(ArrayValue $value): bool
    {
        if ($this->triviaContainsNewline($value->openingTrivia) || $this->triviaContainsNewline($value->closingTrivia)) {
            return true;
        }

        foreach ($value->items as $item) {
            if ($this->triviaContainsNewline($item->getLeadingTrivia()) || $this->triviaContainsNewline($item->getTrailingTrivia())) {
                return true;
            }
        }

        return false;
    }

    private function inferArrayIndentation(ArrayValue $value): ?string
    {
        $indent = $this->extractIndentationFromTrivia($value->openingTrivia);
        if ($indent !== null) {
            return $indent;
        }

        foreach ($value->items as $item) {
            $indent = $this->extractIndentationFromTrivia($item->getLeadingTrivia());
            if ($indent !== null) {
                return $indent;
            }

            $indent = $this->extractIndentationFromTrivia($item->getTrailingTrivia());
            if ($indent !== null) {
                return $indent;
            }
        }

        $indent = $this->extractIndentationFromTrivia($value->closingTrivia);

        return $indent;
    }

    /**
     * @param array<\PhpCollective\Toml\Ast\Trivia> $trivia
     */
    private function triviaContainsNewline(array $trivia): bool
    {
        foreach ($trivia as $item) {
            if (str_contains($item->value, "\n")) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<\PhpCollective\Toml\Ast\Trivia> $trivia
     */
    private function extractIndentationFromTrivia(array $trivia): ?string
    {
        $buffer = $this->encodeTrivia($trivia);
        $lastNewline = strrpos($buffer, "\n");
        if ($lastNewline === false) {
            return null;
        }

        $indent = substr($buffer, $lastNewline + 1);

        return preg_match('/^[ \t]*$/', $indent) === 1 ? $indent : null;
    }

    /**
     * @param array<\PhpCollective\Toml\Ast\Trivia> $trivia
     */
    private function triviaIsOnlyWhitespace(array $trivia): bool
    {
        foreach ($trivia as $item) {
            if (!preg_match('/^[ \t]+$/', $item->value)) {
                return false;
            }
        }

        return $trivia !== [];
    }

    /**
     * @return array{opening:string, beforeComma:string, afterComma:string, closing:string, trailingComma:bool}|null
     */
    private function inferSingleLineArrayStyle(ArrayValue $value): ?array
    {
        if (!$this->singleLineCollectionTriviaIsReusable($value->openingTrivia, $value->closingTrivia)) {
            return null;
        }

        $opening = $this->encodeTrivia($value->openingTrivia);
        $closing = $this->encodeTrivia($value->closingTrivia);

        $beforeComma = null;
        $afterComma = null;
        $observedPair = false;
        $itemCount = count($value->items);

        for ($index = 0; $index < $itemCount - 1; $index++) {
            if ($this->isSyntheticNode($value->items[$index]) || $this->isSyntheticNode($value->items[$index + 1])) {
                continue;
            }

            $currentBeforeComma = $this->singleLineWhitespaceTrivia($value->items[$index]->getTrailingTrivia());
            $currentAfterComma = $this->singleLineWhitespaceTrivia($value->items[$index + 1]->getLeadingTrivia());

            if ($currentBeforeComma === null || $currentAfterComma === null) {
                return null;
            }

            $observedPair = true;
            $beforeComma ??= $currentBeforeComma;
            $afterComma ??= $currentAfterComma;

            if ($beforeComma !== $currentBeforeComma || $afterComma !== $currentAfterComma) {
                return null;
            }
        }

        if (!$observedPair) {
            return null;
        }

        return [
            'opening' => $opening,
            'beforeComma' => $beforeComma ?? '',
            'afterComma' => $afterComma ?? ' ',
            'closing' => $closing,
            'trailingComma' => $value->hasTrailingComma,
        ];
    }

    /**
     * @return array{opening:string, afterComma:string, closing:string}|null
     */
    private function inferSingleLineInlineTableStyle(InlineTable $value): ?array
    {
        if (!$this->singleLineCollectionTriviaIsReusable($value->openingTrivia, $value->closingTrivia)) {
            return null;
        }

        $opening = $this->encodeTrivia($value->openingTrivia);
        $closing = $this->encodeTrivia($value->closingTrivia);

        $afterComma = null;
        $observedPair = false;
        $itemCount = count($value->items);

        for ($index = 1; $index < $itemCount; $index++) {
            if ($this->isSyntheticNode($value->items[$index - 1]) || $this->isSyntheticNode($value->items[$index])) {
                continue;
            }

            $currentAfterComma = $this->singleLineWhitespaceTrivia($value->items[$index]->getLeadingTrivia());
            if ($currentAfterComma === null) {
                return null;
            }

            $observedPair = true;
            $afterComma ??= $currentAfterComma;
            if ($afterComma !== $currentAfterComma) {
                return null;
            }
        }

        if (!$observedPair) {
            return null;
        }

        return [
            'opening' => $opening !== '' ? $opening : ' ',
            'afterComma' => $afterComma ?? ' ',
            'closing' => $closing !== '' ? $closing : ' ',
        ];
    }

    /**
     * @param array<\PhpCollective\Toml\Ast\Trivia> $openingTrivia
     * @param array<\PhpCollective\Toml\Ast\Trivia> $closingTrivia
     */
    private function singleLineCollectionTriviaIsReusable(array $openingTrivia, array $closingTrivia): bool
    {
        return $this->singleLineWhitespaceTrivia($openingTrivia) !== null
            && $this->singleLineWhitespaceTrivia($closingTrivia) !== null;
    }

    /**
     * @param array<\PhpCollective\Toml\Ast\Trivia> $trivia
     */
    private function singleLineWhitespaceTrivia(array $trivia): ?string
    {
        if ($trivia === []) {
            return '';
        }

        return $this->triviaIsOnlyWhitespace($trivia) ? $this->encodeTrivia($trivia) : null;
    }

    private function endsWithNewline(string $value): bool
    {
        return str_ends_with($value, "\n") || str_ends_with($value, "\r\n");
    }

    private function encodeString(string $value): string
    {
        // Use basic string with escaping
        $escaped = str_replace(
            ['\\', '"', "\n", "\r", "\t", "\x08", "\x0C"],
            ['\\\\', '\\"', '\\n', '\\r', '\\t', '\\b', '\\f'],
            $value,
        );

        return '"' . $escaped . '"';
    }

    /**
     * @param array<mixed> $value
     */
    private function encodeArray(array $value): string
    {
        if ($this->options->skipNulls) {
            $value = array_filter($value, static fn ($v) => $v !== null);
        }
        $items = array_map(fn ($v) => $this->encodeValue($v), $value);

        return '[' . implode(', ', $items) . ']';
    }

    /**
     * @param array<string, mixed> $value
     */
    private function encodeInlineTable(array $value): string
    {
        $items = [];
        foreach ($value as $k => $v) {
            if ($v === null && $this->options->skipNulls) {
                continue;
            }
            $items[] = $this->encodeKey((string)$k) . ' = ' . $this->encodeValue($v);
        }

        return '{ ' . implode(', ', $items) . ' }';
    }

    private function encodeKey(string $key): string
    {
        if (preg_match('/^[a-zA-Z0-9_-]+$/', $key)) {
            return $key;
        }

        return $this->encodeString($key);
    }

    /**
     * @param array<string> $path
     */
    private function encodePath(array $path): string
    {
        return implode('.', array_map(fn ($k) => $this->encodeKey($k), $path));
    }

    /**
     * @param array<mixed> $value
     */
    private function isInlineArray(array $value): bool
    {
        return array_is_list($value) && !$this->isArrayOfTables($value);
    }

    /**
     * @param array<mixed> $value
     */
    private function isArrayOfTables(array $value): bool
    {
        if (!array_is_list($value) || $value === []) {
            return false;
        }
        foreach ($value as $item) {
            if (!is_array($item) || array_is_list($item)) {
                return false;
            }
        }

        return true;
    }
}
