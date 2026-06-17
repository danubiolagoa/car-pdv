<?php

declare(strict_types=1);

use App\Controllers\AppointmentsController;
use App\Controllers\AuthController;
use App\Controllers\CustomersController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\InventoryController;
use App\Controllers\ProductsController;
use App\Controllers\SalesController;
use App\Middleware\AuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->get('/', [HomeController::class, 'index']);
    $app->get('/health', [HomeController::class, 'health']);
    $app->get('/login', [AuthController::class, 'showLogin']);
    $app->get('/register', [AuthController::class, 'showRegister']);

    $app->group('/auth', function (RouteCollectorProxy $group) {
        $group->post('/login', [AuthController::class, 'login']);
        $group->post('/register', [AuthController::class, 'register']);
        $group->post('/logout', [AuthController::class, 'logout']);
    });

    $app->group('/app', function (RouteCollectorProxy $group) {
        $group->get('/dashboard', [DashboardController::class, 'index']);
        $group->get('/products', [ProductsController::class, 'index']);
        $group->get('/inventory', [InventoryController::class, 'index']);
        $group->get('/pdv', [SalesController::class, 'index']);
        $group->get('/customers', [CustomersController::class, 'index']);
        $group->get('/appointments', [AppointmentsController::class, 'index']);
    })->add(AuthMiddleware::class);

    $app->group('/api', function (RouteCollectorProxy $group) {
        // Products
        $group->get('/products', [ProductsController::class, 'listProducts']);
        $group->post('/products', [ProductsController::class, 'createProduct']);
        $group->put('/products/{id}', [ProductsController::class, 'updateProduct']);
        $group->delete('/products/{id}', [ProductsController::class, 'deleteProduct']);
        $group->get('/categories', [ProductsController::class, 'listCategories']);
        $group->post('/categories', [ProductsController::class, 'createCategory']);

        // Inventory
        $group->get('/inventory/movements', [InventoryController::class, 'listMovements']);
        $group->get('/inventory/low-stock', [InventoryController::class, 'listLowStock']);
        $group->post('/inventory/movements', [InventoryController::class, 'createMovement']);

        // Customers
        $group->get('/customers', [CustomersController::class, 'listCustomers']);
        $group->get('/customers/{id}', [CustomersController::class, 'getCustomer']);
        $group->post('/customers', [CustomersController::class, 'createCustomer']);
        $group->put('/customers/{id}', [CustomersController::class, 'updateCustomer']);
        $group->delete('/customers/{id}', [CustomersController::class, 'deleteCustomer']);
        $group->post('/vehicles', [CustomersController::class, 'createVehicle']);

        // Sales (PDV)
        $group->get('/sales', [SalesController::class, 'listSales']);
        $group->get('/sales/{id}', [SalesController::class, 'getSale']);
        $group->post('/sales', [SalesController::class, 'createSale']);
        $group->delete('/sales/{id}', [SalesController::class, 'cancelSale']);

        // Appointments
        $group->get('/appointments', [AppointmentsController::class, 'listAppointments']);
        $group->post('/appointments', [AppointmentsController::class, 'createAppointment']);
        $group->patch('/appointments/{id}', [AppointmentsController::class, 'updateStatus']);
        $group->get('/services', [AppointmentsController::class, 'listServices']);
        $group->get('/mechanics', [AppointmentsController::class, 'listMechanics']);
    })->add(AuthMiddleware::class);
};
