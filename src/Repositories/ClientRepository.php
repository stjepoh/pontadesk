<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Data\ExportStore;
use App\Database\Connection;
use App\Models\Client;
use PDO;

final class ClientRepository
{
    public function all(?string $search = null): array
    {
        if (!$this->dbAvailable()) {
            return $this->allFromExport($search);
        }
        $pdo = Connection::pdo();
        $sql = 'SELECT * FROM clients';
        $params = [];

        if ($search !== null && $search !== '') {
            $sql .= ' WHERE name LIKE :search OR email LIKE :search OR contact_person LIKE :search OR vat_id LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY name ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        if (!$this->dbAvailable()) {
            foreach ($this->allFromExport() as $row) {
                if ((int) ($row['id'] ?? 0) === $id) {
                    return $row;
                }
            }
            return null;
        }
        $stmt = Connection::pdo()->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function create(Client $client): int
    {
        if (!$this->dbAvailable()) {
            throw new \RuntimeException('Database not configured.');
        }
        $stmt = Connection::pdo()->prepare(
            'INSERT INTO clients (name, contact_person, email, phone, category, sales_stage, industry, hourly_rate, track_work, notes)
             VALUES (:name, :contact_person, :email, :phone, :category, :sales_stage, :industry, :hourly_rate, :track_work, :notes)'
        );

        $stmt->execute([
            'name' => $client->name,
            'contact_person' => $client->contactPerson,
            'email' => $client->email,
            'phone' => $client->phone,
            'category' => $client->category,
            'sales_stage' => $client->salesStage,
            'industry' => $client->industry,
            'hourly_rate' => $client->hourlyRate,
            'track_work' => $client->trackWork ? 1 : 0,
            'notes' => $client->notes,
        ]);

        return (int) Connection::pdo()->lastInsertId();
    }

    public function update(int $id, Client $client): void
    {
        if (!$this->dbAvailable()) {
            throw new \RuntimeException('Database not configured.');
        }
        $stmt = Connection::pdo()->prepare(
            'UPDATE clients SET name = :name, contact_person = :contact_person, email = :email, phone = :phone,
             category = :category, sales_stage = :sales_stage, industry = :industry, hourly_rate = :hourly_rate,
             track_work = :track_work, notes = :notes WHERE id = :id'
        );

        $stmt->execute([
            'id' => $id,
            'name' => $client->name,
            'contact_person' => $client->contactPerson,
            'email' => $client->email,
            'phone' => $client->phone,
            'category' => $client->category,
            'sales_stage' => $client->salesStage,
            'industry' => $client->industry,
            'hourly_rate' => $client->hourlyRate,
            'track_work' => $client->trackWork ? 1 : 0,
            'notes' => $client->notes,
        ]);
    }

    public function delete(int $id): void
    {
        if (!$this->dbAvailable()) {
            throw new \RuntimeException('Database not configured.');
        }
        $stmt = Connection::pdo()->prepare('DELETE FROM clients WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function dbAvailable(): bool
    {
        return getenv('DB_DATABASE') !== false && getenv('DB_DATABASE') !== '';
    }

    private function allFromExport(?string $search = null): array
    {
        $rows = ExportStore::data()['Client'] ?? [];
        $rows = array_map(static function (array $row, int $index): array {
            return [
                'id' => $row['id'] ?? $index + 1,
                'name' => $row['name'] ?? '',
                'contact_person' => $row['contact_person'] ?? null,
                'email' => $row['email'] ?? null,
                'phone' => $row['phone'] ?? null,
                'category' => $row['category'] ?? 'regular',
                'sales_stage' => $row['sales_stage'] ?? 'lead',
                'industry' => $row['industry'] ?? null,
                'hourly_rate' => $row['hourly_rate'] ?? null,
                'track_work' => !empty($row['track_work']) ? 1 : 0,
                'notes' => $row['notes'] ?? null,
            ];
        }, $rows, array_keys($rows));

        if ($search !== null && $search !== '') {
            $needle = mb_strtolower($search);
            $rows = array_values(array_filter($rows, static function (array $row) use ($needle): bool {
                $haystack = mb_strtolower(implode(' ', array_filter([
                    (string)($row['name'] ?? ''),
                    (string)($row['email'] ?? ''),
                    (string)($row['contact_person'] ?? ''),
                    (string)($row['notes'] ?? ''),
                ])));
                return str_contains($haystack, $needle);
            }));
        }

        usort($rows, static fn(array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));
        return $rows;
    }
}
