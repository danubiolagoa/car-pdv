<?php

declare(strict_types=1);

namespace App\Controllers;

use Doctrine\ORM\EntityManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;

class InventoryController
{
    public function __construct(
        private readonly EntityManager $em,
        private readonly Environment $twig,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write($this->twig->render('inventory/index.html.twig', [
            'user' => [
                'name' => $request->getAttribute('name'),
                'role' => $request->getAttribute('role'),
            ],
        ]));

        return $response->withHeader('Content-Type', 'text/html');
    }

    public function listMovements(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        $params = $request->getQueryParams();
        $type = trim((string) ($params['type'] ?? ''));
        $limit = min(200, max(10, (int) ($params['limit'] ?? 100)));

        $sql = 'SELECT m.id, m.type, m.quantity, m.previous_stock, m.new_stock, m.notes, m.created_at,
                       p.sku, p.name AS product_name,
                       u.name AS user_name
                FROM inventory_movements m
                INNER JOIN products p ON p.id = m.product_id
                LEFT JOIN users u ON u.id = m.user_id
                WHERE m.tenant_id = :tenant_id';
        $bindings = ['tenant_id' => $tenantId];

        if ($type !== '') {
            $sql .= ' AND m.type = :type';
            $bindings['type'] = $type;
        }

        $sql .= ' ORDER BY m.created_at DESC LIMIT ' . $limit;

        $movements = $this->em->getConnection()->fetchAllAssociative($sql, $bindings);

        return $this->json($response, ['movements' => $movements]);
    }

    public function listLowStock(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        $products = $this->em->getConnection()->fetchAllAssociative(
            'SELECT id, sku, name, current_stock, min_stock, unit
             FROM products
             WHERE tenant_id = :tenant_id AND is_active = true AND current_stock <= min_stock
             ORDER BY (current_stock - min_stock) ASC, name
             LIMIT 50',
            ['tenant_id' => $tenantId]
        );

        return $this->json($response, ['products' => $products]);
    }

    public function createMovement(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        $userId = (string) $request->getAttribute('user_id');
        $data = (array) ($request->getParsedBody() ?? []);

        $productId = trim((string) ($data['product_id'] ?? ''));
        $type = trim((string) ($data['type'] ?? ''));
        $quantity = (float) ($data['quantity'] ?? 0);
        $notes = !empty($data['notes']) ? (string) $data['notes'] : null;
        $costPrice = isset($data['cost_price']) ? (float) $data['cost_price'] : null;

        if ($productId === '' || $quantity <= 0) {
            return $this->json($response, ['error' => 'Produto e quantidade (>0) são obrigatórios'], 422);
        }

        $allowedTypes = ['in', 'out', 'adjustment', 'return'];
        if (!in_array($type, $allowedTypes, true)) {
            return $this->json($response, ['error' => 'Tipo inválido. Use: in, out, adjustment, return'], 422);
        }

        $conn = $this->em->getConnection();
        $conn->beginTransaction();

        try {
            $product = $conn->fetchAssociative(
                'SELECT id, current_stock, cost_price FROM products WHERE id = :id AND tenant_id = :tenant_id FOR UPDATE',
                ['id' => $productId, 'tenant_id' => $tenantId]
            );

            if (!$product) {
                $conn->rollBack();
                return $this->json($response, ['error' => 'Produto não encontrado'], 404);
            }

            $previousStock = (float) $product['current_stock'];
            $newStock = match ($type) {
                'in' => $previousStock + $quantity,
                'out' => $previousStock - $quantity,
                'return' => $previousStock + $quantity,
                'adjustment' => $quantity,
            };

            if ($newStock < 0) {
                $conn->rollBack();
                return $this->json($response, ['error' => 'Estoque não pode ficar negativo. Atual: ' . $previousStock], 422);
            }

            $conn->executeStatement(
                'UPDATE products SET current_stock = :new_stock, updated_at = now() WHERE id = :id',
                ['new_stock' => $newStock, 'id' => $productId]
            );

            $movement = $conn->fetchAssociative(
                'INSERT INTO inventory_movements (tenant_id, product_id, user_id, type, quantity, previous_stock, new_stock, cost_price, notes)
                 VALUES (:tenant_id, :product_id, :user_id, :type, :quantity, :previous_stock, :new_stock, :cost_price, :notes)
                 RETURNING id, type, quantity, previous_stock, new_stock, notes, created_at',
                [
                    'tenant_id' => $tenantId,
                    'product_id' => $productId,
                    'user_id' => $userId,
                    'type' => $type,
                    'quantity' => $quantity,
                    'previous_stock' => $previousStock,
                    'new_stock' => $newStock,
                    'cost_price' => $costPrice,
                    'notes' => $notes,
                ]
            );

            $conn->commit();

            return $this->json($response, ['movement' => $movement, 'new_stock' => $newStock], 201);
        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            return $this->json($response, ['error' => 'Erro ao registrar movimentação: ' . $e->getMessage()], 500);
        }
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