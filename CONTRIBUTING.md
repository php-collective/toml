# Contributing

Thank you for your interest in contributing to PHP Toml!

## Getting Started

```bash
# Clone the repository
git clone https://github.com/php-collective/toml.git
cd toml

# Install dependencies
composer install
```

## Development Workflow

### Running Tests

```bash
# Run all tests
composer test

# Run a specific test file
vendor/bin/phpunit tests/Parser/ParserTest.php

# Run a specific test method
vendor/bin/phpunit --filter testParseBasicString
```

### Code Style

This project follows PHP Collective coding standards.

```bash
# Check code style
composer cs-check

# Auto-fix code style issues
composer cs-fix
```

### Static Analysis

```bash
# Run PHPStan
composer stan
```

### Full Check

```bash
# Run all checks (code style + static analysis + tests)
composer check
```

## Project Structure

```
src/
├── Toml.php                # Main entry point (facade)
├── Lexer/
│   ├── Lexer.php           # Tokenizer
│   ├── Token.php           # Token representation
│   ├── TokenType.php       # Token type enum
│   └── Span.php            # Source location tracking
├── Parser/
│   ├── Parser.php          # Recursive descent parser
│   ├── ParseResult.php     # Parse result with errors
│   └── ParseError.php      # Error representation
├── Ast/                    # Abstract Syntax Tree nodes
│   ├── Document.php        # Root document node
│   ├── Table.php           # Table header node
│   ├── KeyValue.php        # Key-value pair node
│   ├── Key.php             # Key with parts and styles
│   └── Value/              # Value type nodes
├── Encoder/
│   ├── Encoder.php         # TOML encoder
│   └── EncoderOptions.php  # Encoding options
├── Normalizer.php          # AST to array conversion
├── Value/                  # Encoder value wrappers
│   ├── LocalDate.php
│   ├── LocalTime.php
│   └── LocalDateTime.php
└── Exception/
    ├── ParseException.php
    └── EncodeException.php
```

## Writing Tests

Tests are located in `tests/`. Follow the existing patterns:

```php
public function testFeatureName(): void
{
    $toml = 'key = "value"';
    $expected = ['key' => 'value'];

    $this->assertSame($expected, Toml::decode($toml));
}
```

For error cases:

```php
public function testRejectsInvalidSyntax(): void
{
    $this->expectException(ParseException::class);

    Toml::decode('invalid = ');
}
```

For encoding tests:

```php
public function testEncodesArray(): void
{
    $data = ['items' => [1, 2, 3]];
    $toml = Toml::encode($data);

    $this->assertStringContainsString('items = [1, 2, 3]', $toml);
}
```

## Documentation Site

The documentation is built with [VitePress](https://vitepress.dev/).

### Local Development

```bash
cd docs
npm install
npm run dev
```

This starts a dev server at `http://localhost:5173/toml/` with hot reload.

### Building

```bash
cd docs
npm run build
npm run preview  # Preview the build
```

## Pull Request Guidelines

1. Create a feature branch from `master`
2. Write tests for new functionality
3. Ensure all tests pass: `vendor/bin/phpunit`
4. Ensure code style passes: `composer cs-check`
5. Ensure PHPStan passes: `composer stan`
6. Submit a pull request with a clear description

## Reporting Issues

When reporting bugs, please include:

- PHP version
- Minimal TOML input that reproduces the issue
- Expected output
- Actual output

## Resources

- [TOML Specification](https://toml.io/)
- [Project Documentation](https://php-collective.github.io/toml/)
- [Support Matrix](docs/reference/support-matrix.md)
