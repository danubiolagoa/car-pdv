<?php

declare(strict_types=1);

namespace App\Controllers;

use Doctrine\ORM\EntityManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;

class CustomersController
{
    public function __construct(
        private readonly EntityManager $em,
        private readonly Environment $twig,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write($this->twig->render('customers/index.html.twig', [
            'user' => [
                'name' => $request->getAttribute('name'),
                'role' => $request->getAttribute('role'),
            ],
        ]));

        return $response->withHeader('Content-Type', 'text/html');
    }

    public function listCustomers(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        $params = $request->getQueryParams();
        $search = trim((string) ($params['search'] ?? ''));

        $sql = 'SELECT c.id, c.name, c.cpf_cnpj, c.email, c.phone, c.mobile, c.total_purchases, c.loyalty_points,
                       (SELECT COUNT(*) FROM vehicles v WHERE v.customer_id = c.id AND v.is_active = true) AS vehicles_count
                FROM customers c
                WHERE c.tenant_id = :tenant_id AND c.is_active = true';
        $bindings = ['tenant_id' => $tenantId];

        if ($search !== '') {
            $sql .= ' AND (c.name ILIKE :search OR c.cpf_cnpj ILIKE :search OR c.email ILIKE :search OR c.phone ILIKE :search)';
            $bindings['search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY c.name LIMIT 200';

        $customers = $this->em->getConnection()->fetchAllAssociative($sql, $bindings);

        return $this->json($response, ['customers' => $customers]);
    }

    public function createCustomer(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        $data = (array) ($request->getParsedBody() ?? []);

        $name = trim((string) ($data['name'] ?? ''));
        $cpfCnpj = !empty($data['cpf_cnpj']) ? preg_replace('/\D/', '', (string) $data['cpf_cnpj']) : null;

        if ($name === '') {
            return $this->json($response, ['error' => 'Nome é obrigatório'], 422);
        }

        if ($cpfCnpj !== null && !in_array(strlen($cpfCnpj), [11, 14], true)) {
            return $this->json($response, ['error' => 'CPF/CNPJ inválido'], 422);
        }

        try {
            $customer = $this->em->getConnection()->fetchAssociative(
                'INSERT INTO customers (tenant_id, name, cpf_cnpj, email, phone, mobile, notes)
                 VALUES (:tenant_id, :name, :cpf_cnpj, :email, :phone, :mobile, :notes)
                 RETURNING id, name, cpf_cnpj, email, phone, mobile',
                [
                    'tenant_id' => $tenantId,
                    'name' => $name,
                    'cpf_cnpj' => $cpfCnpj,
                    'email' => !empty($data['email']) ? (string) $data['email'] : null,
                    'phone' => !empty($data['phone']) ? (string) $data['phone'] : null,
                    'mobile' => !empty($data['mobile']) ? (string) $data['mobile'] : null,
                    'notes' => !empty($data['notes']) ? (string) $data['notes'] : null,
                ]
            );
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => 'Erro ao criar cliente. CPF/CNPJ pode estar duplicado.'], 409);
        }

        return $this->json($response, ['customer' => $customer], 201);
    }

    /** @param array<string, string> $args */
    public function updateCustomer(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        $customerId = (string) $args['id'];
        $data = (array) ($request->getParsedBody() ?? []);

        $cpfCnpj = !empty($data['cpf_cnpj']) ? preg_replace('/\D/', '', (string) $data['cpf_cnpj']) : null;

        $updated = $this->em->getConnection()->fetchAssociative(
            'UPDATE customers
             SET name = :name, cpf_cnpj = :cpf_cnpj, email = :email, phone = :phone, mobile = :mobile, notes = :notes, updated_at = now()
             WHERE id = :id AND tenant_id = :tenant_id
             RETURNING id, name, cpf_cnpj, email, phone, mobile',
            [
                'id' => $customerId,
                'tenant_id' => $tenantId,
                'name' => trim((string) ($data['name'] ?? '')),
                'cpf_cnpj' => $cpfCnpj,
                'email' => !empty($data['email']) ? (string) $data['email'] : null,
                'phone' => !empty($data['phone']) ? (string) $data['phone'] : null,
                'mobile' => !empty($data['mobile']) ? (string) $data['mobile'] : null,
                'notes' => !empty($data['notes']) ? (string) $data['notes'] : null,
            ]
        );

        if (!$updated) {
            return $this->json($response, ['error' => 'Cliente não encontrado'], 404);
        }

        return $this->json($response, ['customer' => $updated]);
    }

    /** @param array<string, string> $args */
    public function deleteCustomer(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        $customerId = (string) $args['id'];

        $affected = $this->em->getConnection()->executeStatement(
            'UPDATE customers SET is_active = false, updated_at = now() WHERE id = :id AND tenant_id = :tenant_id',
            ['id' => $customerId, 'tenant_id' => $tenantId]
        );

        if ($affected === 0) {
            return $this->json($response, ['error' => 'Cliente não encontrado'], 404);
        }

        return $this->json($response, ['message' => 'Cliente desativado']);
    }

    /** @param array<string, string> $args */
    public function getCustomer(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        $customerId = (string) $args['id'];

        $customer = $this->em->getConnection()->fetchAssociative(
            'SELECT * FROM customers WHERE id = :id AND tenant_id = :tenant_id',
            ['id' => $customerId, 'tenant_id' => $tenantId]
        );

        if (!$customer) {
            return $this->json($response, ['error' => 'Cliente não encontrado'], 404);
        }

        $vehicles = $this->em->getConnection()->fetchAllAssociative(
            'SELECT id, plate, brand, model, year, color, chassis FROM vehicles WHERE customer_id = :id AND is_active = true ORDER BY created_at DESC',
            ['id' => $customerId]
        );

        $customer['vehicles'] = $vehicles;

        return $this->json($response, ['customer' => $customer]);
    }

    public function createVehicle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        $data = (array) ($request->getParsedBody() ?? []);

        $customerId = (string) ($data['customer_id'] ?? '');
        $plate = strtoupper(trim((string) ($data['plate'] ?? '')));

        if ($customerId === '' || $plate === '') {
            return $this->json($response, ['error' => 'Cliente e placa são obrigatórios'], 422);
        }

        $customer = $this->em->getConnection()->fetchAssociative(
            'SELECT id FROM customers WHERE id = :id AND tenant_id = :tenant_id',
            ['id' => $customerId, 'tenant_id' => $tenantId]
        );

        if (!$customer) {
            return $this->json($response, ['error' => 'Cliente não encontrado'], 404);
        }

        try {
            $vehicle = $this->em->getConnection()->fetchAssociative(
                'INSERT INTO vehicles (tenant_id, customer_id, plate, brand, model, year, color, chassis, notes)
                 VALUES (:tenant_id, :customer_id, :plate, :brand, :model, :year, :color, :chassis, :notes)
                 RETURNING id, plate, brand, model, year, color',
                [
                    'tenant_id' => $tenantId,
                    'customer_id' => $customerId,
                    'plate' => $plate,
                    'brand' => !empty($data['brand']) ? (string) $data['brand'] : null,
                    'model' => !empty($data['model']) ? (string) $data['model'] : null,
                    'year' => !empty($data['year']) ? (int) $data['year'] : null,
                    'color' => !empty($data['color']) ? (string) $data['color'] : null,
                    'chassis' => !empty($data['chassis']) ? (string) $data['chassis'] : null,
                    'notes' => !empty($data['notes']) ? (string) $data['notes'] : null,
                ]
            );
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => 'Placa já cadastrada para outro cliente'], 409);
        }

        return $this->json($response, ['vehicle' => $vehicle], 201);
    }

    /** @param array<string, mixed> $payload */
    private function json(ResponseInterface $response, array $payload, int $status = 200): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($payload));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}