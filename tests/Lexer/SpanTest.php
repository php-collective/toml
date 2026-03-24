<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Lexer;

use PhpCollective\Toml\Lexer\Span;
use PHPUnit\Framework\TestCase;

final class SpanTest extends TestCase
{
    public function testLength(): void
    {
        $span = new Span(5, 10, 1, 5);
        $this->assertSame(5, $span->length());
    }

    public function testMerge(): void
    {
        $a = new Span(0, 5, 1, 0);
        $b = new Span(10, 15, 1, 10);
        $merged = $a->merge($b);

        $this->assertSame(0, $merged->start);
        $this->assertSame(15, $merged->end);
    }
}
