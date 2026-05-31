<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Parser;

use PhpCollective\Toml\Lexer\Span;

final readonly class ParseError
{
    /**
     * Stable, machine-readable error classification. Derived from the message when
     * not provided explicitly.
     */
    public ParseErrorCode $code;

    public function __construct(
        public string $message,
        public Span $span,
        public ?string $hint = null,
        ?ParseErrorCode $code = null,
    ) {
        $this->code = $code ?? ParseErrorCode::fromMessage($message);
    }

    public function format(string $input): string
    {
        $lines = explode("\n", $input);
        $lineNum = $this->span->line;
        $col = $this->span->column;

        $output = "Parse error: {$this->message}\n\n";

        // Show context lines
        $start = max(0, $lineNum - 2);
        $end = min(count($lines), $lineNum + 1);

        $numWidth = strlen((string)$end);

        for ($i = $start; $i < $end; $i++) {
            $prefix = str_pad((string)($i + 1), $numWidth, ' ', STR_PAD_LEFT);
            $output .= "  {$prefix} | {$lines[$i]}\n";

            if ($i === $lineNum - 1) {
                $output .= '  ' . str_repeat(' ', $numWidth) . ' | ' . str_repeat(' ', $col) . "^\n";
            }
        }

        if ($this->hint !== null) {
            $output .= "\nHint: {$this->hint}\n";
        }

        return $output;
    }
}
