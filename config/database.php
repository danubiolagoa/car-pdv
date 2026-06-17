<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

function createEntityManager(): EntityManager
{
    $paths = [__DIR__ . '/../src/Models'];
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

    return new EntityManager($connection, $config);
}

return createEntityManager();
