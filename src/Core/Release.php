<?php
declare(strict_types=1);
namespace JT\WPCare\Core;

/** Single source of application version and supported package contract. */
final class Release
{
    public const VERSION = '0.12.1-alpha';
    public const LOADER_PROTOCOL = 1;
    public const DATA_SCHEMA = 1;
    public const PHP_MIN = '8.0';
    public const WP_MIN = '6.0';

    public static function metadata(): array
    {
        return ['schema' => 1, 'version' => self::VERSION, 'loader_protocol' => self::LOADER_PROTOCOL,
            'data_schema' => self::DATA_SCHEMA, 'php_min' => self::PHP_MIN, 'wp_min' => self::WP_MIN];
    }
}
