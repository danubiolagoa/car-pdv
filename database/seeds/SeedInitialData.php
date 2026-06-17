<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Models\Tenant;
use App\Models\User;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->safeLoad();

$paths = [__DIR__ . '/../../src/Models'];
$isDevMode = true;

$dbParams = (new DsnParser())->parse($_ENV['DATABASE_URL'] ?? '');
if (($dbParams['driver'] ?? '') === 'postgresql') {
    $dbParams['driver'] = 'pdo_pgsql';
}
if (!isset($dbParams['driver'])) {
    $dbParams['driver'] = 'pdo_pgsql';
}

$config = ORMSetup::createAttributeMetadataConfiguration($paths, $isDevMode);
$connection = DriverManager::getConnection($dbParams, $config);
$em = new EntityManager($connection, $config);

$tenantRepo = $em->getRepository(Tenant::class);
$userRepo = $em->getRepository(User::class);

$adminEmail = $_ENV['ADMIN_EMAIL'] ?? 'admin@car-pdv.local';

$existingTenant = $tenantRepo->findOneBy(['slug' => 'demo']);
if (!$existingTenant) {
    $tenant = (new Tenant())
        ->setName('Oficina Demo')
        ->setSlug('demo')
        ->setBusinessType('automotive')
        ->setPlan('free');
    $em->persist($tenant);
    $em->flush();
    echo "Tenant criado: {$tenant->getName()} ({$tenant->getId()})\n";
} else {
    $tenant = $existingTenant;
    echo "Tenant já existe: {$tenant->getName()}\n";
}

$existingUser = $userRepo->findOneBy(['email' => $adminEmail]);
if (!$existingUser) {
    $user = (new User())
        ->setTenant($tenant)
        ->setName('Administrador')
        ->setEmail($adminEmail)
        ->setPasswordHash(password_hash($_ENV['ADMIN_PASSWORD'] ?? 'admin123', PASSWORD_ARGON2ID))
        ->setRole('admin');
    $em->persist($user);
    $em->flush();
    echo "Usuário admin criado: {$user->getEmail()}\n";
} else {
    echo "Usuário admin já existe: {$existingUser->getEmail()}\n";
}

echo "Seed concluído.\n";
