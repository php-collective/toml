---
layout: home

hero:
  name: toml-php
  text: PHP Parser for TOML
  tagline: A PHP TOML parser and encoder with AST access and collected parse errors
  image:
    src: /logo.svg
    alt: toml-php
  actions:
    - theme: brand
      text: Get Started
      link: /guide/
    - theme: alt
      text: Support Matrix
      link: /reference/support-matrix
    - theme: alt
      text: View on GitHub
      link: https://github.com/php-collective/toml

features:
  - icon: ✅
    title: Strict Validation
    details: Rejects malformed strings, numbers, datetimes, and semantic conflicts such as duplicate keys and table redefinitions
  - icon: 🔍
    title: Error Recovery
    details: Collects multiple parse errors for IDE/tooling integration instead of failing on first error
  - icon: 🌳
    title: AST Access
    details: Full abstract syntax tree for analysis, transformation, or editor integrations
  - icon: ⚡
    title: Zero Dependencies
    details: No required extensions - pure PHP 8.2+ with optional php-ds for performance
  - icon: 🔄
    title: Structured Re-Encode
    details: Parse TOML to an AST, modify nodes, and re-encode normalized TOML output
  - icon: 📝
    title: Detailed Errors
    details: Rich error messages with line/column info and hints for common mistakes
---
