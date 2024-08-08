<?php

return [
    'next_mysql' => [
        'driver' => 'mysql',
        'host' => env('NEXT_DB_HOST', '127.0.0.1'),
        'port' => env('NEXT_DB_PORT', '3306'),
        'database' => env('NEXT_DB_DATABASE', 'forge'),
        'username' => env('NEXT_DB_USERNAME', 'forge'),
        'password' => env('NEXT_DB_PASSWORD', ''),
        'unix_socket' => env('NEXT_DB_SOCKET', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ],
];