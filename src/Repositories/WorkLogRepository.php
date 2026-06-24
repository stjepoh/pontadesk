<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Data\ExportStore;
use App\Data\ImportMap;
use App\Database\Connection;

final class WorkLogRepository
{
    public function all(): array
    {
        if (!$this->dbAvailable()) {
            return $this->allFromExport();
        }
        return Connection::pdo()->query('SELECT work_logs.*, clients.name AS client_name FROM work_logs INNER JOIN clients ON clients.id = work_logs.client_id ORDER BY work_date DESC, id DESC')->fetchAll();
    }

    public function create(array $data): void
    {
        if (!$this->dbAvailable()) {
            throw new \RuntimeException('Database not configured.');
        }
        $stmt = Connection::pdo()->prepare('INSERT INTO work_logs (client_id, work_date, duration_minutes, description, billed) VALUES (:client_id, :work_date, :duration_minutes, :description, :billed)');
        $stmt->execute($data);
    }

    public function delete(int $id): void
    {
        if (!$this->dbAvailable()) {
            throw new \RuntimeException('Database not configured.');
        }
        $stmt = Connection::pdo()->prepare('DELETE FROM work_logs WHERE id = :id');
        $stmt->execute(['id' => $id]);
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

        $stmt = Connection::pdo()->prepare('SELECT work_logs.*, clients.name AS client_name FROM work_logs INNER JOIN clients ON clients.id = work_logs.client_id WHERE work_logs.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(int $id, array $data): void
    {
        if (!$this->dbAvailable()) {
            throw new \RuntimeException('Database not configured.');
        }

        $stmt = Connection::pdo()->prepare('UPDATE work_logs SET client_id = :client_id, work_date = :work_date, duration_minutes = :duration_minutes, description = :description, billed = :billed WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'client_id' => $data['client_id'],
            'work_date' => $data['work_date'],
            'duration_minutes' => $data['duration_minutes'],
            'description' => $data['description'],
            'billed' => $data['billed'],
        ]);
    }

    private function dbAvailable(): bool
    {
        return getenv('DB_DATABASE') !== false && getenv('DB_DATABASE') !== '';
    }

    private function allFromExport(): array
    {
        $clients = ExportStore::data()['Client'] ?? [];
        $clientLabels = [];
        foreach ($clients as $index => $client) {
            $label = (string) ($client['name'] ?? ('Klijent ' . ($index + 1)));
            if (isset($client['id'])) {
                $clientLabels[(string) $client['id']] = $label;
            }
            $clientLabels[(string) ($client['name'] ?? '')] = $label;
        }
        $rows = ExportStore::data()['WorkLog'] ?? [];
        return array_map(static function (array $row, int $index) use ($clientLabels): array {
            $clientId = (string)($row['client_id'] ?? '');
            return [
                'id' => $row['id'] ?? $index + 1,
                'client_id' => $clientId,
                'client_name' => ImportMap::clientLabel($clientId, $clientLabels[$clientId] ?? null),
                'work_date' => $row['date'] ?? '',
                'duration_minutes' => $row['duration_minutes'] ?? 0,
                'billed' => !empty($row['billed']) ? 1 : 0,
                'description' => $row['description'] ?? '',
            ];
        }, $rows, array_keys($rows));
    }
}
