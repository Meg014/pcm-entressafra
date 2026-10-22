<?php
declare(strict_types=1);

use Cake\Database\Driver\Postgres;
use Cake\Database\Driver\Sqlite;
use function Cake\Core\env;

// Restrição deliberada: esta aplicação não pode apontar para bancos de outros sistemas.
if (env('DB_NAME', 'pcm_controle_entressafra') !== 'pcm_controle_entressafra') {
    throw new RuntimeException('DB_NAME deve ser pcm_controle_entressafra.');
}

return [
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
    'App' => [
        'defaultLocale' => 'pt_BR',
        'defaultTimezone' => 'UTC',
        'displayTimezone' => 'America/Sao_Paulo',
        'fullBaseUrl' => env('APP_FULL_BASE_URL', 'http://localhost:8766'),
    ],
    'Security' => ['salt' => env('SECURITY_SALT')],
    'Datasources' => [
        'default' => [
            'driver' => Postgres::class,
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => (int)env('DB_PORT', 5432),
            'database' => 'pcm_controle_entressafra',
            'username' => env('DB_USER', 'pcm_entressafra_app'),
            'password' => env('DB_PASSWORD'),
            'schema' => 'public',
            'encoding' => 'utf8',
            'timezone' => 'UTC',
            'url' => null,
        ],
        // Testes locais isolados; nunca reutilizar a conexão PostgreSQL principal.
        'test' => [
            'driver' => Sqlite::class,
            'database' => TMP . 'entressafra_tests.sqlite',
            'encoding' => 'utf8',
            'url' => null,
        ],
    ],
];
