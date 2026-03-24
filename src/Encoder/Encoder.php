<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Encoder;

use DateTimeInterface;
use PhpCollective\Toml\Ast\Document;
use PhpCollective\Toml\Exception\EncodeException;
use PhpCollective\Toml\Normalizer;

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
        // For now, normalize and encode
        // TODO: Implement trivia preservation
        $normalizer = new Normalizer();

        return $this->encode($normalizer->normalize($doc));
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

        if (is_array($value)) {
            if ($this->isInlineArray($value)) {
                return $this->encodeArray($value);
            }

            return $this->encodeInlineTable($value);
        }

        throw new EncodeException('Cannot encode value of type ' . gettype($value));
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
