<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Support;

use DateTimeImmutable;
use Exception;
use PhpCollective\Toml\TomlVersion;

final class TemporalValidator
{
    public static function isValidOffsetDateTime(string $value, TomlVersion $version = TomlVersion::V11): bool
    {
        // Single regex to validate format and extract date/time parts
        $timePattern = $version === TomlVersion::V10
            ? '\d{2}:\d{2}:\d{2}(?:\.\d+)?'
            : '\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?';
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[Tt ](' . $timePattern . ')(?:[Zz]|[+-]\d{2}:\d{2})$/', $value, $matches) !== 1) {
            return false;
        }

        // Validate the datetime is parseable
        $normalized = str_replace(' ', 'T', $value);
        try {
            new DateTimeImmutable($normalized);
        } catch (Exception) {
            return false;
        }

        // Validate date and time components (e.g., reject Feb 30)
        return self::isValidLocalDate($matches[1]) && self::isValidLocalTime($matches[2]);
    }

    public static function isValidLocalDateTime(string $value, TomlVersion $version = TomlVersion::V11): bool
    {
        $timePattern = $version === TomlVersion::V10
            ? '\d{2}:\d{2}:\d{2}(?:\.\d+)?'
            : '\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?';
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[Tt ](' . $timePattern . ')$/', $value, $matches) !== 1) {
            return false;
        }

        return self::isValidLocalDate($matches[1]) && self::isValidLocalTime($matches[2]);
    }

    public static function isValidLocalDate(string $value): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) !== 1) {
            return false;
        }

        return checkdate((int)$matches[2], (int)$matches[3], (int)$matches[1]);
    }

    public static function isValidLocalTime(string $value, TomlVersion $version = TomlVersion::V11): bool
    {
        $pattern = $version === TomlVersion::V10
            ? '/^(\d{2}):(\d{2}):(\d{2})(?:\.(\d+))?$/'
            : '/^(\d{2}):(\d{2})(?::(\d{2})(?:\.(\d+))?)?$/';
        if (preg_match($pattern, $value, $matches) !== 1) {
            return false;
        }

        $hour = (int)$matches[1];
        $minute = (int)$matches[2];
        $second = isset($matches[3]) && $matches[3] !== '' ? (int)$matches[3] : 0;

        return $hour <= 23 && $minute <= 59 && $second <= 59;
    }
}
