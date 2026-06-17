<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema for CAR-PDV: tenants, users, products, inventory, customers, sales, services, appointments';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on PostgreSQL.');

        // Tenants
        $this->addSql('CREATE TABLE tenants (
            id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            name VARCHAR(200) NOT NULL,
            slug VARCHAR(100) UNIQUE NOT NULL,
            business_type VARCHAR(50) NOT NULL CHECK (business_type IN (\'automotive\', \'mixed\')),
            cnpj VARCHAR(18),
            phone VARCHAR(20),
            email VARCHAR(200),
            address JSONB DEFAULT \'{}\',
            settings JSONB DEFAULT \'{}\',
            is_active BOOLEAN DEFAULT true,
            plan VARCHAR(50) DEFAULT \'free\',
            created_at TIMESTAMPTZ DEFAULT now(),
            updated_at TIMESTAMPTZ DEFAULT now()
        )');

        // Users
        $this->addSql('CREATE TABLE users (
            id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            name VARCHAR(200) NOT NULL,
            email VARCHAR(200) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL CHECK (role IN (\'admin\', \'manager\', \'seller\', \'mechanic\')),
            cpf VARCHAR(14),
            phone VARCHAR(20),
            commission_rate DECIMAL(5,2) DEFAULT 0,
            is_active BOOLEAN DEFAULT true,
            last_login_at TIMESTAMPTZ,
            created_at TIMESTAMPTZ DEFAULT now(),
            updated_at TIMESTAMPTZ DEFAULT now(),
            UNIQUE(tenant_id, email)
        )');

        // Categories
        $this->addSql('CREATE TABLE categories (
            id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            parent_id UUID REFERENCES categories(id),
            name VARCHAR(200) NOT NULL,
            slug VARCHAR(200) NOT NULL,
            description TEXT,
            sort_order INT DEFAULT 0,
            is_active BOOLEAN DEFAULT true,
            created_at TIMESTAMPTZ DEFAULT now(),
            UNIQUE(tenant_id, slug)
        )');

        // Products
        $this->addSql('CREATE TABLE products (
            id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            category_id UUID REFERENCES categories(id),
            sku VARCHAR(100) NOT NULL,
            barcode VARCHAR(100),
            name VARCHAR(300) NOT NULL,
            description TEXT,
            unit VARCHAR(20) DEFAULT \'UN\',
            cost_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            sale_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            min_stock DECIMAL(12,3) DEFAULT 0,
            current_stock DECIMAL(12,3) DEFAULT 0,
            max_stock DECIMAL(12,3),
            location VARCHAR(100),
            images JSONB DEFAULT \'[]\',
            attributes JSONB DEFAULT \'{}\',
            is_active BOOLEAN DEFAULT true,
            is_service BOOLEAN DEFAULT false,
            created_at TIMESTAMPTZ DEFAULT now(),
            updated_at TIMESTAMPTZ DEFAULT now(),
            UNIQUE(tenant_id, sku)
        )');

        // Inventory movements
        $this->addSql('CREATE TABLE inventory_movements (
            id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            product_id UUID NOT NULL REFERENCES products(id),
            user_id UUID REFERENCES users(id),
            type VARCHAR(30) NOT NULL CHECK (type IN (\'in\', \'out\', \'adjustment\', \'return\', \'sale\')),
            quantity DECIMAL(12,3) NOT NULL,
            previous_stock DECIMAL(12,3) NOT NULL,
            new_stock DECIMAL(12,3) NOT NULL,
            cost_price DECIMAL(12,2),
            reference_type VARCHAR(50),
            reference_id UUID,
            notes TEXT,
            created_at TIMESTAMPTZ DEFAULT now()
        )');

        // Customers
        $this->addSql('CREATE TABLE customers (
            id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            name VARCHAR(200) NOT NULL,
            cpf_cnpj VARCHAR(18),
            email VARCHAR(200),
            phone VARCHAR(20),
            mobile VARCHAR(20),
            address JSONB DEFAULT \'{}\',
            notes TEXT,
            total_purchases DECIMAL(12,2) DEFAULT 0,
            loyalty_points INT DEFAULT 0,
            tags JSONB DEFAULT \'[]\',
            is_active BOOLEAN DEFAULT true,
            created_at TIMESTAMPTZ DEFAULT now(),
            updated_at TIMESTAMPTZ DEFAULT now()
        )');

        // Vehicles (automotive-specific)
        $this->addSql('CREATE TABLE vehicles (
            id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            customer_id UUID NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
            plate VARCHAR(20) NOT NULL,
            brand VARCHAR(100),
            model VARCHAR(100),
            year INT,
            color VARCHAR(50),
            chassis VARCHAR(100),
            notes TEXT,
            is_active BOOLEAN DEFAULT true,
            created_at TIMESTAMPTZ DEFAULT now(),
            updated_at TIMESTAMPTZ DEFAULT now(),
            UNIQUE(tenant_id, plate)
        )');

        // Services
        $this->addSql('CREATE TABLE services (
            id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            name VARCHAR(300) NOT NULL,
            description TEXT,
            duration_minutes INT DEFAULT 60,
            price DECIMAL(12,2) NOT NULL DEFAULT 0,
            category VARCHAR(100),
            is_active BOOLEAN DEFAULT true,
            created_at TIMESTAMPTZ DEFAULT now(),
            updated_at TIMESTAMPTZ DEFAULT now()
        )');

        // Appointments
        $this->addSql('CREATE TABLE appointments (
            id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            customer_id UUID NOT NULL REFERENCES customers(id),
            vehicle_id UUID REFERENCES vehicles(id),
            mechanic_id UUID REFERENCES users(id),
            service_id UUID REFERENCES services(id),
            status VARCHAR(30) NOT NULL DEFAULT \'scheduled\' CHECK (status IN (\'scheduled\', \'in_progress\', \'completed\', \'cancelled\', \'no_show\')),
            scheduled_at TIMESTAMPTZ NOT NULL,
            estimated_end TIMESTAMPTZ,
            actual_start TIMESTAMPTZ,
            actual_end TIMESTAMPTZ,
            notes TEXT,
            total_price DECIMAL(12,2) DEFAULT 0,
            sale_id UUID,
            created_at TIMESTAMPTZ DEFAULT now(),
            updated_at TIMESTAMPTZ DEFAULT now()
        )');

        // Sales
        $this->addSql('CREATE TABLE sales (
            id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            customer_id UUID REFERENCES customers(id),
            seller_id UUID NOT NULL REFERENCES users(id),
            sale_number VARCHAR(50) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT \'open\' CHECK (status IN (\'open\', \'completed\', \'cancelled\', \'refunded\')),
            subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
            discount DECIMAL(12,2) DEFAULT 0,
            discount_type VARCHAR(20) DEFAULT \'value\' CHECK (discount_type IN (\'value\', \'percent\')),
            total DECIMAL(12,2) NOT NULL DEFAULT 0,
            commission DECIMAL(12,2) DEFAULT 0,
            notes TEXT,
            coupon_code VARCHAR(100),
            created_at TIMESTAMPTZ DEFAULT now(),
            completed_at TIMESTAMPTZ,
            cancelled_at TIMESTAMPTZ,
            UNIQUE(tenant_id, sale_number)
        )');

        // Sale items
        $this->addSql('CREATE TABLE sale_items (
            id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            sale_id UUID NOT NULL REFERENCES sales(id) ON DELETE CASCADE,
            product_id UUID NOT NULL REFERENCES products(id),
            name VARCHAR(300) NOT NULL,
            quantity DECIMAL(12,3) NOT NULL,
            unit_price DECIMAL(12,2) NOT NULL,
            discount DECIMAL(12,2) DEFAULT 0,
            total DECIMAL(12,2) NOT NULL,
            cost_price DECIMAL(12,2) NOT NULL,
            created_at TIMESTAMPTZ DEFAULT now()
        )');

        // Payments
        $this->addSql('CREATE TABLE payments (
            id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            sale_id UUID NOT NULL REFERENCES sales(id) ON DELETE CASCADE,
            method VARCHAR(50) NOT NULL CHECK (method IN (\'cash\', \'debit\', \'credit\', \'pix\', \'transfer\', \'check\', \'other\')),
            amount DECIMAL(12,2) NOT NULL,
            installments INT DEFAULT 1,
            change_amount DECIMAL(12,2) DEFAULT 0,
            reference VARCHAR(200),
            status VARCHAR(30) DEFAULT \'confirmed\' CHECK (status IN (\'pending\', \'confirmed\', \'cancelled\')),
            created_at TIMESTAMPTZ DEFAULT now()
        )');

        // Financial entries
        $this->addSql('CREATE TABLE financial_entries (
            id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            type VARCHAR(20) NOT NULL CHECK (type IN (\'income\', \'expense\')),
            category VARCHAR(100) NOT NULL,
            description VARCHAR(500) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            due_date DATE NOT NULL,
            paid_date DATE,
            status VARCHAR(20) DEFAULT \'pending\' CHECK (status IN (\'pending\', \'paid\', \'overdue\', \'cancelled\')),
            reference_type VARCHAR(50),
            reference_id UUID,
            recurring BOOLEAN DEFAULT false,
            recurring_interval VARCHAR(20),
            created_at TIMESTAMPTZ DEFAULT now()
        )');

        // Coupons
        $this->addSql('CREATE TABLE coupons (
            id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
            code VARCHAR(100) NOT NULL,
            type VARCHAR(20) NOT NULL CHECK (type IN (\'percent\', \'value\')),
            value DECIMAL(12,2) NOT NULL,
            min_purchase DECIMAL(12,2) DEFAULT 0,
            max_uses INT,
            used_count INT DEFAULT 0,
            valid_from TIMESTAMPTZ,
            valid_until TIMESTAMPTZ,
            is_active BOOLEAN DEFAULT true,
            created_at TIMESTAMPTZ DEFAULT now(),
            UNIQUE(tenant_id, code)
        )');

        // Indexes
        $this->addSql('CREATE INDEX idx_users_tenant ON users(tenant_id)');
        $this->addSql('CREATE INDEX idx_users_email ON users(tenant_id, email)');
        $this->addSql('CREATE INDEX idx_products_tenant ON products(tenant_id)');
        $this->addSql('CREATE INDEX idx_products_category ON products(category_id)');
        $this->addSql('CREATE INDEX idx_products_sku ON products(tenant_id, sku)');
        $this->addSql('CREATE INDEX idx_products_barcode ON products(barcode)');
        $this->addSql('CREATE INDEX idx_inventory_product ON inventory_movements(product_id)');
        $this->addSql('CREATE INDEX idx_inventory_date ON inventory_movements(created_at)');
        $this->addSql('CREATE INDEX idx_customers_tenant ON customers(tenant_id)');
        $this->addSql('CREATE INDEX idx_customers_cpf_cnpj ON customers(tenant_id, cpf_cnpj)');
        $this->addSql('CREATE INDEX idx_vehicles_tenant ON vehicles(tenant_id)');
        $this->addSql('CREATE INDEX idx_vehicles_customer ON vehicles(customer_id)');
        $this->addSql('CREATE INDEX idx_vehicles_plate ON vehicles(tenant_id, plate)');
        $this->addSql('CREATE INDEX idx_services_tenant ON services(tenant_id)');
        $this->addSql('CREATE INDEX idx_appointments_tenant ON appointments(tenant_id)');
        $this->addSql('CREATE INDEX idx_appointments_date ON appointments(scheduled_at)');
        $this->addSql('CREATE INDEX idx_appointments_mechanic ON appointments(mechanic_id)');
        $this->addSql('CREATE INDEX idx_sales_tenant ON sales(tenant_id)');
        $this->addSql('CREATE INDEX idx_sales_seller ON sales(seller_id)');
        $this->addSql('CREATE INDEX idx_sales_customer ON sales(customer_id)');
        $this->addSql('CREATE INDEX idx_sales_status ON sales(status)');
        $this->addSql('CREATE INDEX idx_sales_date ON sales(created_at)');
        $this->addSql('CREATE INDEX idx_sale_items_sale ON sale_items(sale_id)');
        $this->addSql('CREATE INDEX idx_payments_sale ON payments(sale_id)');
        $this->addSql('CREATE INDEX idx_financial_tenant ON financial_entries(tenant_id)');
        $this->addSql('CREATE INDEX idx_financial_due ON financial_entries(due_date)');
        $this->addSql('CREATE INDEX idx_coupons_tenant ON coupons(tenant_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on PostgreSQL.');

        $tables = [
            'payments',
            'sale_items',
            'sales',
            'appointments',
            'services',
            'vehicles',
            'customers',
            'inventory_movements',
            'products',
            'categories',
            'users',
            'tenants',
            'financial_entries',
            'coupons',
        ];

        foreach ($tables as $table) {
            $this->addSql("DROP TABLE IF EXISTS {$table} CASCADE");
        }
    }
}
