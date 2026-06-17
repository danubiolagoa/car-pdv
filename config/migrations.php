<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$dbParams = (new Doctrine\DBAL\Tools\DsnParser())->parse($_ENV['DATABASE_URL'] ?? '');
if (($dbParams['driver'] ?? '') === 'postgresql') {
    $dbParams['driver'] = 'pdo_pgsql';
}
if (!isset($dbParams['driver'])) {
    $dbParams['driver'] = 'pdo_pgsql';
}

return [
    'table_storage' => [
        'table_name' => 'doctrine_migration_versions',
        'version_column_name' => 'version',
        'version_column_length' => 191,
        'executed_at_column_name' => 'executed_at',
        'execution_time_column_name' => 'execution_time',
    ],
    'migrations_paths' => [
        'App\\Migrations' => __DIR__ . '/../database/migrations',
    ],
    'all_or_nothing' => true,
    'transactional' => true,
    'check_database_platform' => true,
    'organize_migrations' => 'none',
    'connection' => $dbParams,
];
