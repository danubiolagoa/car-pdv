<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Utils\JwtHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AuthMiddleware implements MiddlewareInterface
{
    private JwtHelper $jwtHelper;

    public function __construct(JwtHelper $jwtHelper)
    {
        $this->jwtHelper = $jwtHelper;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authHeader = $request->getHeaderLine('Authorization');
        $cookies = $request->getCookieParams();

        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
        } elseif (!empty($cookies['car_pdv_token'])) {
            $token = $cookies['car_pdv_token'];
        } else {
            $response = new Response();
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response
                ->withStatus(401)
                ->withHeader('Content-Type', 'application/json');
        }

        try {
            $decoded = $this->jwtHelper->decodeToken($token);
            $request = $request
                ->withAttribute('user_id', $decoded->sub)
                ->withAttribute('tenant_id', $decoded->tenant_id)
                ->withAttribute('email', $decoded->email)
                ->withAttribute('role', $decoded->role)
                ->withAttribute('name', $decoded->name);

            return $handler->handle($request);
        } catch (\Exception $e) {
            $response = new Response();
            $response->getBody()->write(json_encode(['error' => 'Invalid token']));
            return $response
                ->withStatus(401)
                ->withHeader('Content-Type', 'application/json');
        }
    }
}
