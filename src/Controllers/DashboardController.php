<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;

class DashboardController
{
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $tenantId = $request->getAttribute('tenant_id');
        $name = $request->getAttribute('name');
        $role = $request->getAttribute('role');

        $html = $this->twig->render('dashboard/index.html.twig', [
            'app_name' => $_ENV['APP_NAME'] ?? 'CAR-PDV',
            'user' => [
                'id' => $userId,
                'name' => $name,
                'role' => $role,
            ],
            'tenant_id' => $tenantId,
        ]);

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }
}
