#!/bin/bash
cat > /var/app/current/config/app_local.php << 'PHP'
<?php
return [
    'debug' => false,
    'Security' => ['salt' => getenv('SECURITY_SALT') ?: 'change-this-salt'],
    'Datasources' => [
        'default' => [
            'host'     => getenv('DB_HOST') ?: '127.0.0.1',
            'port'     => getenv('DB_PORT') ?: '3306',
            'username' => getenv('DB_USERNAME') ?: 'sdi_user',
            'password' => getenv('DB_PASSWORD') ?: 'secret',
            'database' => getenv('DB_NAME') ?: 'sdi_ops_monitor',
        ],
    ],
];
PHP
