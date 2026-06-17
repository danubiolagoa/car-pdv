<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\Migrations\Configuration\Configuration;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ExistingConfiguration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\Storage\TableMetadataStorageConfiguration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$paths = [__DIR__ . '/src/Models'];
$isDevMode = ($_ENV['APP_ENV'] ?? 'production') === 'development';

$dbParams = (new DsnParser())->parse($_ENV['DATABASE_URL'] ?? '');
if (($dbParams['driver'] ?? '') === 'postgresql') {
    $dbParams['driver'] = 'pdo_pgsql';
}
if (!isset($dbParams['driver'])) {
    $dbParams['driver'] = 'pdo_pgsql';
}

$config = ORMSetup::createAttributeMetadataConfiguration($paths, $isDevMode);
$connection = DriverManager::getConnection($dbParams, $config);
$entityManager = new EntityManager($connection, $config);

$configuration = new Configuration();
$configuration->addMigrationsDirectory('App\\Migrations', __DIR__ . '/database/migrations');
$configuration->setAllOrNothing(true);
$configuration->setCheckDatabasePlatform(true);

$storageConfiguration = new TableMetadataStorageConfiguration();
$storageConfiguration->setTableName('doctrine_migration_versions');
$configuration->setMetadataStorageConfiguration($storageConfiguration);

return DependencyFactory::fromConnection(
    new ExistingConfiguration($configuration),
    new ExistingConnection($entityManager->getConnection())
);
