# PRD - CAR-PDV

## Product Requirements Document

### 1. Visão Geral

**CAR-PDV** é um sistema de Ponto de Venda (PDV) especializado para oficinas mecânicas e lojas de acessórios automotivos. Oferece gestão de vendas, estoque, clientes, veículos e agendamento de serviços em uma plataforma multi-tenant.

### 2. Público-Alvo

- **Oficinas mecânicas** (segmento principal)
  - Donos de oficinas autônomas
  - Redes de oficinas
  - Mecânicos que também vendem peças
- **Lojas de acessórios automotivos**
  - Lojas de peças e acessórios
  - Estações de serviços automotivos
- **Perfil dos usuários**
  - Administradores (donos/gerentes)
  - Vendedores (atendimento no balcão)
  - Mecânicos (execução de serviços)

### 3. Problema

Oficinas mecânicas e lojas de autopeças no Brasil carecem de sistemas PDV modernos, acessíveis e fáceis de usar. As soluções existentes são:
- Caras e com contratos longos
- Complexas demais (ERP completos com funcionalidades desnecessárias)
- Desatualizadas (UI antiquada, sem integração moderna)
- Sem suporte para o modelo misto (venda de peças + prestação de serviços)

### 4. Funcionalidades (MVP)

#### 4.1 Autenticação e Multi-tenancy

- Login via email/senha com JWT (cookie HttpOnly)
- Registro de novo tenant com slug único
- Separação completa de dados entre tenants
- 3 papéis: admin, seller, mechanic
- Logout com cookie invalidation

#### 4.2 Dashboard

- KPIs do dia: vendas do dia, tickets pendentes, estoque baixo
- Cards com valores totais do mês
- Acesso rápido a todos os módulos via sidebar

#### 4.3 Produtos e Categorias

- CRUD completo de produtos (SKU, nome, descrição, preço, estoque)
- Categorias aninhadas (parent/child)
- Controle de estoque mínimo
- Soft-delete (is_active)
- Busca por nome/SKU
- Slugify automático para categorias

#### 4.4 Controle de Estoque

- Movimentações: entrada (in), saída (out), ajuste (adjustment), devolução (return)
- Alerta de estoque baixo (abaixo do min_stock)
- Histórico completo com filtro por tipo
- Atualização automática via venda (sale)
- Lock transacional (FOR UPDATE) para evitar condição de corrida

#### 4.5 PDV (Vendas)

- Carrinho de compras com adição/remoção de itens
- Desconto por valor (R$) ou percentual (%)
- Múltiplos métodos de pagamento por venda: cash, pix, debit, credit, transfer, check
- Cálculo de troco
- Número de venda no formato YYYY-XXXX-NNNNNN
- Comissão automática do vendedor (commission_rate)
- Atualização automática de estoque (com rollback em caso de saldo insuficiente)
- Cancelamento de venda com devolução de itens ao estoque
- Registro de movimento de estoque do tipo "sale"

#### 4.6 Clientes e Veículos

- CRUD completo de clientes (nome, CPF/CNPJ, email, telefone, endereço)
- Máscaras de CPF/CNPJ e telefone no frontend
- Vínculo de veículos ao cliente (placa, marca, modelo, ano, cor)
- Placa única por tenant
- Total de compras acumulado

#### 4.7 Agendamento de Serviços

- Agendamento por data e horário
- Serviços pré-cadastrados (troca de óleo, revisão, etc.)
- Mecânicos designados
- Status: scheduled, in_progress, completed, cancelled, no_show
- Registro de timestamps reais (actual_start, actual_end)
- Vínculo com cliente e veículo

#### 4.8 Serviços (Catálogo)

- Cadastro de serviços (nome, duração, preço, categoria)
- Preço fixo por serviço
- Categorização (Manutenção, Freios, Motor, etc.)

### 5. Stack Técnica

| Componente | Tecnologia |
|---|---|
| Backend | PHP 8.1+ / Slim Framework 4 |
| ORM | Doctrine ORM 3 |
| Banco | PostgreSQL 16 (Neon serverless) |
| Frontend | SSR com Twig + Tailwind CSS + Alpine.js + HTMX |
| Autenticação | JWT (firebase/php-jwt) via cookie HttpOnly |
| Validação | Valitron |
| Logs | Monolog |
| Deploy | Vercel (serverless PHP - vercel-php@0.7.3) |
| CI/CD | GitHub Actions (PHPStan + syntax check) |

### 6. Arquitetura

- MVC com Slim 4 (rotas → controllers → models → templates)
- Multi-tenancy por coluna tenant_id em todas as tabelas
- JWT armazenado em cookie HttpOnly para páginas SSR
- API REST para operações CRUD (JSON)
- Serverless PHP (cada requisição é um cold-start potencial)
- Transações com FOR UPDATE para operações críticas de estoque

### 7. Estrutura do Banco

15 tabelas principais: tenants, users, categories, products, inventory_movements, customers, vehicles, services, appointments, sales, sale_items, payments, financial_entries, coupons.

UUIDs gerados via gen_random_uuid() do PostgreSQL.

### 8. Critérios de Sucesso (MVP)

- [x] Login funcional com JWT
- [x] Dashboard com dados reais do banco
- [x] CRUD de produtos funcionando
- [x] Controle de estoque com alertas
- [x] Venda completa (carrinho → pagamento → finalização)
- [x] Cadastro de clientes com veículos
- [x] Agendamento de serviços
- [x] Deploy funcional no Vercel
- [x] CI/CD automatizado
- [x] Seed de dados demo

### 9. Não-Escopo (MVP)

- App mobile nativo (apenas web responsivo)
- Notificações push/email
- Integração com maquininha de cartão
- Emissão de NF-e
- Relatórios avançados (gráficos, exportação)
- Integração com marketplaces
- App offline-first
