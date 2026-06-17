<?php

declare(strict_types=1);

namespace App\Controllers;

use Doctrine\ORM\EntityManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;
use Valitron\Validator;

class SalesController
{
    public function __construct(
        private readonly EntityManager $em,
        private readonly Environment $twig,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write($this->twig->render('sales/index.html.twig', [
            'user' => [
                'name' => $request->getAttribute('name'),
                'role' => $request->getAttribute('role'),
            ],
        ]));

        return $response->withHeader('Content-Type', 'text/html');
    }

    public function listSales(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        $params = $request->getQueryParams();
        $status = trim((string) ($params['status'] ?? ''));

        $sql = 'SELECT s.id, s.sale_number, s.status, s.subtotal, s.discount, s.total, s.commission, s.created_at, s.completed_at,
                       c.name AS customer_name,
                       u.name AS seller_name
                FROM sales s
                LEFT JOIN customers c ON c.id = s.customer_id
                INNER JOIN users u ON u.id = s.seller_id
                WHERE s.tenant_id = :tenant_id';
        $bindings = ['tenant_id' => $tenantId];

        if ($status !== '') {
            $sql .= ' AND s.status = :status';
            $bindings['status'] = $status;
        }

        $sql .= ' ORDER BY s.created_at DESC LIMIT 100';

        $sales = $this->em->getConnection()->fetchAllAssociative($sql, $bindings);

        return $this->json($response, ['sales' => $sales]);
    }

    /** @param array<string, string> $args */
    public function getSale(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        $saleId = (string) $args['id'];

        $sale = $this->em->getConnection()->fetchAssociative(
            'SELECT s.*, c.name AS customer_name, u.name AS seller_name
             FROM sales s
             LEFT JOIN customers c ON c.id = s.customer_id
             INNER JOIN users u ON u.id = s.seller_id
             WHERE s.id = :id AND s.tenant_id = :tenant_id',
            ['id' => $saleId, 'tenant_id' => $tenantId]
        );

        if (!$sale) {
            return $this->json($response, ['error' => 'Venda não encontrada'], 404);
        }

        $items = $this->em->getConnection()->fetchAllAssociative(
            'SELECT id, product_id, name, quantity, unit_price, discount, total FROM sale_items WHERE sale_id = :id',
            ['id' => $saleId]
        );

        $payments = $this->em->getConnection()->fetchAllAssociative(
            'SELECT id, method, amount, installments, change_amount, reference, status FROM payments WHERE sale_id = :id',
            ['id' => $saleId]
        );

        $sale['items'] = $items;
        $sale['payments'] = $payments;

        return $this->json($response, ['sale' => $sale]);
    }

    public function createSale(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        $sellerId = (string) $request->getAttribute('user_id');
        $data = (array) ($request->getParsedBody() ?? []);

        $items = $data['items'] ?? [];
        $payments = $data['payments'] ?? [];
        $customerId = !empty($data['customer_id']) ? (string) $data['customer_id'] : null;
        $discount = (float) ($data['discount'] ?? 0);
        $discountType = ($data['discount_type'] ?? 'value') === 'percent' ? 'percent' : 'value';
        $notes = !empty($data['notes']) ? (string) $data['notes'] : null;

        if (!is_array($items) || count($items) === 0) {
            return $this->json($response, ['error' => 'Adicione pelo menos um item'], 422);
        }

        if (!is_array($payments) || count($payments) === 0) {
            return $this->json($response, ['error' => 'Informe pelo menos uma forma de pagamento'], 422);
        }

        $conn = $this->em->getConnection();
        $conn->beginTransaction();

        try {
            $subtotal = 0.0;
            $processedItems = [];

            foreach ($items as $item) {
                $productId = (string) ($item['product_id'] ?? '');
                $quantity = (float) ($item['quantity'] ?? 0);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $itemDiscount = (float) ($item['discount'] ?? 0);

                if ($productId === '' || $quantity <= 0 || $unitPrice < 0) {
                    throw new \InvalidArgumentException('Item inválido no carrinho');
                }

                $product = $conn->fetchAssociative(
                    'SELECT id, name, current_stock, cost_price FROM products WHERE id = :id AND tenant_id = :tenant_id FOR UPDATE',
                    ['id' => $productId, 'tenant_id' => $tenantId]
                );

                if (!$product) {
                    throw new \RuntimeException('Produto não encontrado: ' . $productId);
                }

                if ((float) $product['current_stock'] < $quantity) {
                    throw new \RuntimeException(sprintf(
                        'Estoque insuficiente para "%s". Disponível: %s',
                        $product['name'],
                        $product['current_stock']
                    ));
                }

                $itemTotal = max(0, ($unitPrice * $quantity) - $itemDiscount);
                $subtotal += $itemTotal;

                $processedItems[] = [
                    'product_id' => $productId,
                    'name' => $product['name'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => $itemDiscount,
                    'total' => $itemTotal,
                    'cost_price' => $product['cost_price'],
                ];
            }

            $discountValue = $discountType === 'percent'
                ? ($subtotal * $discount / 100)
                : $discount;
            $total = max(0, $subtotal - $discountValue);

            $totalPaid = 0.0;
            foreach ($payments as $pay) {
                $totalPaid += (float) ($pay['amount'] ?? 0);
            }

            if (abs($totalPaid - $total) > 0.01) {
                throw new \RuntimeException(sprintf(
                    'Pagamento (R$ %.2f) não confere com total (R$ %.2f)',
                    $totalPaid,
                    $total
                ));
            }

            $seller = $conn->fetchAssociative(
                'SELECT commission_rate FROM users WHERE id = :id',
                ['id' => $sellerId]
            );
            $commissionRate = $seller ? (float) $seller['commission_rate'] : 0;
            $commission = round($total * $commissionRate / 100, 2);

            $year = date('Y');
            $sequence = (int) $conn->fetchOne(
                "SELECT COUNT(*) FROM sales WHERE tenant_id = :tenant_id AND EXTRACT(YEAR FROM created_at) = :year",
                ['tenant_id' => $tenantId, 'year' => $year]
            ) + 1;
            $saleNumber = sprintf('%s-%04d-%06d', $year, random_int(0, 9999), $sequence);

            $sale = $conn->fetchAssociative(
                'INSERT INTO sales (tenant_id, customer_id, seller_id, sale_number, status, subtotal, discount, discount_type, total, commission, notes, completed_at)
                 VALUES (:tenant_id, :customer_id, :seller_id, :sale_number, :status, :subtotal, :discount, :discount_type, :total, :commission, :notes, now())
                 RETURNING id, sale_number, total',
                [
                    'tenant_id' => $tenantId,
                    'customer_id' => $customerId,
                    'seller_id' => $sellerId,
                    'sale_number' => $saleNumber,
                    'status' => 'completed',
                    'subtotal' => $subtotal,
                    'discount' => $discountValue,
                    'discount_type' => $discountType,
                    'total' => $total,
                    'commission' => $commission,
                    'notes' => $notes,
                ]
            );

            $saleId = $sale['id'];

            foreach ($processedItems as $it) {
                $conn->executeStatement(
                    'INSERT INTO sale_items (sale_id, product_id, name, quantity, unit_price, discount, total, cost_price)
                     VALUES (:sale_id, :product_id, :name, :quantity, :unit_price, :discount, :total, :cost_price)',
                    array_merge(['sale_id' => $saleId], $it)
                );

                $previousStock = (float) $conn->fetchOne(
                    'SELECT current_stock FROM products WHERE id = :id',
                    ['id' => $it['product_id']]
                );
                $newStock = $previousStock - $it['quantity'];

                $conn->executeStatement(
                    'UPDATE products SET current_stock = :s, updated_at = now() WHERE id = :id',
                    ['s' => $newStock, 'id' => $it['product_id']]
                );

                $conn->executeStatement(
                    'INSERT INTO inventory_movements (tenant_id, product_id, user_id, type, quantity, previous_stock, new_stock, reference_type, reference_id)
                     VALUES (:tenant_id, :product_id, :user_id, :type, :quantity, :previous_stock, :new_stock, :reference_type, :reference_id)',
                    [
                        'tenant_id' => $tenantId,
                        'product_id' => $it['product_id'],
                        'user_id' => $sellerId,
                        'type' => 'sale',
                        'quantity' => $it['quantity'],
                        'previous_stock' => $previousStock,
                        'new_stock' => $newStock,
                        'reference_type' => 'sale',
                        'reference_id' => $saleId,
                    ]
                );
            }

            foreach ($payments as $pay) {
                $method = (string) ($pay['method'] ?? 'cash');
                $amount = (float) ($pay['amount'] ?? 0);
                $installments = (int) ($pay['installments'] ?? 1);
                $change = (float) ($pay['change_amount'] ?? 0);
                $reference = !empty($pay['reference']) ? (string) $pay['reference'] : null;

                if (!in_array($method, ['cash', 'debit', 'credit', 'pix', 'transfer', 'check', 'other'], true)) {
                    throw new \InvalidArgumentException('Forma de pagamento inválida');
                }

                $conn->executeStatement(
                    'INSERT INTO payments (sale_id, method, amount, installments, change_amount, reference, status)
                     VALUES (:sale_id, :method, :amount, :installments, :change, :reference, :status)',
                    [
                        'sale_id' => $saleId,
                        'method' => $method,
                        'amount' => $amount,
                        'installments' => max(1, $installments),
                        'change' => $change,
                        'reference' => $reference,
                        'status' => 'confirmed',
                    ]
                );
            }

            if ($customerId !== null) {
                $conn->executeStatement(
                    'UPDATE customers SET total_purchases = total_purchases + :total, updated_at = now() WHERE id = :id',
                    ['total' => $total, 'id' => $customerId]
                );
            }

            $conn->commit();

            return $this->json($response, ['sale' => $sale], 201);
        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            return $this->json($response, ['error' => $e->getMessage()], 422);
        }
    }

    /** @param array<string, string> $args */
    public function cancelSale(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenantId = (string) $request->getAttribute('tenant_id');
        $saleId = (string) $args['id'];

        $conn = $this->em->getConnection();
        $conn->beginTransaction();

        try {
            $sale = $conn->fetchAssociative(
                'SELECT id, status FROM sales WHERE id = :id AND tenant_id = :tenant_id FOR UPDATE',
                ['id' => $saleId, 'tenant_id' => $tenantId]
            );

            if (!$sale) {
                $conn->rollBack();
                return $this->json($response, ['error' => 'Venda não encontrada'], 404);
            }

            if ($sale['status'] === 'cancelled') {
                $conn->rollBack();
                return $this->json($response, ['error' => 'Venda já cancelada'], 409);
            }

            $items = $conn->fetchAllAssociative(
                'SELECT product_id, quantity FROM sale_items WHERE sale_id = :id',
                ['id' => $saleId]
            );

            foreach ($items as $item) {
                $currentStock = (float) $conn->fetchOne(
                    'SELECT current_stock FROM products WHERE id = :id',
                    ['id' => $item['product_id']]
                );
                $newStock = $currentStock + (float) $item['quantity'];

                $conn->executeStatement(
                    'UPDATE products SET current_stock = :s, updated_at = now() WHERE id = :id',
                    ['s' => $newStock, 'id' => $item['product_id']]
                );

                $conn->executeStatement(
                    'INSERT INTO inventory_movements (tenant_id, product_id, user_id, type, quantity, previous_stock, new_stock, reference_type, reference_id, notes)
                     VALUES (:tenant_id, :product_id, :user_id, :type, :quantity, :previous_stock, :new_stock, :reference_type, :reference_id, :notes)',
                    [
                        'tenant_id' => $tenantId,
                        'product_id' => $item['product_id'],
                        'user_id' => (string) $request->getAttribute('user_id'),
                        'type' => 'return',
                        'quantity' => $item['quantity'],
                        'previous_stock' => $currentStock,
                        'new_stock' => $newStock,
                        'reference_type' => 'sale',
                        'reference_id' => $saleId,
                        'notes' => 'Cancelamento de venda',
                    ]
                );
            }

            $conn->executeStatement(
                'UPDATE sales SET status = :status, cancelled_at = now() WHERE id = :id',
                ['status' => 'cancelled', 'id' => $saleId]
            );

            $conn->commit();

            return $this->json($response, ['message' => 'Venda cancelada e estoque devolvido']);
        } catch (\Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            return $this->json($response, ['error' => 'Erro ao cancelar: ' . $e->getMessage()], 500);
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