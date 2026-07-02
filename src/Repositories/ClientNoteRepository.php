<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Data\ExportStore;
use App\Data\ImportMap;
use App\Database\Connection;

final class ClientNoteRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        if (!$this->dbAvailable()) {
            return $this->allFromExport();
        }

        $this->ensureTable();

        return Connection::pdo()
            ->query(
                'SELECT client_notes.*, clients.name AS client_name
                 FROM client_notes
                 INNER JOIN clients ON clients.id = client_notes.client_id
                 ORDER BY clients.name ASC, client_notes.pinned DESC, client_notes.updated_at DESC, client_notes.id DESC'
            )
            ->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forClient(int $clientId, string $category = 'all', string $importance = 'all'): array
    {
        if (!$this->dbAvailable()) {
            return array_values(array_filter($this->allFromExport(), static function (array $row) use ($clientId, $category, $importance): bool {
                if ((int) ($row['client_id'] ?? 0) !== $clientId) {
                    return false;
                }
                if ($category !== 'all' && (string) ($row['category'] ?? 'other') !== $category) {
                    return false;
                }
                if ($importance !== 'all' && (string) ($row['importance'] ?? 'normal') !== $importance) {
                    return false;
                }
                return true;
            }));
        }

        $this->ensureTable();

        $sql = 'SELECT client_notes.*, clients.name AS client_name
                FROM client_notes
                INNER JOIN clients ON clients.id = client_notes.client_id
                WHERE client_notes.client_id = :client_id';
        $params = ['client_id' => $clientId];

        if ($category !== 'all') {
            $sql .= ' AND client_notes.category = :category';
            $params['category'] = $category;
        }
        if ($importance !== 'all') {
            $sql .= ' AND client_notes.importance = :importance';
            $params['importance'] = $importance;
        }

        $sql .= ' ORDER BY client_notes.pinned DESC, client_notes.updated_at DESC, client_notes.id DESC';

        $stmt = Connection::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @return array<int, int>
     */
    public function countsByClient(): array
    {
        if (!$this->dbAvailable()) {
            $counts = [];
            foreach ($this->allFromExport() as $row) {
                $clientId = (int) ($row['client_id'] ?? 0);
                if ($clientId > 0) {
                    $counts[$clientId] = ($counts[$clientId] ?? 0) + 1;
                }
            }
            return $counts;
        }

        $this->ensureTable();

        $rows = Connection::pdo()
            ->query('SELECT client_id, COUNT(*) AS total FROM client_notes GROUP BY client_id')
            ->fetchAll();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['client_id']] = (int) $row['total'];
        }

        return $counts;
    }

    public function create(array $data): void
    {
        if (!$this->dbAvailable()) {
            throw new \RuntimeException('Database not configured.');
        }

        $this->ensureTable();

        $stmt = Connection::pdo()->prepare(
            'INSERT INTO client_notes (client_id, title, content, category, importance, pinned, attachments)
             VALUES (:client_id, :title, :content, :category, :importance, :pinned, NULL)'
        );
        $stmt->execute([
            'client_id' => (int) $data['client_id'],
            'title' => (string) $data['title'],
            'content' => $data['content'] !== '' ? (string) $data['content'] : null,
            'category' => $this->validCategory((string) ($data['category'] ?? 'other')),
            'importance' => $this->validImportance((string) ($data['importance'] ?? 'normal')),
            'pinned' => !empty($data['pinned']) ? 1 : 0,
        ]);
    }

    public function delete(int $id): void
    {
        if (!$this->dbAvailable()) {
            throw new \RuntimeException('Database not configured.');
        }

        $this->ensureTable();

        $stmt = Connection::pdo()->prepare('DELETE FROM client_notes WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * @return array<string, string>
     */
    public function categoryOptions(): array
    {
        return [
            'technical' => 'Tehničko',
            'billing' => 'Financije',
            'communication' => 'Komunikacija',
            'access' => 'Pristupi',
            'legal' => 'Pravno',
            'other' => 'Ostalo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function importanceOptions(): array
    {
        return [
            'low' => 'Nisko',
            'normal' => 'Normalno',
            'high' => 'Važno',
            'critical' => 'Kritično',
        ];
    }

    private function validCategory(string $category): string
    {
        return array_key_exists($category, $this->categoryOptions()) ? $category : 'other';
    }

    private function validImportance(string $importance): string
    {
        return array_key_exists($importance, $this->importanceOptions()) ? $importance : 'normal';
    }

    private function dbAvailable(): bool
    {
        return in_array('mysql', \PDO::getAvailableDrivers(), true) && getenv('DB_DATABASE') !== false && getenv('DB_DATABASE') !== '';
    }

    private function ensureTable(): void
    {
        Connection::pdo()->exec(
            "CREATE TABLE IF NOT EXISTS client_notes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                client_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(191) NOT NULL,
                content TEXT NULL,
                category ENUM('technical','billing','communication','access','legal','other') NOT NULL DEFAULT 'other',
                importance ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
                pinned TINYINT(1) NOT NULL DEFAULT 0,
                attachments JSON NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_client_notes_client_id (client_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function allFromExport(): array
    {
        $export = $this->exportData();
        $clients = $export['Client'] ?? [];
        $clientIds = [];
        $clientLabels = [];

        foreach ($clients as $index => $client) {
            $fallbackId = (string) ($index + 1);
            $id = (string) ($client['id'] ?? $fallbackId);
            $name = (string) ($client['name'] ?? ('Klijent ' . $fallbackId));
            $clientIds[$id] = (int) $fallbackId;
            $clientLabels[$id] = $name;
        }

        foreach (ImportMap::load()['clients'] ?? [] as $oldId => $clientName) {
            foreach ($clients as $index => $client) {
                if ((string) ($client['name'] ?? '') === (string) $clientName) {
                    $clientIds[(string) $oldId] = $index + 1;
                    $clientLabels[(string) $oldId] = (string) $clientName;
                    break;
                }
            }
        }

        $rows = $export['ClientNote'] ?? [];
        return array_map(static function (array $row, int $index) use ($clientIds, $clientLabels): array {
            $oldClientId = (string) ($row['client_id'] ?? '');
            return [
                'id' => $row['id'] ?? $index + 1,
                'client_id' => $clientIds[$oldClientId] ?? 0,
                'client_name' => ImportMap::clientLabel($oldClientId, $clientLabels[$oldClientId] ?? null),
                'title' => $row['title'] ?? 'Bilješka',
                'content' => $row['content'] ?? '',
                'category' => $row['category'] ?? 'other',
                'importance' => $row['importance'] ?? 'normal',
                'pinned' => !empty($row['pinned']) ? 1 : 0,
                'created_at' => $row['created_at'] ?? null,
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }, $rows, array_keys($rows));
    }

    /**
     * @return array<string, mixed>
     */
    private function exportData(): array
    {
        $data = ExportStore::data();
        if ($data !== []) {
            return $data;
        }

        $path = dirname(__DIR__, 2) . '/data/base44-bundle.json';
        if (!is_file($path)) {
            return [];
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
