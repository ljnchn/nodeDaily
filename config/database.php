<?php
return  [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver'      => 'mysql',
            'host'        => getenv('MYSQL_HOST'),
            'port'        => getenv('MYSQL_PORT'),
            'database'    => getenv('MYSQL_NAME'),
            'username'    => getenv('MYSQL_USER'),
            'password'    => getenv('MYSQL_PASSWORD'),
            'charset'     => 'utf8mb4',
            'collation'   => 'utf8mb4_general_ci',
            'prefix'      => '',
            'strict'      => true,
            'engine'      => null,
            'options'   => [
                PDO::ATTR_EMULATE_PREPARES => false, // Must be false for Swoole and Swow drivers.
            ],
            'pool' => [
                'max_connections' => 5,
                'min_connections' => 1,
                'wait_timeout' => 3,
                'idle_timeout' => 60,
                'heartbeat_interval' => 50,
            ],
        ],
        'sqlite' => [
            'driver'   => 'sqlite',
            'url' => '',
            'database' => 'database/lite.db',
            'prefix' => '',
            'foreign_key_constraints' => false,
            'busy_timeout' => null,
            'journal_mode' => 'WAL',
            'synchronous' => 'OFF',
            'cache_size' => -20000,
            'temp_store ' => 'MEMORY',
            'mmap_size ' => '2147483648',
        ],
    ],
];