<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Utils\JwtHelper;
use Doctrine\ORM\EntityManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;
use Twig\Environment;
use Valitron\Validator;

class AuthController
{
    private EntityManager $em;
    private JwtHelper $jwtHelper;
    private Environment $twig;

    public function __construct(EntityManager $em, JwtHelper $jwtHelper, Environment $twig)
    {
        $this->em = $em;
        $this->jwtHelper = $jwtHelper;
        $this->twig = $twig;
    }

    public function showLogin(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write($this->twig->render('auth/login.html.twig'));
        return $response->withHeader('Content-Type', 'text/html');
    }

    public function showRegister(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write($this->twig->render('auth/register.html.twig'));
        return $response->withHeader('Content-Type', 'text/html');
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody() ?? [];

        $validator = new Validator($data);
        $validator->rule('required', ['email', 'password']);
        $validator->rule('email', 'email');

        if (!$validator->validate()) {
            $response->getBody()->write(json_encode(['errors' => $validator->errors()]));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => strtolower($data['email'])]);

        if (!$user || !$user->isActive() || !$user->verifyPassword($data['password'])) {
            $response->getBody()->write(json_encode(['error' => 'Invalid credentials']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $user->setLastLoginAt(new \DateTimeImmutable());
        $this->em->flush();

        $token = $this->jwtHelper->generateToken([
            'user_id' => $user->getId(),
            'tenant_id' => $user->getTenant()->getId(),
            'email' => $user->getEmail(),
            'role' => $user->getRole(),
            'name' => $user->getName(),
        ]);

        $response->getBody()->write(json_encode([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'role' => $user->getRole(),
                'tenant' => [
                    'id' => $user->getTenant()->getId(),
                    'name' => $user->getTenant()->getName(),
                ],
            ],
        ]));

        return $this->withAuthCookie($response, $token)
            ->withHeader('Content-Type', 'application/json');
    }

    public function register(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->getParsedBody() ?? [];

        $validator = new Validator($data);
        $validator->rule('required', ['tenant_name', 'slug', 'name', 'email', 'password']);
        $validator->rule('email', 'email');
        $validator->rule('lengthMin', 'password', 6);
        $validator->rule('slug', 'slug');

        if (!$validator->validate()) {
            $response->getBody()->write(json_encode(['errors' => $validator->errors()]));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        $existingTenant = $this->em->getRepository(Tenant::class)->findOneBy(['slug' => $data['slug']]);
        if ($existingTenant) {
            $response->getBody()->write(json_encode(['error' => 'Slug already in use']));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        $existingUser = $this->em->getRepository(User::class)->findOneBy(['email' => strtolower($data['email'])]);
        if ($existingUser) {
            $response->getBody()->write(json_encode(['error' => 'Email already registered']));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        $tenant = (new Tenant())
            ->setName($data['tenant_name'])
            ->setSlug($this->slugify($data['slug']))
            ->setBusinessType($data['business_type'] ?? 'automotive')
            ->setPlan('free');

        $user = (new User())
            ->setTenant($tenant)
            ->setName($data['name'])
            ->setEmail($data['email'])
            ->setPasswordHash(password_hash($data['password'], PASSWORD_ARGON2ID))
            ->setRole('admin');

        $this->em->persist($tenant);
        $this->em->persist($user);
        $this->em->flush();

        $token = $this->jwtHelper->generateToken([
            'user_id' => $user->getId(),
            'tenant_id' => $tenant->getId(),
            'email' => $user->getEmail(),
            'role' => $user->getRole(),
            'name' => $user->getName(),
        ]);

        $response->getBody()->write(json_encode([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'role' => $user->getRole(),
                'tenant' => [
                    'id' => $tenant->getId(),
                    'name' => $tenant->getName(),
                ],
            ],
        ]));

        return $this->withAuthCookie($response, $token)
            ->withStatus(201)
            ->withHeader('Content-Type', 'application/json');
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write(json_encode(['message' => 'Logged out']));
        return $response
            ->withHeader('Set-Cookie', 'car_pdv_token=; Path=/; Max-Age=0; SameSite=Lax; HttpOnly')
            ->withHeader('Content-Type', 'application/json');
    }

    private function withAuthCookie(ResponseInterface $response, string $token): ResponseInterface
    {
        $secure = ($_ENV['APP_ENV'] ?? 'production') !== 'development' ? '; Secure' : '';

        return $response->withHeader(
            'Set-Cookie',
            sprintf('car_pdv_token=%s; Path=/; Max-Age=%d; SameSite=Lax; HttpOnly%s', $token, (int) ($_ENV['JWT_EXPIRATION'] ?? 86400), $secure)
        );
    }

    private function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        return strtolower($text);
    }
}
