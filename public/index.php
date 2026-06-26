<?php
declare(strict_types=1);

function pontadesk_bootstrap_path(): string
{
    $candidates = [
        __DIR__ . '/../src/bootstrap.php',
        __DIR__ . '/../../repositories/pontadesk/src/bootstrap.php',
        '/home/hladilo/repositories/pontadesk/src/bootstrap.php',
        '/home/' . (getenv('USER') ?: 'hladilo') . '/repositories/pontadesk/src/bootstrap.php',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    throw new RuntimeException('PontaDesk bootstrap nije pronađen.');
}

require pontadesk_bootstrap_path();

$router = new App\Routing\Router();

$router->get('/login', [App\Http\Controllers\AuthController::class, 'login']);
$router->post('/login', [App\Http\Controllers\AuthController::class, 'authenticate']);
$router->get('/logout', [App\Http\Controllers\AuthController::class, 'logout']);

$router->get('/', [App\Http\Controllers\DashboardController::class, 'index']);
$router->get('/health', [App\Http\Controllers\HealthController::class, 'index']);
$router->get('/clients', [App\Http\Controllers\ClientController::class, 'index']);
$router->get('/clients/create', [App\Http\Controllers\ClientController::class, 'create']);
$router->get('/clients/detail', [App\Http\Controllers\ClientController::class, 'detail']);
$router->get('/clients/edit', [App\Http\Controllers\ClientController::class, 'edit']);
$router->get('/clients/delete', [App\Http\Controllers\ClientController::class, 'delete']);
$router->get('/clients/update', [App\Http\Controllers\ClientController::class, 'update']);

$router->post('/clients', [App\Http\Controllers\ClientController::class, 'store']);
$router->post('/clients/update', [App\Http\Controllers\ClientController::class, 'update']);

$router->get('/contracts', [App\Http\Controllers\ContractController::class, 'index']);
$router->get('/contracts/create', [App\Http\Controllers\ContractController::class, 'create']);
$router->get('/contracts/edit', [App\Http\Controllers\ContractController::class, 'edit']);
$router->get('/contracts/delete', [App\Http\Controllers\ContractController::class, 'delete']);
$router->post('/contracts', [App\Http\Controllers\ContractController::class, 'store']);
$router->post('/contracts/update', [App\Http\Controllers\ContractController::class, 'update']);

$router->get('/work-logs', [App\Http\Controllers\WorkLogController::class, 'index']);
$router->get('/work-logs/create', [App\Http\Controllers\WorkLogController::class, 'create']);
$router->get('/work-logs/edit', [App\Http\Controllers\WorkLogController::class, 'edit']);
$router->get('/work-logs/delete', [App\Http\Controllers\WorkLogController::class, 'delete']);
$router->post('/work-logs', [App\Http\Controllers\WorkLogController::class, 'store']);
$router->post('/work-logs/update', [App\Http\Controllers\WorkLogController::class, 'update']);

$router->get('/reports', [App\Http\Controllers\PlaceholderController::class, 'reports']);
$router->get('/tasks', [App\Http\Controllers\PlaceholderController::class, 'tasks']);
$router->get('/notes', [App\Http\Controllers\PlaceholderController::class, 'notes']);
$router->get('/notifications', [App\Http\Controllers\PlaceholderController::class, 'notifications']);
$router->get('/import-map', [App\Http\Controllers\ImportMapController::class, 'index']);
$router->post('/import-map', [App\Http\Controllers\ImportMapController::class, 'store']);
$router->get('/data-import', [App\Http\Controllers\DataImportController::class, 'index']);
$router->post('/data-import', [App\Http\Controllers\DataImportController::class, 'store']);

$router->dispatch(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'
);
