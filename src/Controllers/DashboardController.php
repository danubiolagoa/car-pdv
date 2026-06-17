<?php

declare(strict_types=1);

namespace App\Controllers;

use Doctrine\ORM\EntityManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;

class DashboardController
{
    private Environment $twig;
    private EntityManager $em;

    public function __construct(Environment $twig, EntityManager $em)
    {
        $this->twig = $twig;
        $this->em = $em;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tenantId = $request->getAttribute('tenant_id');
        $name = $request->getAttribute('name');
        $role = $request->getAttribute('role');

        $conn = $this->em->getConnection();
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $salesToday = $conn->fetchAssociative(
            "SELECT COALESCE(COUNT(*), 0) AS count, COALESCE(SUM(total), 0) AS total
             FROM sales WHERE tenant_id = ? AND status = 'completed' AND DATE(completed_at) = ?",
            [$tenantId, $today]
        );

        $pendingSales = $conn->fetchOne(
            "SELECT COUNT(*) FROM sales WHERE tenant_id = ? AND status = 'open'",
            [$tenantId]
        );

        $lowStock = $conn->fetchOne(
            "SELECT COUNT(*) FROM products WHERE tenant_id = ? AND is_active = true AND current_stock < min_stock",
            [$tenantId]
        );

        $appointmentsToday = $conn->fetchOne(
            "SELECT COUNT(*) FROM appointments WHERE tenant_id = ? AND DATE(scheduled_at) = ? AND status NOT IN ('cancelled', 'no_show')",
            [$tenantId, $today]
        );

        $appointmentsPending = $conn->fetchOne(
            "SELECT COUNT(*) FROM appointments WHERE tenant_id = ? AND status = 'scheduled'",
            [$tenantId]
        );

        $html = $this->twig->render('dashboard/index.html.twig', [
            'app_name' => $_ENV['APP_NAME'] ?? 'CAR-PDV',
            'user' => [
                'id' => $request->getAttribute('user_id'),
                'name' => $name,
                'role' => $role,
            ],
            'tenant_id' => $tenantId,
            'kpi' => [
                'sales_today' => (float) ($salesToday['total'] ?? 0),
                'sales_today_count' => (int) ($salesToday['count'] ?? 0),
                'pending_sales' => (int) ($pendingSales ?? 0),
                'low_stock' => (int) ($lowStock ?? 0),
                'appointments_today' => (int) ($appointmentsToday ?? 0),
                'appointments_pending' => (int) ($appointmentsPending ?? 0),
            ],
        ]);

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }
}
