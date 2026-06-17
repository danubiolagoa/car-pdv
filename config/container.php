<?php

declare(strict_types=1);

use App\Utils\JwtHelper;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Proxy\ProxyFactory;
use Psr\Container\ContainerInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

return [
    EntityManager::class => function (ContainerInterface $c) {
        $settings = $c->get('settings');
        $paths = $settings['doctrine']['metadata_dirs'];
        $isDevMode = $settings['doctrine']['dev_mode'];

        $dbParams = (new DsnParser())->parse($settings['database']['url']);
        if (($dbParams['driver'] ?? '') === 'postgresql') {
            $dbParams['driver'] = 'pdo_pgsql';
        }
        if (!isset($dbParams['driver'])) {
            $dbParams['driver'] = 'pdo_pgsql';
        }

        $config = ORMSetup::createAttributeMetadataConfiguration($paths, $isDevMode);
        $proxyDir = '/tmp/doctrine/proxies';
        @mkdir($proxyDir, 0775, true);
        $config->setProxyDir($proxyDir);
        $config->setAutoGenerateProxyClasses(ProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS);
        $connection = DriverManager::getConnection($dbParams, $config);

        return new EntityManager($connection, $config);
    },

    Environment::class => function () {
        $loader = new FilesystemLoader(__DIR__ . '/../templates');
        $isDev = ($_ENV['APP_ENV'] ?? 'production') === 'development';
        $twig = new Environment($loader, [
            'cache' => $isDev || getenv('VERCEL') ? false : __DIR__ . '/../cache/twig',
            'debug' => $isDev,
        ]);

        $twig->addGlobal('app_name', $_ENV['APP_NAME'] ?? 'CAR-PDV');
        $twig->addGlobal('app_url', $_ENV['APP_URL'] ?? '');
        $twig->addGlobal('app_env', $_ENV['APP_ENV'] ?? 'production');

        return $twig;
    },

    JwtHelper::class => function (ContainerInterface $c) {
        $settings = $c->get('settings');
        return new JwtHelper($settings['jwt']);
    },

    'settings' => [
        'displayErrorDetails' => ($_ENV['APP_ENV'] ?? 'production') === 'development',
        'logErrors' => true,
        'logErrorDetails' => true,
        'database' => [
            'url' => $_ENV['DATABASE_URL'] ?? '',
        ],
        'doctrine' => [
            'dev_mode' => ($_ENV['APP_ENV'] ?? 'production') === 'development',
            'metadata_dirs' => [__DIR__ . '/../src/Models'],
        ],
        'jwt' => [
            'secret' => $_ENV['JWT_SECRET'] ?? '',
            'issuer' => $_ENV['JWT_ISSUER'] ?? 'car-pdv',
            'audience' => $_ENV['JWT_AUDIENCE'] ?? 'car-pdv',
            'expiration' => (int) ($_ENV['JWT_EXPIRATION'] ?? 86400),
        ],
    ],
];
