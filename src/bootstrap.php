<?php
declare(strict_types=1);

require __DIR__ . '/Support/Env.php';

App\Support\Env::load(dirname(__DIR__) . '/.env');

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
