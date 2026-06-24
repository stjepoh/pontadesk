<?php
declare(strict_types=1);

namespace App\Models;

final class User
{
    public function __construct(
        public ?int $id,
        public string $email,
        public string $fullName,
        public string $role = 'user',
        public string $passwordHash = '',
    ) {
    }
}
