<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuthService;

final class AuthController
{
    public function login(): void
    {
        if ((new AuthService())->check()) {
            $this->redirect('/');
        }
        $this->render('Prijava', $this->loginForm());
    }

    public function authenticate(): void
    {
        $auth = new AuthService();
        if ($auth->attempt(trim((string)($_POST['email'] ?? '')), (string)($_POST['password'] ?? ''))) {
            $this->redirect('/');
        }
        $this->render('Prijava', '<p style="color:#fca5a5">Neispravni podaci za prijavu.</p>' . $this->loginForm());
    }

    public function logout(): void
    {
        (new AuthService())->logout();
        $this->redirect('/login');
    }

    private function loginForm(): string
    {
        return '<form method="post" action="/login" style="display:grid;gap:12px;max-width:360px"><input class="input" name="email" type="email" placeholder="Email" required><input class="input" name="password" type="password" placeholder="Lozinka" required><button class="btn" type="submit">Prijavi se</button></form>';
    }

    private function render(string $title, string $content): void
    {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="hr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>PontaDesk - ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title><style>body{font-family:Arial,sans-serif;background:#0f172a;color:#e2e8f0;margin:0;padding:40px}.card{max-width:560px;margin:0 auto;background:#111827;border:1px solid #334155;border-radius:16px;padding:28px}.input,.btn{width:100%;box-sizing:border-box;padding:12px 14px;border-radius:10px;border:1px solid #334155}.input{background:#0b1220;color:#e2e8f0}.btn{background:#2563eb;color:#fff;cursor:pointer}.muted{color:#94a3b8}</style></head><body><main class="card"><h1>Prijava</h1><p class="muted">Admin pristup PontaDesk aplikaciji.</p>' . $content . '</main></body></html>';
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path);
        exit;
    }
}
