<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Data\ExportStore;
use App\Data\ImportMap;
use App\Database\Connection;

final class ClientTaskRepository
{
    public function all(): array
    {
        if (!$this->dbAvailable()) {
            return $this->allFromExport();
        }

        return Connection::pdo()
            ->query(
                'SELECT client_tasks.*, clients.name AS client_name
                 FROM client_tasks
                 INNER JOIN clients ON clients.id = client_tasks.client_id
                 ORDER BY clients.name ASC, client_tasks.done ASC, client_tasks.created_at ASC, client_tasks.id ASC'
            )
            ->fetchAll();
    }

    public function create(int $clientId, string $title, string $priority = 'normal'): void
    {
        if (!$this->dbAvailable()) {
            throw new \RuntimeException('Database not configured.');
        }

        $stmt = Connection::pdo()->prepare(
            'INSERT INTO client_tasks (client_id, title, notes, done, done_date, priority)
             VALUES (:client_id, :title, NULL, 0, NULL, :priority)'
        );
        $stmt->execute([
            'client_id' => $clientId,
            'title' => $title,
            'priority' => in_array($priority, ['low', 'normal', 'high'], true) ? $priority : 'normal',
        ]);
    }

    public function toggle(int $id): void
    {
        if (!$this->dbAvailable()) {
            throw new \RuntimeException('Database not configured.');
        }

        $stmt = Connection::pdo()->prepare(
            'UPDATE client_tasks
             SET done = CASE WHEN done = 1 THEN 0 ELSE 1 END,
                 done_date = CASE WHEN done = 1 THEN NULL ELSE CURDATE() END
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public function delete(int $id): void
    {
        if (!$this->dbAvailable()) {
            throw new \RuntimeException('Database not configured.');
        }

        $stmt = Connection::pdo()->prepare('DELETE FROM client_tasks WHERE id = :id');
        $stmt->execute(['id' => $id]);
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
            $id = (string) ($client['id'] ?? $index + 1);
            $clientLabels[$id] = (string) ($client['name'] ?? ('Klijent ' . ($index + 1)));
        }

        $rows = ExportStore::data()['ClientTask'] ?? [];
        return array_map(static function (array $row, int $index) use ($clientLabels): array {
            $clientId = (string) ($row['client_id'] ?? '');
            return [
                'id' => $row['id'] ?? $index + 1,
                'client_id' => $clientId,
                'client_name' => ImportMap::clientLabel($clientId, $clientLabels[$clientId] ?? null),
                'title' => $row['title'] ?? 'Zadatak',
                'notes' => $row['notes'] ?? null,
                'done' => !empty($row['done']) ? 1 : 0,
                'done_date' => $row['done_date'] ?? null,
                'priority' => $row['priority'] ?? 'normal',
            ];
        }, $rows, array_keys($rows));
    }
}
