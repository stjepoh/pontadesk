<?php
declare(strict_types=1);

namespace App\Http\Controllers;

final class HealthController
{
    public function index(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        echo json_encode([
            'status' => 'ok',
            'service' => 'PontaDesk',
            'time' => date(DATE_ATOM),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
