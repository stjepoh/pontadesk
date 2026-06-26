<?php
declare(strict_types=1);

require __DIR__ . '/Support/Env.php';

function pontadesk_env_path(): ?string
{
    $candidates = [
        dirname(__DIR__) . '/.env',
        dirname(__DIR__) . '/../public_html/dashboard.hladilo.com/.env',
        dirname(__DIR__) . '/../../public_html/dashboard.hladilo.com/.env',
        '/home/hladilo/public_html/dashboard.hladilo.com/.env',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

$envPath = pontadesk_env_path();
if ($envPath !== null) {
    App\Support\Env::load($envPath);
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

date_default_timezone_set((string) (getenv('APP_TIMEZONE') ?: 'Europe/Zagreb'));
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

App\Setup\BootstrapAdmin::ensure();
