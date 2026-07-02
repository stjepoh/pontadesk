<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;

final class AuthService
{
    public function user(): ?array
    {
        if (isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user'])) {
            return $_SESSION['auth_user'];
        }

        $id = $_SESSION['user_id'] ?? null;
        if (!is_int($id) && !ctype_digit((string) $id)) {
            return null;
        }

        if (!$this->dbAvailable()) {
            return null;
        }

        return (new UserRepository())->findById((int) $id);
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function isAdmin(): bool
    {
        return (($this->user()['role'] ?? null) === 'admin');
    }

    public function attempt(string $email, string $password): bool
    {
        if (!$this->dbAvailable()) {
            $defaultEmail = (string) (getenv('ADMIN_EMAIL') ?: 'admin@pontadesk.local');
            $defaultPassword = (string) (getenv('ADMIN_PASSWORD') ?: 'ChangeMe123!');

            if (strcasecmp($email, $defaultEmail) === 0 && $password === $defaultPassword) {
                $_SESSION['auth_user'] = [
                    'id' => 1,
                    'email' => $defaultEmail,
                    'full_name' => (string) (getenv('ADMIN_NAME') ?: 'PontaDesk Admin'),
                    'role' => 'admin',
                ];
                return true;
            }

            return false;
        }

        $user = (new UserRepository())->findByEmail($email);
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['auth_user'] = $user;
        return true;
    }

    /**
     * @param array<string, mixed> $profile
     */
    public function attemptGoogle(array $profile): bool
    {
        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        if ($email === '' || empty($profile['verified_email'])) {
            return false;
        }

        $google = new GoogleAuthService();
        $allowedEmails = $google->allowedEmails();
        $allowedDomain = $google->allowedDomain();
        $domainAllowed = $allowedDomain !== '' && str_ends_with($email, '@' . $allowedDomain);

        if ($this->dbAvailable()) {
            $user = (new UserRepository())->findByEmail($email);
            if ($user) {
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['auth_user'] = $user;
                return true;
            }
        }

        if (!in_array($email, $allowedEmails, true) && !$domainAllowed) {
            return false;
        }

        $_SESSION['auth_user'] = [
            'id' => 0,
            'email' => $email,
            'full_name' => (string) ($profile['name'] ?? $email),
            'role' => 'admin',
        ];

        return true;
    }

    public function logout(): void
    {
        unset($_SESSION['user_id']);
        unset($_SESSION['auth_user']);
    }

    private function dbAvailable(): bool
    {
        return in_array('mysql', \PDO::getAvailableDrivers(), true) && getenv('DB_DATABASE') !== false && getenv('DB_DATABASE') !== '';
    }
}
