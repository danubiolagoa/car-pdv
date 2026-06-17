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

$conn = $em->getConnection();
$tenantRepo = $em->getRepository(Tenant::class);
$userRepo = $em->getRepository(User::class);

$tenant = $tenantRepo->findOneBy(['slug' => 'demo']);
if (!$tenant) {
    echo "ERRO: Execute primeiro o SeedInitialData para criar o tenant demo.\n";
    exit(1);
}

$tenantId = $tenant->getId();
echo "Usando tenant: {$tenant->getName()} ({$tenantId})\n";

// ─── Usuários extras ──────────────────────────────────────────────

$users = [];
$extraUsers = [
    ['name' => 'Carlos Vendedor', 'email' => 'vendedor@car-pdv.local', 'role' => 'seller', 'commission' => 3.00],
    ['name' => 'Ana Gerente',     'email' => 'gerente@car-pdv.local',  'role' => 'manager', 'commission' => 0],
    ['name' => 'João Mecânico',   'email' => 'mecanico@car-pdv.local', 'role' => 'mechanic', 'commission' => 0],
    ['name' => 'Pedro Mecânico',  'email' => 'pedro@car-pdv.local',    'role' => 'mechanic', 'commission' => 0],
];

foreach ($extraUsers as $data) {
    $existing = $userRepo->findOneBy(['email' => $data['email']]);
    if ($existing) {
        $users[$data['role']] = $existing;
        echo "Usuário já existe: {$data['email']}\n";
        continue;
    }
    $user = (new User())
        ->setTenant($tenant)
        ->setName($data['name'])
        ->setEmail($data['email'])
        ->setPasswordHash(password_hash('123456', PASSWORD_ARGON2ID))
        ->setRole($data['role'])
        ->setCommissionRate((float) $data['commission']);
    $em->persist($user);
    $users[$data['role']] = $user;
    echo "Usuário criado: {$data['name']} ({$data['role']})\n";
}
$em->flush();

// ─── Categorias ───────────────────────────────────────────────────

$categoriesData = [
    ['name' => 'Óleos e Lubrificantes',   'slug' => 'oleos',       'sort' => 1],
    ['name' => 'Filtros',                 'slug' => 'filtros',     'sort' => 2],
    ['name' => 'Freios',                  'slug' => 'freios',      'sort' => 3],
    ['name' => 'Acessórios',              'slug' => 'acessorios',  'sort' => 4],
    ['name' => 'Baterias',                'slug' => 'baterias',    'sort' => 5],
    ['name' => 'Suspensão e Direção',     'slug' => 'suspensao',   'sort' => 6],
    ['name' => 'Motor',                   'slug' => 'motor',       'sort' => 7],
];

$categories = [];
foreach ($categoriesData as $data) {
    $existing = $conn->fetchOne('SELECT id FROM categories WHERE tenant_id = ? AND slug = ?', [$tenantId, $data['slug']]);
    if ($existing) {
        $categories[$data['slug']] = $existing;
        echo "Categoria já existe: {$data['name']}\n";
        continue;
    }
    $catId = $conn->executeQuery(
        "INSERT INTO categories (id, tenant_id, name, slug, description, sort_order, is_active)
         VALUES (gen_random_uuid(), ?, ?, ?, ?, ?, true)
         RETURNING id",
        [$tenantId, $data['name'], $data['slug'], "Categoria {$data['name']}", $data['sort']]
    )->fetchOne();
    $categories[$data['slug']] = $catId;
    echo "Categoria criada: {$data['name']}\n";
}

// ─── Produtos ─────────────────────────────────────────────────────

$productsData = [
    ['Óleo Motor 5W30 Sintético 1L',       'OLE-001', 'oleos',      45.90, 89.90,  20, 5],
    ['Óleo Motor 15W40 Semissintético 1L',  'OLE-002', 'oleos',      38.50, 69.90,  30, 10],
    ['Óleo Motor 20W50 Mineral 1L',         'OLE-003', 'oleos',      35.00, 59.90,  25, 8],
    ['Óleo Câmbio ATF Dexron III 1L',       'OLE-004', 'oleos',      52.00, 99.90,  15, 5],
    ['Óleo Câmbio MTF 75W90 1L',            'OLE-005', 'oleos',      48.00, 89.90,  12, 5],
    ['Aditivo Radiador 1L',                  'OLE-006', 'oleos',      22.00, 39.90,  20, 5],
    ['Graxa Automotiva 400g',                'OLE-007', 'oleos',      18.00, 34.90,  10, 3],
    ['Filtro de Óleo',                       'FIL-001', 'filtros',   15.00, 29.90,  50, 10],
    ['Filtro de Ar',                         'FIL-002', 'filtros',   22.00, 44.90,  40, 10],
    ['Filtro de Combustível',                'FIL-003', 'filtros',   18.00, 35.90,  30, 8],
    ['Filtro de Cabine/Poeira',              'FIL-004', 'filtros',   25.00, 49.90,  25, 5],
    ['Filtro de Óleo Premium',               'FIL-005', 'filtros',   22.00, 42.90,  20, 5],
    ['Pastilha de Freio Dianteira',          'FRE-001', 'freios',    85.00, 169.90,  30, 5],
    ['Pastilha de Freio Traseira',           'FRE-002', 'freios',    80.00, 159.90,  25, 5],
    ['Disco de Freio Dianteiro',             'FRE-003', 'freios',   120.00, 249.90,  15, 3],
    ['Disco de Freio Traseiro',              'FRE-004', 'freios',   110.00, 229.90,  12, 3],
    ['Líquido de Freio DOT 4 500ml',         'FRE-005', 'freios',    18.00, 34.90,  20, 5],
    ['Lonas de Freio (conjunto)',            'FRE-006', 'freios',    95.00, 189.90,  10, 2],
    ['Lâmpada Farol H4',                     'ACE-001', 'acessorios', 8.00, 19.90,  60, 10],
    ['Lâmpada Seta 12V',                     'ACE-002', 'acessorios', 5.00, 12.90,  50, 10],
    ['Palheta Limpador 20"',                 'ACE-003', 'acessorios', 12.00, 29.90,  40, 8],
    ['Palheta Limpador 22"',                 'ACE-004', 'acessorios', 14.00, 32.90,  35, 8],
    ['Cera Automotiva 500ml',                'ACE-005', 'acessorios', 25.00, 49.90,  20, 5],
    ['Desengripante WD-40 400ml',            'ACE-006', 'acessorios', 28.00, 54.90,  25, 5],
    ['Silicone Spray 400ml',                 'ACE-007', 'acessorios', 22.00, 42.90,  15, 3],
    ['Abraçadeira Nylon (pacote 100)',       'ACE-008', 'acessorios', 15.00, 34.90,  10, 3],
    ['Bateria Automotiva 60Ah',              'BAT-001', 'baterias',  280.00, 499.90,  8, 2],
    ['Bateria Automotiva 70Ah',              'BAT-002', 'baterias',  320.00, 559.90,  8, 2],
    ['Bateria Automotiva 45Ah',              'BAT-003', 'baterias',  220.00, 399.90,  5, 2],
    ['Carregador de Bateria 12V',            'BAT-004', 'baterias',  85.00, 169.90,  5, 1],
    ['Amortecedor Dianteiro',                'SUS-001', 'suspensao', 180.00, 349.90,  10, 2],
    ['Amortecedor Traseiro',                 'SUS-002', 'suspensao', 165.00, 329.90,  8, 2],
    ['Bieleta de Suspensão',                 'SUS-003', 'suspensao', 35.00, 69.90,  20, 5],
    ['Barra Estabilizadora',                 'SUS-004', 'suspensao', 95.00, 189.90,  5, 2],
    ['Terminal de Direção',                  'SUS-005', 'suspensao', 42.00, 89.90,  15, 3],
    ['Correia Dentada (kit)',                'MOT-001', 'motor',      145.00, 289.90,  8, 2],
    ['Correia Alternador',                   'MOT-002', 'motor',      55.00, 109.90,  12, 3],
    ['Vela de Ignição (cada)',               'MOT-003', 'motor',      18.00, 34.90,  40, 10],
    ['Cabo de Vela (jogo)',                  'MOT-004', 'motor',      65.00, 129.90,  8, 2],
    ['Tampa de Válvula',                     'MOT-005', 'motor',      95.00, 189.90,  5, 1],
    ['Sonda Lambda',                         'MOT-006', 'motor',     120.00, 239.90,  6, 2],
];

foreach ($productsData as [$name, $sku, $catSlug, $cost, $price, $stock, $minStock]) {
    $existing = $conn->fetchOne('SELECT id FROM products WHERE tenant_id = ? AND sku = ?', [$tenantId, $sku]);
    if ($existing) {
        echo "Produto já existe: {$sku} - {$name}\n";
        continue;
    }
    $catId = $categories[$catSlug] ?? null;
    $pid = $conn->executeQuery(
        "INSERT INTO products (id, tenant_id, category_id, sku, name, cost_price, sale_price, current_stock, min_stock, unit, is_active, is_service)
         VALUES (gen_random_uuid(), ?, ?, ?, ?, ?, ?, ?, ?, 'UN', true, false)
         RETURNING id",
        [$tenantId, $catId, $sku, $name, $cost, $price, $stock, $minStock]
    )->fetchOne();
    $conn->executeStatement(
        "INSERT INTO inventory_movements (id, tenant_id, product_id, type, quantity, previous_stock, new_stock, cost_price, notes)
         VALUES (gen_random_uuid(), ?, ?, 'in', ?, 0, ?, ?, 'Estoque inicial (seed)')",
        [$tenantId, $pid, $stock, $stock, $cost]
    );
    echo "Produto criado: {$sku} - {$name} ({$stock} un)\n";
}

// ─── Serviços ─────────────────────────────────────────────────────

$servicesData = [
    ['Troca de Óleo Completa',          60,  89.90,  'Manutenção'],
    ['Troca de Filtros (óleo + ar)',    40,  59.90,  'Manutenção'],
    ['Troca de Pastilhas de Freio',     90, 149.90,  'Freios'],
    ['Alinhamento e Balanceamento',      60, 129.90,  'Suspensão'],
    ['Troca de Bateria',                30,  49.90,  'Elétrica'],
    ['Revisão Preventiva 10.000km',    120, 299.90,  'Revisão'],
    ['Revisão Preventiva 20.000km',    180, 449.90,  'Revisão'],
    ['Troca de Amortecedores (par)',   120, 199.90,  'Suspensão'],
    ['Troca de Correia Dentada',       180, 349.90,  'Motor'],
    ['Scanner de Injeção Eletrônica',   30,  79.90,  'Diagnóstico'],
    ['Troca de Velas e Cabos',          60,  99.90,  'Motor'],
    ['Higienização do Ar Condicionado', 45, 109.90,  'Ar Condicionado'],
];

foreach ($servicesData as [$name, $duration, $price, $cat]) {
    $existing = $conn->fetchOne('SELECT id FROM services WHERE tenant_id = ? AND name = ?', [$tenantId, $name]);
    if ($existing) {
        echo "Serviço já existe: {$name}\n";
        continue;
    }
    $conn->executeStatement(
        "INSERT INTO services (id, tenant_id, name, duration_minutes, price, category, is_active)
         VALUES (gen_random_uuid(), ?, ?, ?, ?, ?, true)",
        [$tenantId, $name, $duration, $price, $cat]
    );
    echo "Serviço criado: {$name} (R$ {$price})\n";
}

// ─── Clientes ─────────────────────────────────────────────────────

$customersData = [
    ['João Batista Silva',     '111.222.333-44', 'joao@email.com',    '(11) 99999-1111', '(11) 98888-1111', '{"street":"Rua das Flores, 123","neighborhood":"Centro","city":"São Paulo","state":"SP","zipcode":"01001-000"}'],
    ['Maria Aparecida Costa',  '222.333.444-55', 'maria@email.com',   '(11) 99999-2222', '(11) 98888-2222', '{"street":"Av. Paulista, 1000","neighborhood":"Bela Vista","city":"São Paulo","state":"SP","zipcode":"01310-100"}'],
    ['Carlos Eduardo Santos',  '333.444.555-66', 'carlos@email.com',  '(11) 99999-3333', '(11) 98888-3333', '{"street":"Rua Augusta, 500","neighborhood":"Consolação","city":"São Paulo","state":"SP","zipcode":"01304-000"}'],
    ['Ana Beatriz Oliveira',   '444.555.666-77', 'ana@email.com',     '(11) 99999-4444', '(11) 98888-4444', '{"street":"Rua Oscar Freire, 200","neighborhood":"Pinheiros","city":"São Paulo","state":"SP","zipcode":"01426-001"}'],
    ['Pedro Henrique Lima',    '555.666.777-88', 'pedro@email.com',   '(11) 99999-5555', '(11) 98888-5555', '{"street":"Rua dos Três Irmãos, 50","neighborhood":"Vila Mariana","city":"São Paulo","state":"SP","zipcode":"04120-000"}'],
    ['Lucia Ferreira Souza',   '666.777.888-99', 'lucia@email.com',   '(11) 99999-6666', '(11) 98888-6666', '{"street":"Rua das Acácias, 300","neighborhood":"Moema","city":"São Paulo","state":"SP","zipcode":"04550-000"}'],
    ['Roberto Almeida Neto',   '777.888.999-00', 'roberto@email.com', '(11) 99999-7777', '(11) 98888-7777', '{"street":"Rua Bela Cintra, 800","neighborhood":"Cerqueira César","city":"São Paulo","state":"SP","zipcode":"01415-000"}'],
    ['Fernanda Costa Lima',    '888.999.000-11', 'fernanda@email.com','(11) 99999-8888', '(11) 98888-8888', '{"street":"Rua da Consolação, 2000","neighborhood":"Higienópolis","city":"São Paulo","state":"SP","zipcode":"01201-000"}'],
];

$customers = [];
foreach ($customersData as [$name, $cpf, $email, $phone, $mobile, $address]) {
    $existing = $conn->fetchOne('SELECT id FROM customers WHERE tenant_id = ? AND cpf_cnpj = ?', [$tenantId, $cpf]);
    if ($existing) {
        $customers[$name] = $existing;
        echo "Cliente já existe: {$name}\n";
        continue;
    }
    $cid = $conn->executeQuery(
        "INSERT INTO customers (id, tenant_id, name, cpf_cnpj, email, phone, mobile, address, is_active)
         VALUES (gen_random_uuid(), ?, ?, ?, ?, ?, ?, ?::jsonb, true)
         RETURNING id",
        [$tenantId, $name, $cpf, $email, $phone, $mobile, $address]
    )->fetchOne();
    $customers[$name] = $cid;
    echo "Cliente criado: {$name}\n";
}

// ─── Veículos ─────────────────────────────────────────────────────

$vehiclesData = [
    ['João Batista Silva',    'ABC-1234', 'Fiat',   'Uno',       2018, 'Branco'],
    ['Maria Aparecida Costa', 'DEF-5678', 'VW',     'Gol',       2020, 'Prata'],
    ['Carlos Eduardo Santos', 'GHI-9012', 'Chevrolet', 'Onix',   2022, 'Preto'],
    ['Ana Beatriz Oliveira',  'JKL-3456', 'Honda',  'HR-V',      2021, 'Vermelho'],
    ['Pedro Henrique Lima',   'MNO-7890', 'Toyota', 'Corolla',   2023, 'Azul'],
    ['Lucia Ferreira Souza',  'PQR-1234', 'Jeep',   'Renegade',  2020, 'Verde'],
    ['Roberto Almeida Neto',  'STU-5678', 'VW',     'T-Cross',   2022, 'Cinza'],
    ['Fernanda Costa Lima',   'VWX-9012', 'Ford',   'Ka',        2019, 'Branco'],
];

foreach ($vehiclesData as [$customerName, $plate, $brand, $model, $year, $color]) {
    $customerId = $customers[$customerName] ?? null;
    if (!$customerId) {
        echo "Pulando veículo {$plate}: cliente {$customerName} não encontrado\n";
        continue;
    }
    $existing = $conn->fetchOne('SELECT id FROM vehicles WHERE tenant_id = ? AND plate = ?', [$tenantId, $plate]);
    if ($existing) {
        echo "Veículo já existe: {$plate}\n";
        continue;
    }
    $conn->executeStatement(
        "INSERT INTO vehicles (id, tenant_id, customer_id, plate, brand, model, year, color, is_active)
         VALUES (gen_random_uuid(), ?, ?, ?, ?, ?, ?, ?, true)",
        [$tenantId, $customerId, $plate, $brand, $model, $year, $color]
    );
    echo "Veículo criado: {$plate} ({$brand} {$model})\n";
}

// ─── Agendamentos de exemplo ──────────────────────────────────────

$mechanicUser = $users['mechanic'] ?? null;
if ($mechanicUser && !empty($customers)) {
    $serviceNames = [];
    foreach ($servicesData as [$name]) {
        $id = $conn->fetchOne('SELECT id FROM services WHERE tenant_id = ? AND name = ?', [$tenantId, $name]);
        if ($id) $serviceNames[$name] = $id;
    }
    $customerNames = array_keys($customers);

    for ($d = 0; $d < 5; $d++) {
        $date = (new DateTimeImmutable("+{$d} days 08:00:00"))->modify('+' . ($d * 2) . ' hours');
        $custName = $customerNames[$d % count($customerNames)];
        $svcName = array_rand($serviceNames);
        $svcId = $serviceNames[$svcName];

        $existing = $conn->fetchOne(
            'SELECT id FROM appointments WHERE tenant_id = ? AND scheduled_at = ? AND customer_id = ?',
            [$tenantId, $date->format('Y-m-d H:i:sO'), $customers[$custName]]
        );
        if ($existing) {
            echo "Agendamento já existe: {$custName} em {$date->format('d/m/Y H:i')}\n";
            continue;
        }
        $conn->executeStatement(
            "INSERT INTO appointments (id, tenant_id, customer_id, mechanic_id, service_id, status, scheduled_at, estimated_end, notes)
             VALUES (gen_random_uuid(), ?, ?, ?, ?, 'scheduled', ?, ?, 'Serviço agendado via seed de demonstração')",
            [
                $tenantId,
                $customers[$custName],
                $mechanicUser->getId(),
                $svcId,
                $date->format('Y-m-d H:i:sO'),
                $date->modify('+1 hour')->format('Y-m-d H:i:sO'),
            ]
        );
        echo "Agendamento criado: {$custName} - {$svcName} ({$date->format('d/m/Y H:i')})\n";
    }
} else {
    echo "Pulando agendamentos: sem mecânico ou clientes disponíveis\n";
}

echo "\nSeed de demonstração concluído!\n";
echo "\nResumo:\n";
echo "- Usuários: " . count($extraUsers) . " extras\n";
echo "- Categorias: " . count($categoriesData) . "\n";
echo "- Produtos: " . count($productsData) . "\n";
echo "- Serviços: " . count($servicesData) . "\n";
echo "- Clientes: " . count($customersData) . "\n";
echo "- Veículos: " . count($vehiclesData) . "\n";

echo "\n--- Credenciais dos usuários ---\n";
echo "Admin:     admin@car-pdv.local / admin123\n";
echo "Vendedor:  vendedor@car-pdv.local / 123456\n";
echo "Gerente:   gerente@car-pdv.local / 123456\n";
echo "Mecânico:  mecanico@car-pdv.local / 123456\n";
echo "Mecânico:  pedro@car-pdv.local / 123456\n";
