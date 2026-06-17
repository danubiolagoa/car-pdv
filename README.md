# CAR-PDV

Sistema PDV (Ponto de Venda) para oficinas mecânicas e lojas de acessórios automotivos.

## Stack

- **Backend:** PHP 8.1+ + Slim Framework 4
- **ORM:** Doctrine ORM 3
- **Banco de dados:** Neon (PostgreSQL serverless)
- **Frontend:** Server-side rendering + Tailwind CSS + Alpine.js + HTMX
- **Deploy:** Vercel (serverless PHP)
- **CI/CD:** GitHub Actions

## Funcionalidades

- [x] Autenticação JWT + multi-tenancy
- [x] Schema de banco de dados (15 tabelas)
- [x] Dashboard com KPIs
- [x] Cadastro de produtos e categorias
- [x] Controle de estoque (movimentações + alertas)
- [x] PDV (vendas, carrinho, múltiplos pagamentos)
- [x] Cadastro de clientes e veículos
- [x] Agendamento de serviços
- [x] Deploy no Vercel

## Requisitos

- PHP 8.1+
- Composer
- Conta Neon (PostgreSQL)
- Conta Vercel

## Instalação local

```bash
# Clone o repositório
git clone https://github.com/danubiolagoa/car-pdv.git
cd car-pdv

# Instale as dependências
composer install

# Configure as variáveis de ambiente
cp .env.example .env
# Edite o .env com suas credenciais do Neon

# Execute as migrations
vendor/bin/doctrine-migrations migrate

# Execute o seed de dados iniciais
php database/seeds/SeedInitialData.php

# Inicie o servidor
php -S localhost:8080 api/index.php
```

Acesse http://localhost:8080

## Deploy

O deploy é feito automaticamente via GitHub Actions para o Vercel quando há push na branch `main`.

## Licença

MIT
