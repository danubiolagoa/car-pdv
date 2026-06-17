# Planejamento - CAR-PDV

## 1. Escopo Inicial

Sistema PDV multi-tenant para oficinas mecânicas e lojas de acessórios automotivos.

### Stack Definida
- PHP 8.1+ / Slim 4 / Doctrine ORM 3
- Neon (PostgreSQL serverless)
- SSR (Twig + Tailwind + Alpine.js + HTMX)
- Deploy Vercel (vercel-php)

## 2. Fases de Implementação

### Fase 1 - MVP (Concluída)
- [x] Setup do projeto (Slim, Doctrine, migrations, seed)
- [x] Autenticação JWT + multi-tenancy
- [x] Dashboard com KPIs
- [x] CRUD Produtos e Categorias
- [x] Controle de Estoque
- [x] PDV (vendas, carrinho, pagamentos)
- [x] Clientes e Veículos
- [x] Agendamento de Serviços
- [x] Deploy Vercel
- [x] CI/CD GitHub Actions
- [x] Seed de dados demo

### Fase 2 - Planejada
- [ ] Relatórios (vendas por período, produtos mais vendidos)
- [ ] Financeiro (contas a pagar/receber, fluxo de caixa)
- [ ] Cupons de desconto
- [ ] Histórico de preços de produtos
- [ ] Múltiplos endereços por cliente
- [ ] Upload de imagens de produtos
- [ ] Impressão de cupom/comprovante
- [ ] Notificações de estoque baixo (dashboard)

### Fase 3 - Futura
- [ ] API pública para integrações
- [ ] App mobile (PWA)
- [ ] Integração com gateways de pagamento (Mercado Pago, PIX)
- [ ] Emissão de NF-e
- [ ] Integração com sistemas contábeis
- [ ] Multi-idioma (português/inglês/espanhol)

## 3. Decisões do Projeto

### 17/06/2026 - Retomada do Projeto
- **Controllers por módulo**: Cada módulo tem seu próprio controller com métodos separados para páginas SSR e ações de API
- **Sidebar reutilizável**: Criada macro Twig (macros.html.twig) compartilhada entre todos os templates
- **UUID no banco**: gen_random_uuid() do PostgreSQL em vez de GeneratedValue do Doctrine (evita problemas de compatibilidade)
- **Transações com FOR UPDATE**: Garantia de integridade do estoque em vendas concorrentes
- **Testes via CLI**: Em vez de servidor HTTP (evita travamentos no Windows)
- **PHPStan level 6**: Todo o código aprovado
- **Deploy Vercel**: Token via MCP, env vars via API REST, alias car-pdv.vercel.app

### Observações Técnicas
- PHP 8.5 local (Scoop) ≠ PHP 8.2 Vercel (compatibilidade mantida)
- symfony/cache + symfony/var-exporter necessários para Doctrine ORM
- Valitron não tem regra `slug` nativa (evitada)
- vercel-php@0.7.3 usado com rewrite /(.*) → /api/index.php
- Doctrine proxies devem ser auto-gerados (serverless não mantém /tmp entre execuções)
- Twig cache desabilitado no Vercel (filesystem read-only)

## 4. Estrutura do Time

Projeto individual (Danubio Lagoa).

## 5. Timeline

| Data | Evento |
|---|---|
| 16/06/2026 | Setup inicial, migrations, seed |
| 17/06/2026 | Implementação de todos os módulos + deploy |
| 17/06/2026 | CI/CD configurado |
| 17/06/2026 | Deploy no Vercel |
