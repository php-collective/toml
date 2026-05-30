<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Parser;

use PhpCollective\Toml\Ast\Document;

final class ParseResult
{
    /**
     * @param \PhpCollective\Toml\Ast\Document|null $document
     * @param array<\PhpCollective\Toml\Parser\ParseError> $errors
     * @param array<string, mixed>|null $value
     */
    public function __construct(
        private readonly ?Document $document,
        private readonly array $errors,
        private readonly ?array $value = null,
    ) {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function getDocument(): ?Document
    {
        return $this->document;
    }

    /**
     * @return array<\PhpCollective\Toml\Parser\ParseError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getValue(): ?array
    {
        return $this->value;
    }
}
