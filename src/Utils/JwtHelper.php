<?php

declare(strict_types=1);

namespace App\Utils;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtHelper
{
    private string $secret;
    private string $issuer;
    private string $audience;
    private int $expiration;

    /** @param array{secret: string, issuer: string, audience: string, expiration: int} $config */
    public function __construct(array $config)
    {
        $this->secret = $config['secret'];
        $this->issuer = $config['issuer'];
        $this->audience = $config['audience'];
        $this->expiration = $config['expiration'];
    }

    /** @param array{user_id: string, tenant_id: string, email: string, role: string, name: string} $payload */
    public function generateToken(array $payload): string
    {
        $now = time();
        $claims = [
            'iat' => $now,
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'exp' => $now + $this->expiration,
            'sub' => $payload['user_id'],
            'tenant_id' => $payload['tenant_id'],
            'email' => $payload['email'],
            'role' => $payload['role'],
            'name' => $payload['name'],
        ];

        return JWT::encode($claims, $this->secret, 'HS256');
    }

    public function decodeToken(string $token): object
    {
        return JWT::decode($token, new Key($this->secret, 'HS256'));
    }
}
