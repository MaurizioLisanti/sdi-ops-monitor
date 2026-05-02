<?php
return [
    'debug' => (bool)env('DEBUG', false),
    'Security' => [
        'salt' => env('SECURITY_SALT', 'default-salt-change-in-production'),
    ],
    'Datasources' => [
        'default' => [
            'host'     => env('DB_HOST', '127.0.0.1'),
            'port'     => env('DB_PORT', '3306'),
            'username' => env('DB_USERNAME', 'sdi_user'),
            'password' => env('DB_PASSWORD', ''),
            'database' => env('DB_NAME', 'sdi_ops_monitor'),
        ],
        'test' => [
            'host'     => env('DB_HOST', '127.0.0.1'),
            'port'     => env('DB_PORT', '3306'),
            'username' => env('DB_USERNAME', 'sdi_user'),
            'password' => env('DB_PASSWORD', ''),
            'database' => env('DB_NAME', 'sdi_ops_monitor_test'),
        ],
    ],
];
