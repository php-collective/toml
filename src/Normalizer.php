<?php

declare(strict_types=1);

namespace PhpCollective\Toml;

use PhpCollective\Toml\Ast\Document;
use PhpCollective\Toml\Ast\KeyValue;
use PhpCollective\Toml\Ast\Table;
use PhpCollective\Toml\Ast\Value\ArrayValue;
use PhpCollective\Toml\Ast\Value\InlineTable;
use PhpCollective\Toml\Ast\Value\Value;
use PhpCollective\Toml\Lexer\Span;
use PhpCollective\Toml\Parser\ParseError;

final class Normalizer
{
    /**
     * @var array<\PhpCollective\Toml\Parser\ParseError>
     */
    private array $errors = [];

    /**
     * @var array<string, array{kind: string, span: \PhpCollective\Toml\Lexer\Span}>
     */
    private array $definedTables = [];

    /**
     * @var array<string, \PhpCollective\Toml\Lexer\Span>
     */
    private array $definedKeys = [];

    /**
     * @var array<string>
     */
    private array $activeDisplayPath = [];

    /**
     * @var array<string>
     */
    private array $activeInternalPath = [];

    /**
     * Keys that are inline tables and cannot be extended with dotted keys.
     *
     * @var array<string, \PhpCollective\Toml\Lexer\Span>
     */
    private array $sealedInlineTables = [];

    /**
     * Keys that are static arrays (defined via = [...]) whose elements cannot be extended.
     *
     * @var array<string, \PhpCollective\Toml\Lexer\Span>
     */
    private array $staticArrays = [];

    /**
     * @return array<string, mixed>
     */
    public function normalize(Document $doc): array
    {
        $this->errors = [];
        $this->definedTables = [];
        $this->definedKeys = [];
        $this->activeDisplayPath = [];
        $this->activeInternalPath = [];
        $this->sealedInlineTables = [];
        $this->staticArrays = [];
        $result = [];

        foreach ($doc->items as $item) {
            if ($item instanceof KeyValue) {
                $normalized = $this->normalizeValue($item->value);
                $this->setDocumentValue($result, $item->key->parts, $normalized['value'], $item->getSpan());
                if ($normalized['isInlineTable']) {
                    $this->sealedInlineTables[$this->pathId($item->key->parts)] = $item->getSpan();
                }
                if ($normalized['isStaticArray']) {
                    $this->staticArrays[$this->pathId($item->key->parts)] = $item->getSpan();
                }
            } elseif ($item instanceof Table) {
                $this->processTable($result, $item);
            }
        }

        return $result;
    }

    /**
     * @return array<\PhpCollective\Toml\Parser\ParseError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @param array<string, mixed> $result
     * @param \PhpCollective\Toml\Ast\Table $table
     */
    private function processTable(array &$result, Table $table): void
    {
        $path = $table->key->parts;

        if ($table->isArrayTable) {
            $target = &$this->openArrayTable($result, $path, $table->getSpan());
        } else {
            $target = &$this->openTable($result, $path, $table->getSpan());
        }

        if ($target === null) {
            return;
        }

        foreach ($table->items as $kv) {
            $normalized = $this->normalizeValue($kv->value);
            $this->setDocumentValue(
                $target,
                $kv->key->parts,
                $normalized['value'],
                $kv->getSpan(),
                $this->activeDisplayPath,
                $this->activeInternalPath,
            );
            if ($normalized['isInlineTable']) {
                $fullPath = [...$this->activeInternalPath, ...$kv->key->parts];
                $this->sealedInlineTables[$this->pathId($fullPath)] = $kv->getSpan();
            }
            if ($normalized['isStaticArray']) {
                $fullPath = [...$this->activeInternalPath, ...$kv->key->parts];
                $this->staticArrays[$this->pathId($fullPath)] = $kv->getSpan();
            }
        }
    }

    /**
     * @param array<string, mixed> $array
     * @param array<string> $path
     * @param \PhpCollective\Toml\Lexer\Span $span
     * @param mixed $value
     * @param array<string> $displayBasePath
     * @param array<string> $internalBasePath
     */
    private function setDocumentValue(
        array &$array,
        array $path,
        mixed $value,
        Span $span,
        array $displayBasePath = [],
        array $internalBasePath = [],
    ): void {
        $displayPath = [...$displayBasePath, ...$path];
        $internalPath = [...$internalBasePath, ...$path];
        $this->setNestedValue(
            $array,
            $path,
            $value,
            $span,
            $this->definedTables,
            $this->definedKeys,
            $displayPath,
            $internalPath,
        );
    }

    /**
     * @return array{value: mixed, isInlineTable: bool, isStaticArray: bool}
     */
    private function normalizeValue(Value $value): array
    {
        if ($value instanceof ArrayValue) {
            return [
                'value' => array_map(fn (Value $item) => $this->normalizeValue($item)['value'], $value->items),
                'isInlineTable' => false,
                'isStaticArray' => true,
            ];
        }

        if ($value instanceof InlineTable) {
            $result = [];
            $inlineDefinedTables = [];
            $inlineDefinedKeys = [];
            $inlineSealedTables = [];
            $inlineStaticArrays = [];

            foreach ($value->items as $kv) {
                $normalized = $this->normalizeValue($kv->value);
                $this->setNestedValue(
                    $result,
                    $kv->key->parts,
                    $normalized['value'],
                    $kv->getSpan(),
                    $inlineDefinedTables,
                    $inlineDefinedKeys,
                    $kv->key->parts,
                    $kv->key->parts,
                    $inlineSealedTables,
                    $inlineStaticArrays,
                );
                // Track sealed inline tables within this scope
                if ($normalized['isInlineTable']) {
                    $inlineSealedTables[$this->pathId($kv->key->parts)] = $kv->getSpan();
                }
                if ($normalized['isStaticArray']) {
                    $inlineStaticArrays[$this->pathId($kv->key->parts)] = $kv->getSpan();
                }
            }

            return ['value' => $result, 'isInlineTable' => true, 'isStaticArray' => false];
        }

        return ['value' => $value->getValue(), 'isInlineTable' => false, 'isStaticArray' => false];
    }

    /**
     * @param array<string, mixed> $array
     * @param array<string> $path
     * @param \PhpCollective\Toml\Lexer\Span $span
     *
     * @return array<string, mixed>|null
     */
    private function &openTable(array &$array, array $path, Span $span): ?array
    {
        $null = null;
        $current = &$array;
        $displayPrefix = [];
        $internalPrefix = [];
        $leafKey = array_pop($path);
        if ($leafKey === null) {
            return $null;
        }

        // Check if any path component is a sealed inline table
        $checkPath = [];
        foreach ([...$path, $leafKey] as $key) {
            $checkPath[] = $key;
            $checkPathId = $this->pathId($checkPath);
            if (isset($this->sealedInlineTables[$checkPathId])) {
                $displayPath = implode('.', $checkPath);
                $this->errors[] = new ParseError(
                    "Cannot extend inline table '{$displayPath}'",
                    $span,
                );

                return $null;
            }
        }

        foreach ($path as $key) {
            $displayPrefix[] = $key;
            $displayPath = implode('.', $displayPrefix);

            if (!array_key_exists($key, $current)) {
                $current[$key] = [];
                $internalPrefix[] = $key;
                $this->definedTables[$this->pathId($internalPrefix)] ??= ['kind' => 'implicit', 'span' => $span];
                $current = &$current[$key];

                continue;
            }

            if (!is_array($current[$key])) {
                $this->errors[] = new ParseError("Cannot redefine key '{$displayPath}' as a table", $span);

                return $null;
            }

            $internalPrefix[] = $key;
            if ($current[$key] !== [] && array_is_list($current[$key])) {
                // Check if this is a static array - cannot extend elements of static arrays
                $internalPathId = $this->pathId($internalPrefix);
                if (isset($this->staticArrays[$internalPathId])) {
                    $this->errors[] = new ParseError(
                        "Cannot extend values in static array '{$displayPath}'",
                        $span,
                    );

                    return $null;
                }
                $lastEntry = array_key_last($current[$key]);
                $internalPrefix[] = '#' . (string)$lastEntry;
                $current = &$current[$key][$lastEntry];
            } else {
                $this->definedTables[$this->pathId($internalPrefix)] ??= ['kind' => 'implicit', 'span' => $span];
                $current = &$current[$key];
            }
        }

        $displayPath = implode('.', [...$displayPrefix, $leafKey]);
        $internalPath = [...$internalPrefix, $leafKey];
        $internalPathString = $this->pathId($internalPath);

        if (isset($this->definedKeys[$internalPathString])) {
            $this->errors[] = new ParseError("Cannot redefine key '{$displayPath}' as a table", $span);

            return $null;
        }

        if (isset($this->definedTables[$internalPathString])) {
            $kind = $this->definedTables[$internalPathString]['kind'];
            if ($kind === 'array') {
                $this->errors[] = new ParseError(
                    "Cannot redefine array table '{$displayPath}' as a regular table",
                    $span,
                );

                return $null;
            }
            if ($kind === 'explicit') {
                $this->errors[] = new ParseError("Duplicate table '{$displayPath}'", $span);

                return $null;
            }
            if ($kind === 'dotted') {
                $this->errors[] = new ParseError(
                    "Cannot define table '{$displayPath}' after it was implicitly created by dotted keys",
                    $span,
                );

                return $null;
            }
            // kind === 'implicit' is OK - we're explicitly defining a previously implicit table
        }

        if (!array_key_exists($leafKey, $current)) {
            $current[$leafKey] = [];
        } elseif (!is_array($current[$leafKey]) || array_is_list($current[$leafKey])) {
            $this->errors[] = new ParseError("Cannot redefine key '{$displayPath}' as a table", $span);

            return $null;
        }

        $this->definedTables[$internalPathString] = ['kind' => 'explicit', 'span' => $span];
        $this->activeDisplayPath = [...$displayPrefix, $leafKey];
        $this->activeInternalPath = $internalPath;
        $target = &$current[$leafKey];

        return $target;
    }

    /**
     * @param array<string, mixed> $array
     * @param array<string> $path
     * @param \PhpCollective\Toml\Lexer\Span $span
     *
     * @return array<string, mixed>|null
     */
    private function &openArrayTable(array &$array, array $path, Span $span): ?array
    {
        $null = null;
        $current = &$array;
        $displayPrefix = [];
        $internalPrefix = [];
        $leafKey = array_pop($path);
        if ($leafKey === null) {
            return $null;
        }

        // Check if any path component is a sealed inline table
        $checkPath = [];
        foreach ([...$path, $leafKey] as $key) {
            $checkPath[] = $key;
            $checkPathId = $this->pathId($checkPath);
            if (isset($this->sealedInlineTables[$checkPathId])) {
                $displayPath = implode('.', $checkPath);
                $this->errors[] = new ParseError(
                    "Cannot extend inline table '{$displayPath}'",
                    $span,
                );

                return $null;
            }
        }

        foreach ($path as $key) {
            $displayPrefix[] = $key;
            $displayPath = implode('.', $displayPrefix);

            if (!array_key_exists($key, $current)) {
                $current[$key] = [];
                $internalPrefix[] = $key;
                $this->definedTables[$this->pathId($internalPrefix)] ??= ['kind' => 'implicit', 'span' => $span];
                $current = &$current[$key];

                continue;
            }

            if (!is_array($current[$key])) {
                $this->errors[] = new ParseError("Cannot redefine key '{$displayPath}' as a table", $span);

                return $null;
            }

            $internalPrefix[] = $key;
            if ($current[$key] !== [] && array_is_list($current[$key])) {
                // Check if this is a static array - cannot extend elements of static arrays
                $internalPathId = $this->pathId($internalPrefix);
                if (isset($this->staticArrays[$internalPathId])) {
                    $this->errors[] = new ParseError(
                        "Cannot extend values in static array '{$displayPath}'",
                        $span,
                    );

                    return $null;
                }
                $lastEntry = array_key_last($current[$key]);
                $internalPrefix[] = '#' . (string)$lastEntry;
                $current = &$current[$key][$lastEntry];
            } else {
                $this->definedTables[$this->pathId($internalPrefix)] ??= ['kind' => 'implicit', 'span' => $span];
                $current = &$current[$key];
            }
        }

        $displayPath = implode('.', [...$displayPrefix, $leafKey]);
        $internalPath = [...$internalPrefix, $leafKey];
        $internalPathString = $this->pathId($internalPath);

        if (isset($this->definedKeys[$internalPathString])) {
            $this->errors[] = new ParseError("Cannot redefine key '{$displayPath}' as an array table", $span);

            return $null;
        }

        if (isset($this->definedTables[$internalPathString]) && $this->definedTables[$internalPathString]['kind'] !== 'array') {
            $this->errors[] = new ParseError("Cannot redefine table '{$displayPath}' as an array table", $span);

            return $null;
        }

        if (!array_key_exists($leafKey, $current)) {
            $current[$leafKey] = [];
        } elseif (!is_array($current[$leafKey]) || !array_is_list($current[$leafKey])) {
            $this->errors[] = new ParseError("Cannot redefine key '{$displayPath}' as an array table", $span);

            return $null;
        }

        $current[$leafKey][] = [];
        $lastIndex = array_key_last($current[$leafKey]);
        $scopedInternalPath = [...$internalPath, '#' . (string)$lastIndex];
        $this->definedTables[$internalPathString] = ['kind' => 'array', 'span' => $span];
        $this->activeDisplayPath = [...$displayPrefix, $leafKey];
        $this->activeInternalPath = $scopedInternalPath;
        $target = &$current[$leafKey][$lastIndex];

        return $target;
    }

    /**
     * @param array<string, mixed> $array
     * @param array<string> $path
     * @param mixed $value
     * @param \PhpCollective\Toml\Lexer\Span $span
     * @param array<string, array{kind: string, span: \PhpCollective\Toml\Lexer\Span}> $definedTables
     * @param array<string, \PhpCollective\Toml\Lexer\Span> $definedKeys
     * @param array<string> $displayFullPath
     * @param array<string> $internalFullPath
     * @param array<string, \PhpCollective\Toml\Lexer\Span>|null $scopeSealedTables Sealed tables for inline scope (null = use global)
     * @param array<string, \PhpCollective\Toml\Lexer\Span>|null $scopeStaticArrays Static arrays for inline scope (null = use global)
     */
    private function setNestedValue(
        array &$array,
        array $path,
        mixed $value,
        Span $span,
        array &$definedTables,
        array &$definedKeys,
        array $displayFullPath,
        array $internalFullPath,
        ?array $scopeSealedTables = null,
        ?array $scopeStaticArrays = null,
    ): void {
        $current = &$array;
        $displayPrefix = array_slice($displayFullPath, 0, count($displayFullPath) - count($path));
        $internalPrefix = array_slice($internalFullPath, 0, count($internalFullPath) - count($path));
        $lastIndex = count($path) - 1;

        // Determine which sealed tables and static arrays to check
        $sealedTables = $scopeSealedTables ?? $this->sealedInlineTables;
        $staticArrays = $scopeStaticArrays ?? $this->staticArrays;

        // Check if we're extending a sealed inline table or static array
        $checkPath = $displayPrefix;
        foreach ($path as $i => $key) {
            $checkPath[] = $key;
            $checkPathStr = implode('.', $checkPath);
            $checkPathId = $this->pathId($checkPath);
            // If this intermediate path is sealed AND there's more path to traverse, reject
            if ($i < $lastIndex) {
                if (isset($sealedTables[$checkPathId])) {
                    $this->errors[] = new ParseError(
                        "Cannot extend inline table '{$checkPathStr}' with dotted keys",
                        $span,
                    );

                    return;
                }
                if (isset($staticArrays[$checkPathId])) {
                    $this->errors[] = new ParseError(
                        "Cannot extend static array '{$checkPathStr}' with dotted keys",
                        $span,
                    );

                    return;
                }
            }
        }

        // Reset prefix tracking for the main loop
        $displayPrefix = array_slice($displayFullPath, 0, count($displayFullPath) - count($path));
        $internalPrefix = array_slice($internalFullPath, 0, count($internalFullPath) - count($path));

        foreach ($path as $i => $key) {
            $displayPrefix[] = $key;
            $internalPrefix[] = $key;
            $displayPath = implode('.', $displayPrefix);
            $internalPath = $this->pathId($internalPrefix);

            if ($i === $lastIndex) {
                if (isset($definedKeys[$internalPath])) {
                    $this->errors[] = new ParseError("Duplicate key '{$displayPath}'", $span);

                    return;
                }

                if (isset($definedTables[$internalPath])) {
                    $this->errors[] = new ParseError("Cannot redefine table '{$displayPath}' as a key", $span);

                    return;
                }

                if (array_key_exists($key, $current)) {
                    $message = is_array($current[$key])
                        ? "Cannot redefine table '{$displayPath}' as a key"
                        : "Duplicate key '{$displayPath}'";
                    $this->errors[] = new ParseError($message, $span);

                    return;
                }

                $current[$key] = $value;
                $definedKeys[$internalPath] = $span;

                return;
            }

            // Check if this intermediate path was explicitly defined as a table or array table
            // If so, we cannot extend it with dotted keys (only for global scope)
            if ($scopeSealedTables === null && isset($this->definedTables[$internalPath])) {
                $kind = $this->definedTables[$internalPath]['kind'];
                if ($kind === 'explicit' || $kind === 'array') {
                    $this->errors[] = new ParseError(
                        "Cannot add keys to explicitly defined table '{$displayPath}' via dotted keys",
                        $span,
                    );

                    return;
                }
            }

            if (!array_key_exists($key, $current)) {
                $current[$key] = [];
                // Mark as 'dotted' - cannot be explicitly defined later
                $definedTables[$internalPath] ??= ['kind' => 'dotted', 'span' => $span];
            } elseif (!is_array($current[$key])) {
                $this->errors[] = new ParseError("Cannot redefine key '{$displayPath}' as a table", $span);

                return;
            }

            if ($current[$key] !== [] && array_is_list($current[$key])) {
                $lastEntry = array_key_last($current[$key]);
                $internalPrefix[] = '#' . (string)$lastEntry;
                $current = &$current[$key][$lastEntry];
            } else {
                // Mark as 'dotted' - cannot be explicitly defined later
                $definedTables[$internalPath] ??= ['kind' => 'dotted', 'span' => $span];
                $current = &$current[$key];
            }
        }
    }

    /**
     * @param array<string> $segments
     */
    private function pathId(array $segments): string
    {
        return json_encode($segments, JSON_THROW_ON_ERROR);
    }
}
