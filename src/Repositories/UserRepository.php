<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use App\Models\User;

final class UserRepository
{
    public function findByEmail(string $email): ?array
    {
        $stmt = Connection::pdo()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = Connection::pdo()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(User $user): int
    {
        $stmt = Connection::pdo()->prepare('INSERT INTO users (email, full_name, role, password_hash) VALUES (:email, :full_name, :role, :password_hash)');
        $stmt->execute([
            'email' => $user->email,
            'full_name' => $user->fullName,
            'role' => $user->role,
            'password_hash' => $user->passwordHash,
        ]);
        return (int) Connection::pdo()->lastInsertId();
    }
}
