<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Ast\Value;

use PhpCollective\Toml\Lexer\Span;

final class InlineTable extends AbstractValue
{
    /**
     * @param array<\PhpCollective\Toml\Ast\KeyValue> $items
     * @param \PhpCollective\Toml\Lexer\Span $span
     * @param array<\PhpCollective\Toml\Ast\Trivia> $openingTrivia
     * @param array<\PhpCollective\Toml\Ast\Trivia> $closingTrivia
     * @param int|null $originalItemCount
     */
    public function __construct(
        public array $items,
        Span $span,
        public array $openingTrivia = [],
        public array $closingTrivia = [],
        public ?int $originalItemCount = null,
    ) {
        parent::__construct($span);
    }

    /**
     * @return array<string, mixed>
     */
    public function getValue(): array
    {
        $result = [];
        foreach ($this->items as $kv) {
            $current = &$result;
            $lastIndex = count($kv->key->parts) - 1;

            foreach ($kv->key->parts as $index => $part) {
                if ($index === $lastIndex) {
                    $current[$part] = $kv->value->getValue();
                } else {
                    if (!isset($current[$part]) || !is_array($current[$part])) {
                        $current[$part] = [];
                    }
                    $current = &$current[$part];
                }
            }
        }

        return $result;
    }
}
