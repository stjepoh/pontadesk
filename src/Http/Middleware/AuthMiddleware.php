<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AuthService;

final class AuthMiddleware
{
    public function handle(): void
    {
        if (!(new AuthService())->check()) {
            header('Location: /login');
            exit;
        }
    }
}
