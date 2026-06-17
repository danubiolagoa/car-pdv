# Arquitetura - CAR-PDV

## 1. Stack Decisões

### Por que Slim 4 e não Laravel?
- Cold start mais rápido em serverless (menos código carregado)
- Sem magia/facades — controle explícito do ciclo de vida
- Mais leve para deploy em função serverless

### Por que Doctrine ORM 3 e não Eloquent?
- Suporte nativo a PostgreSQL UUID (gen_random_uuid)
- Attribute mapping (PHP 8+) em vez de anotações/docblocks
- Migrations built-in com Doctrine Migrations
- Performance em consultas complexas

### Por que SSR com Twig e não SPA?
- Simplicidade: sem build step, sem client routing
- Páginas renderizadas no servidor = sem estado JS inicial
- Alpine.js para interatividade pontual (modais, carrinho)
- HTMX para updates parciais sem framework JS

### Por que JWT em cookie HttpOnly e não localStorage?
- Proteção contra XSS (cookie HttpOnly não é acessível via JS)
- CSRF mitigado via SameSite=Lax
- Funciona tanto para páginas SSR quanto API

## 2. Fluxo de Requisição

```
Browser → Vercel CDN → PHP Serverless Function → Slim App
                                                      │
                                          ┌───────────┴───────────┐
                                          ▼                       ▼
                                     Route Match           404 Handler
                                          │
                                          ▼
                                   Middleware Stack
                                          │
                          ┌───────────────┼───────────────┐
                          ▼               ▼               ▼
                    BodyParsing     AuthMiddleware    RoutingMiddleware
                          │               │               │
                          └───────────────┼───────────────┘
                                          ▼
                                    Controller
                                          │
                              ┌───────────┼───────────┐
                              ▼           ▼           ▼
                          EntityMgr    Twig       Json Response
                          (Doctrine)   (Render)
```

## 3. Estrutura de Diretórios

```
api/
  index.php              # Entry point Slim (Slim App, error handling, CORS)
config/
  container.php          # DI Container (EntityManager, Twig, JwtHelper, settings)
  routes.php             # Definição de todas as rotas (web + api)
  database.php           # Configuração de conexão
  migrations.php         # Configuração Doctrine Migrations
src/
  Controllers/
    AuthController.php           # Login, register, logout
    DashboardController.php      # KPIs do dashboard
    ProductsController.php       # CRUD produtos + categorias
    InventoryController.php      # Movimentações de estoque
    SalesController.php          # PDV completo
    CustomersController.php      # Clientes + veículos
    AppointmentsController.php   # Agendamentos + serviços + mecânicos
  Middleware/
    AuthMiddleware.php           # Validação JWT
  Models/
    Tenant.php                   # Entidade tenant
    User.php                     # Entidade usuário
  Utils/
    JwtHelper.php                # Geração/validação JWT
templates/
  base.html.twig                 # Layout base
  macros.html.twig               # Sidebar + header reutilizáveis
  home/index.html.twig           # Landing page
  auth/login.html.twig           # Login com credenciais pré-preenchidas
  auth/register.html.twig        # Registro de novo tenant
  dashboard/index.html.twig      # Dashboard com KPIs
  products/index.html.twig       # Gestão de produtos
  inventory/index.html.twig      # Controle de estoque
  sales/index.html.twig          # PDV
  customers/index.html.twig      # Clientes e veículos
  appointments/index.html.twig   # Agendamentos
database/
  migrations/                    # Migrations Doctrine
  seeds/                         # Seeds de dados
```

## 4. Modelo de Dados

### Multi-tenancy
- Todas as tabelas de negócio têm `tenant_id UUID REFERENCES tenants(id)`
- Todas as queries filtram por `tenant_id`
- Slug do tenant é único globalmente

### Entidades

```
Tenant (id, name, slug, business_type, cnpj, phone, email, address, settings, is_active, plan)
  └── User (id, tenant_id, name, email, password_hash, role, commission_rate, ...)
  └── Category (id, tenant_id, parent_id, name, slug, sort_order, ...)
  │     └── Product (id, tenant_id, category_id, sku, name, cost_price, sale_price, current_stock, ...)
  │           └── InventoryMovement (id, tenant_id, product_id, type, quantity, previous_stock, new_stock, ...)
  └── Customer (id, tenant_id, name, cpf_cnpj, email, phone, ...)
  │     └── Vehicle (id, tenant_id, customer_id, plate, brand, model, year, ...)
  └── Service (id, tenant_id, name, duration_minutes, price, category, ...)
  └── Appointment (id, tenant_id, customer_id, vehicle_id, mechanic_id, service_id, status, scheduled_at, ...)
  └── Sale (id, tenant_id, customer_id, seller_id, sale_number, status, subtotal, total, ...)
        ├── SaleItem (id, sale_id, product_id, quantity, unit_price, total, ...)
        └── Payment (id, sale_id, method, amount, installments, change_amount, ...)
```

### Transações Críticas

**Criação de Venda:**
1. BEGIN transaction
2. SELECT ... FOR UPDATE nos produtos (lock)
3. Verificar saldo de cada item
4. Criar sale + sale_items
5. Atualizar current_stock em products
6. Criar inventory_movements do tipo 'sale'
7. Calcular comissão
8. Criar payments
9. COMMIT / ROLLBACK

**Movimentação de Estoque:**
1. BEGIN transaction
2. SELECT current_stock FOR UPDATE
3. Validar quantidade (out/ajustment não pode exceder saldo)
4. Atualizar current_stock
5. Criar inventory_movement
6. COMMIT

## 5. Serverless PHP na Vercel

### Limitações e Adaptações
- **Filesystem read-only** (exceto /tmp): Twig cache desabilitado, proxies Doctrine em /tmp
- **Cold starts**: Doctrine deve gerar proxies automaticamente (AUTOGENERATE_FILE_NOT_EXISTS)
- **Timeout**: Limite de 30s (configurado em vercel.json)
- **Memória**: 512MB configurados
- **Runtime**: PHP 8.3 (Vercel) vs PHP 8.5 (dev local)

### vercel.json
```json
{
  "functions": {
    "api/index.php": {
      "runtime": "vercel-php@0.7.3",
      "memory": 512,
      "maxDuration": 30
    }
  },
  "rewrites": [
    { "source": "/(.*)", "destination": "/api/index.php" }
  ]
}
```

## 6. Variáveis de Ambiente

| Variável | Descrição |
|---|---|
| DATABASE_URL | Conexão PostgreSQL (Neon pooler) |
| JWT_SECRET | Chave para assinar tokens JWT |
| JWT_ISSUER | Emissor do JWT |
| JWT_AUDIENCE | Audiência do JWT |
| JWT_EXPIRATION | Tempo de expiração em segundos |
| APP_ENV | development | production |
| APP_KEY | Chave interna da aplicação |

## 7. CI/CD

GitHub Actions executa em push/PR para main:
1. Setup PHP 8.2
2. Composer install
3. Check syntax (php -l)
4. PHPStan level 6
