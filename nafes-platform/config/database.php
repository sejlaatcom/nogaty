<?php
use App\Core\Env;

return [
    'host'     => Env::get('DB_HOST', 'localhost'),
    'port'     => (int) Env::get('DB_PORT', 3306),
    'database' => Env::get('DB_DATABASE', ''),
    'username' => Env::get('DB_USERNAME', ''),
    'password' => (string) Env::get('DB_PASSWORD', ''),
    'charset'  => 'utf8mb4',
];
