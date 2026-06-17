<?php

declare(strict_types=1);

namespace App\Controllers;

use Doctrine\ORM\EntityManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;
use Twig\Environment;

class HomeController
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
        $html = $this->twig->render('home/index.html.twig', [
            'app_name' => $_ENV['APP_NAME'] ?? 'CAR-PDV',
        ]);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }

    public function health(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $this->em->getConnection()->executeQuery('SELECT 1');
            $status = 'ok';
            $db = 'connected';
        } catch (\Throwable $e) {
            $status = 'error';
            $db = 'disconnected: ' . $e->getMessage();
        }

        $response->getBody()->write(json_encode([
            'status' => $status,
            'database' => $db,
            'timestamp' => (new \DateTimeImmutable())->format('c'),
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
