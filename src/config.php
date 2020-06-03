<?php

return [
    'meta' => [
        'key' => '~/.ssh/id_rsa',
        'temp_dir' => '/var/tmp',
        'db' => [
            'character' => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
            'copy_sufix' => '_copy',
            'old_sufix' => '_old',
        ],
    ],
    'prod' => [
        'server' => [
            'host' => '',
            'user' => '',
            'pass' => '',
        ],
        'db' => [
            'host' => 'localhost',
            'port' => '3306',
            'name' => '',
            'user' => '',
            'pass' => '',
        ],
    ],
    'stage' => [
        'server' => [
            'host' => '',
            'user' => '',
            'pass' => '',
        ],
        'db' => [
            'host' => 'localhost',
            'port' => '3306',
            'name' => '',
            'user' => '',
            'pass' => '',
        ],
    ],
    'hooks' => [
        'pre_pod' => [
            // Maybe we will want to update real database
        ],
        'post_prod' => [
            'update Persons set FirstName = \'Margot\' where FirstName = \'BoB\'',
            'update Persons set LastName = \'Robbie\' where 1',
        ],
    ],
];