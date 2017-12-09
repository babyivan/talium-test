<?php

namespace app;

final class config
{
    private static $host = 'localhost';
    private static $port = 3334;
    private static $max_connection = 100;

    private static $TAG_SYS = 'Sys';
    private static $TAG_INFO = 'Info';
    private static $TAG_DB = 'DB';

    private static $bd_conf = [
        '1-22' => [
            'color' => 'yellow',
            'rows_in_the_sector' => 10,
            'places_in_a_row' => 10,

            'custom' => [
                2 => [
                    'rows_in_the_sector' => 5,
                    'places_in_a_row' => 5,
                ],
                3 => [
                    'rows_in_the_sector' => 2,
                    'places_in_a_row' => 15,
                ],
            ],
        ],
        '23-40' => [
            'color' => 'green',
            'rows_in_the_sector' => 10,
            'places_in_a_row' => 10,
        ],
        '41-63' => [
            'color' => 'blue',
            'rows_in_the_sector' => 10,
            'places_in_a_row' => 10,
            'custom' => [
                50 => [
                    'rows_in_the_sector' => 8,
                    'places_in_a_row' => 3,
                ],
                51 => [
                    'rows_in_the_sector' => 12,
                    'places_in_a_row' => 10,
                ],
            ],
        ],
        '64-80' => [
            'color' => 'red',
            'rows_in_the_sector' => 10,
            'places_in_a_row' => 10,
        ],
    ];

    public static function logtag_db(): string
    {
        return self::$TAG_DB;
    }

    public static function logtag_sys(): string
    {
        return self::$TAG_SYS;
    }

    public static function logtag_info(): string
    {
        return self::$TAG_INFO;
    }

    public static function get_host(): string
    {
        return self::$host;
    }

    public static function get_port(): int
    {
        return self::$port;
    }

    public static function get_max_allowed_connections(): int
    {
        return self::$max_connection;
    }

    public static function get_db_rules(): array
    {
        return self::$bd_conf;
    }
}