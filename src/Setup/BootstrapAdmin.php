<?php
declare(strict_types=1);

namespace App\Setup;

use App\Models\User;
use App\Repositories\UserRepository;

final class BootstrapAdmin
{
    public static function ensure(): void
    {
        try {
            $repo = new UserRepository();
            if ($repo->findById(1) !== null) {
                return;
            }

            if ($repo->findByEmail((string) (getenv('ADMIN_EMAIL') ?: 'admin@pontadesk.local')) !== null) {
                return;
            }

            $repo->create(new User(
                id: null,
                email: (string) (getenv('ADMIN_EMAIL') ?: 'admin@pontadesk.local'),
                fullName: (string) (getenv('ADMIN_NAME') ?: 'PontaDesk Admin'),
                role: 'admin',
                passwordHash: password_hash((string) (getenv('ADMIN_PASSWORD') ?: 'ChangeMe123!'), PASSWORD_DEFAULT),
            ));
        } catch (\Throwable) {
            // Ignore bootstrap issues until DB is configured.
        }
    }
}
