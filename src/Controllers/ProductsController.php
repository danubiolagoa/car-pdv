<?php

declare(strict_types=1);

namespace App\Controllers;

use Doctrine\ORM\EntityManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;
use Valitron\Validator;

class ProductsController
{
    public function __construct(
        private readonly EntityManager $em,
        private readonly Environment $twig,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write($this->twig->render('products/index.html.twig', [
            'user' => [
                'name' => $request->getAttribute('name'),
                'role' => $request->getAttribute('role'),
            ],
        ]));

        return $response->withHeader('Content-Type', 'text/html');
    }

    public function listProducts(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        $params = $request->getQueryParams();
        $search = trim((string) ($params['search'] ?? ''));

        $sql = 'SELECT p.id, p.sku, p.barcode, p.name, p.unit, p.cost_price, p.sale_price, p.current_stock,
                       p.min_stock, p.is_active, c.name AS category_name
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE p.tenant_id = :tenant_id';
        $bindings = ['tenant_id' => $tenantId];

        if ($search !== '') {
            $sql .= ' AND (p.name ILIKE :search OR p.sku ILIKE :search OR p.barcode ILIKE :search)';
            $bindings['search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY p.created_at DESC LIMIT 100';

        $products = $this->em->getConnection()->fetchAllAssociative($sql, $bindings);

        return $this->json($response, ['products' => $products]);
    }

    public function createProduct(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        $data = (array) ($request->getParsedBody() ?? []);

        $validator = new Validator($data);
        $validator->rule('required', ['sku', 'name', 'sale_price']);
        $validator->rule('numeric', ['cost_price', 'sale_price', 'current_stock', 'min_stock']);

        if (!$validator->validate()) {
            return $this->json($response, ['errors' => $validator->errors()], 422);
        }

        try {
            $product = $this->em->getConnection()->fetchAssociative(
                'INSERT INTO products (tenant_id, category_id, sku, barcode, name, description, unit, cost_price, sale_price, min_stock, current_stock, location, is_service)
                 VALUES (:tenant_id, :category_id, :sku, :barcode, :name, :description, :unit, :cost_price, :sale_price, :min_stock, :current_stock, :location, false)
                 RETURNING id, sku, name, sale_price, current_stock',
                [
                    'tenant_id' => $tenantId,
                    'category_id' => !empty($data['category_id']) ? (string) $data['category_id'] : null,
                    'sku' => trim((string) $data['sku']),
                    'barcode' => !empty($data['barcode']) ? (string) $data['barcode'] : null,
                    'name' => trim((string) $data['name']),
                    'description' => !empty($data['description']) ? (string) $data['description'] : null,
                    'unit' => !empty($data['unit']) ? (string) $data['unit'] : 'UN',
                    'cost_price' => (float) ($data['cost_price'] ?? 0),
                    'sale_price' => (float) $data['sale_price'],
                    'min_stock' => (float) ($data['min_stock'] ?? 0),
                    'current_stock' => (float) ($data['current_stock'] ?? 0),
                    'location' => !empty($data['location']) ? (string) $data['location'] : null,
                ]
            );
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => 'Nao foi possivel criar o produto. Verifique se o SKU ja existe.'], 409);
        }

        return $this->json($response, ['product' => $product], 201);
    }

    /** @param array<string, string> $args */
    public function updateProduct(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        $productId = (string) $args['id'];
        $data = (array) ($request->getParsedBody() ?? []);

        $updated = $this->em->getConnection()->fetchAssociative(
            'UPDATE products
             SET category_id = :category_id, sku = :sku, barcode = :barcode, name = :name, description = :description,
                 unit = :unit, cost_price = :cost_price, sale_price = :sale_price, min_stock = :min_stock,
                 current_stock = :current_stock, location = :location, updated_at = now()
             WHERE id = :id AND tenant_id = :tenant_id
             RETURNING id, sku, name, sale_price, current_stock',
            [
                'id' => $productId,
                'tenant_id' => $tenantId,
                'category_id' => !empty($data['category_id']) ? (string) $data['category_id'] : null,
                'sku' => trim((string) $data['sku']),
                'barcode' => !empty($data['barcode']) ? (string) $data['barcode'] : null,
                'name' => trim((string) $data['name']),
                'description' => !empty($data['description']) ? (string) $data['description'] : null,
                'unit' => !empty($data['unit']) ? (string) $data['unit'] : 'UN',
                'cost_price' => (float) ($data['cost_price'] ?? 0),
                'sale_price' => (float) ($data['sale_price'] ?? 0),
                'min_stock' => (float) ($data['min_stock'] ?? 0),
                'current_stock' => (float) ($data['current_stock'] ?? 0),
                'location' => !empty($data['location']) ? (string) $data['location'] : null,
            ]
        );

        if (!$updated) {
            return $this->json($response, ['error' => 'Produto nao encontrado'], 404);
        }

        return $this->json($response, ['product' => $updated]);
    }

    /** @param array<string, string> $args */
    public function deleteProduct(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        $productId = (string) $args['id'];

        $affected = $this->em->getConnection()->executeStatement(
            'UPDATE products SET is_active = false, updated_at = now() WHERE id = :id AND tenant_id = :tenant_id',
            ['id' => $productId, 'tenant_id' => $tenantId]
        );

        if ($affected === 0) {
            return $this->json($response, ['error' => 'Produto nao encontrado'], 404);
        }

        return $this->json($response, ['message' => 'Produto desativado']);
    }

    public function listCategories(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        $categories = $this->em->getConnection()->fetchAllAssociative(
            'SELECT id, name, slug FROM categories WHERE tenant_id = :tenant_id AND is_active = true ORDER BY name',
            ['tenant_id' => $tenantId]
        );

        return $this->json($response, ['categories' => $categories]);
    }

    public function createCategory(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        $data = (array) ($request->getParsedBody() ?? []);
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            return $this->json($response, ['error' => 'Nome da categoria e obrigatorio'], 422);
        }

        $category = $this->em->getConnection()->fetchAssociative(
            'INSERT INTO categories (tenant_id, name, slug)
             VALUES (:tenant_id, :name, :slug)
             ON CONFLICT (tenant_id, slug) DO UPDATE SET name = EXCLUDED.name
             RETURNING id, name, slug',
            ['tenant_id' => $tenantId, 'name' => $name, 'slug' => $this->slugify($name)]
        );

        return $this->json($response, ['category' => $category], 201);
    }

    /** @param array<string, mixed> $payload */
    private function json(ResponseInterface $response, array $payload, int $status = 200): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($payload));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }

    private function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text) ?: '';
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text) ?: $text;
        $text = preg_replace('~[^-\w]+~', '', $text) ?: '';
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text) ?: '';

        return strtolower($text);
    }
}
