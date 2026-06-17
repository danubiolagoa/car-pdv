<?php

declare(strict_types=1);

namespace App\Controllers;

use Doctrine\ORM\EntityManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;

class AppointmentsController
{
    public function __construct(
        private readonly EntityManager $em,
        private readonly Environment $twig,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write($this->twig->render('appointments/index.html.twig', [
            'user' => [
                'name' => $request->getAttribute('name'),
                'role' => $request->getAttribute('role'),
            ],
        ]));

        return $response->withHeader('Content-Type', 'text/html');
    }

    public function listAppointments(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        $params = $request->getQueryParams();
        $date = trim((string) ($params['date'] ?? date('Y-m-d')));

        $appointments = $this->em->getConnection()->fetchAllAssociative(
            'SELECT a.id, a.status, a.scheduled_at, a.estimated_end, a.notes, a.total_price,
                    c.name AS customer_name, c.phone AS customer_phone,
                    v.plate, v.brand, v.model,
                    s.name AS service_name, s.duration_minutes, s.price AS service_price,
                    m.name AS mechanic_name
             FROM appointments a
             INNER JOIN customers c ON c.id = a.customer_id
             LEFT JOIN vehicles v ON v.id = a.vehicle_id
             LEFT JOIN services s ON s.id = a.service_id
             LEFT JOIN users m ON m.id = a.mechanic_id
             WHERE a.tenant_id = :tenant_id
               AND DATE(a.scheduled_at) = :date
             ORDER BY a.scheduled_at ASC',
            ['tenant_id' => $tenantId, 'date' => $date]
        );

        return $this->json($response, ['appointments' => $appointments]);
    }

    public function createAppointment(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        $data = (array) ($request->getParsedBody() ?? []);

        $customerId = (string) ($data['customer_id'] ?? '');
        $serviceId = !empty($data['service_id']) ? (string) $data['service_id'] : null;
        $vehicleId = !empty($data['vehicle_id']) ? (string) $data['vehicle_id'] : null;
        $mechanicId = !empty($data['mechanic_id']) ? (string) $data['mechanic_id'] : null;
        $scheduledAt = (string) ($data['scheduled_at'] ?? '');
        $notes = !empty($data['notes']) ? (string) $data['notes'] : null;

        if ($customerId === '' || $scheduledAt === '') {
            return $this->json($response, ['error' => 'Cliente e data/hora são obrigatórios'], 422);
        }

        $conn = $this->em->getConnection();

        $customer = $conn->fetchAssociative(
            'SELECT id FROM customers WHERE id = :id AND tenant_id = :tenant_id',
            ['id' => $customerId, 'tenant_id' => $tenantId]
        );
        if (!$customer) {
            return $this->json($response, ['error' => 'Cliente não encontrado'], 404);
        }

        $duration = 60;
        $totalPrice = 0;

        if ($serviceId !== null) {
            $service = $conn->fetchAssociative(
                'SELECT duration_minutes, price FROM services WHERE id = :id AND tenant_id = :tenant_id',
                ['id' => $serviceId, 'tenant_id' => $tenantId]
            );
            if ($service) {
                $duration = (int) $service['duration_minutes'];
                $totalPrice = (float) $service['price'];
            }
        }

        $estimatedEnd = (new \DateTimeImmutable($scheduledAt))
            ->modify('+' . $duration . ' minutes')
            ->format('Y-m-d H:i:s');

        $appointment = $conn->fetchAssociative(
            'INSERT INTO appointments (tenant_id, customer_id, vehicle_id, mechanic_id, service_id, status, scheduled_at, estimated_end, notes, total_price)
             VALUES (:tenant_id, :customer_id, :vehicle_id, :mechanic_id, :service_id, :status, :scheduled_at, :estimated_end, :notes, :total_price)
             RETURNING id, scheduled_at, estimated_end, status',
            [
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
                'vehicle_id' => $vehicleId,
                'mechanic_id' => $mechanicId,
                'service_id' => $serviceId,
                'status' => 'scheduled',
                'scheduled_at' => $scheduledAt,
                'estimated_end' => $estimatedEnd,
                'notes' => $notes,
                'total_price' => $totalPrice,
            ]
        );

        return $this->json($response, ['appointment' => $appointment], 201);
    }

    /** @param array<string, string> $args */
    public function updateStatus(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        $appointmentId = (string) $args['id'];
        $data = (array) ($request->getParsedBody() ?? []);
        $status = (string) ($data['status'] ?? '');

        $allowed = ['scheduled', 'in_progress', 'completed', 'cancelled', 'no_show'];
        if (!in_array($status, $allowed, true)) {
            return $this->json($response, ['error' => 'Status inválido'], 422);
        }

        $now = $status === 'in_progress' ? ', actual_start = now()' : '';
        $now .= $status === 'completed' ? ', actual_end = now()' : '';

        $sql = 'UPDATE appointments SET status = :status' . $now . ', updated_at = now()
                WHERE id = :id AND tenant_id = :tenant_id RETURNING id, status';

        $updated = $this->em->getConnection()->fetchAssociative($sql, [
            'status' => $status,
            'id' => $appointmentId,
            'tenant_id' => $tenantId,
        ]);

        if (!$updated) {
            return $this->json($response, ['error' => 'Agendamento não encontrado'], 404);
        }

        return $this->json($response, ['appointment' => $updated]);
    }

    public function listServices(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        $services = $this->em->getConnection()->fetchAllAssociative(
            'SELECT id, name, duration_minutes, price, category FROM services WHERE tenant_id = :tenant_id AND is_active = true ORDER BY name',
            ['tenant_id' => $tenantId]
        );

        return $this->json($response, ['services' => $services]);
    }

    public function listMechanics(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        $mechanics = $this->em->getConnection()->fetchAllAssociative(
            "SELECT id, name FROM users WHERE tenant_id = :tenant_id AND role IN ('mechanic', 'manager', 'admin') AND is_active = true ORDER BY name",
            ['tenant_id' => $tenantId]
        );

        return $this->json($response, ['mechanics' => $mechanics]);
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