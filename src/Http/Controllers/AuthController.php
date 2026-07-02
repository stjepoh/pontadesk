<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuthService;
use App\Services\GoogleAuthService;

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

    public function googleRedirect(): void
    {
        $google = new GoogleAuthService();
        if (!$google->isConfigured()) {
            $this->render('Prijava', '<p style="color:#fca5a5">Google prijava još nije konfigurirana.</p>' . $this->loginForm());
            return;
        }

        $this->redirect($google->authUrl());
    }

    public function googleCallback(): void
    {
        $google = new GoogleAuthService();
        $auth = new AuthService();

        try {
            $profile = $google->userFromCallback($_GET);
        } catch (\Throwable) {
            $this->render('Prijava', '<p style="color:#fca5a5">Google prijava nije uspjela. Pokušajte ponovno.</p>' . $this->loginForm());
            return;
        }

        if ($auth->attemptGoogle($profile)) {
            $this->redirect('/');
        }

        $this->render('Prijava', '<p style="color:#fca5a5">Ovaj Google račun nema pristup aplikaciji.</p>' . $this->loginForm());
    }

    public function logout(): void
    {
        (new AuthService())->logout();
        $this->redirect('/login');
    }

    private function loginForm(): string
    {
        $google = new GoogleAuthService();
        $googleButton = $google->isConfigured()
            ? '<a class="google-btn" href="/login/google"><span>G</span> Prijava preko Google računa</a><div class="divider"><span>ili</span></div>'
            : '<p class="muted small">Google prijava će se prikazati nakon što se dodaju GOOGLE_CLIENT_ID i GOOGLE_CLIENT_SECRET.</p>';

        return '<div style="display:grid;gap:14px;max-width:360px">' . $googleButton . '<form method="post" action="/login" style="display:grid;gap:12px"><input class="input" name="email" type="email" placeholder="Email" required><input class="input" name="password" type="password" placeholder="Lozinka" required><button class="btn" type="submit">Prijavi se emailom</button></form></div>';
    }

    private function render(string $title, string $content): void
    {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="hr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>PontaDesk - ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
        echo '<link rel="icon" href="/favicon.ico" sizes="any">';
        echo '<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">';
        echo '<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">';
        echo '<link rel="apple-touch-icon" href="/apple-touch-icon.png">';
        echo '<link rel="manifest" href="/site.webmanifest">';
        echo '<meta name="theme-color" content="#111827">';
        echo '<meta name="mobile-web-app-capable" content="yes">';
        echo '<meta name="apple-mobile-web-app-capable" content="yes">';
        echo '<meta name="apple-mobile-web-app-title" content="PontaDesk">';
        echo '<style>body{font-family:Arial,sans-serif;background:#0f172a;color:#e2e8f0;margin:0;padding:40px}.card{max-width:560px;margin:0 auto;background:#111827;border:1px solid #334155;border-radius:16px;padding:28px}.login-brand{display:flex;align-items:center;gap:12px;margin-bottom:20px}.login-brand img{width:54px;height:54px;border-radius:14px;object-fit:cover;background:#fff}.login-brand strong{display:block;font-size:20px}.input,.btn,.google-btn{width:100%;box-sizing:border-box;padding:12px 14px;border-radius:10px;border:1px solid #334155}.input{background:#0b1220;color:#e2e8f0}.btn{background:#2563eb;color:#fff;cursor:pointer}.google-btn{display:flex;align-items:center;justify-content:center;gap:10px;background:#fff;color:#111827;font-weight:700;text-decoration:none}.google-btn span{width:22px;height:22px;border-radius:50%;display:grid;place-items:center;background:#4285f4;color:#fff;font-weight:800}.divider{display:flex;align-items:center;gap:12px;color:#64748b;font-size:12px}.divider:before,.divider:after{content:"";height:1px;background:#334155;flex:1}.muted{color:#94a3b8}.small{font-size:12px;line-height:1.45}</style></head><body><main class="card"><div class="login-brand"><img src="/assets/img/ponta-app-logo.png" alt="PontaDesk"><div><strong>PontaDesk</strong><span class="muted">Admin pristup aplikaciji</span></div></div><h1>Prijava</h1><p class="muted">Admin pristup PontaDesk aplikaciji.</p>' . $content . '</main></body></html>';
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path);
        exit;
    }
}
