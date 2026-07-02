<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Data\ExportStore;
use App\Database\Connection;

final class ContractRepository
{
    public function all(string $status = 'active', int $clientId = 0): array
    {
        if (!$this->dbAvailable()) {
            return $this->filterRows($this->allFromExport(), $status, $clientId);
        }

        $sql = 'SELECT contracts.*, clients.name AS client_name
                FROM contracts
                INNER JOIN clients ON clients.id = contracts.client_id';
        $where = [];
        $params = [];

        if ($status !== 'all') {
            $where[] = 'contracts.status = :status';
            $params['status'] = $status;
        }
        if ($clientId > 0) {
            $where[] = 'contracts.client_id = :client_id';
            $params['client_id'] = $clientId;
        }

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY end_date ASC, clients.name ASC, contracts.id ASC';

        $stmt = Connection::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function create(array $data): void
    {
        if (!$this->dbAvailable()) {
            throw new \RuntimeException('Database not configured.');
        }
        $stmt = Connection::pdo()->prepare('INSERT INTO contracts (client_id, contract_name, contract_number, start_date, end_date, status, contract_file_url, contract_file_name, value, maintenance_billing_period, maintenance_amount, auto_renewal, reminder_days, notes) VALUES (:client_id, :contract_name, :contract_number, :start_date, :end_date, :status, :contract_file_url, :contract_file_name, :value, :maintenance_billing_period, :maintenance_amount, :auto_renewal, :reminder_days, :notes)');
        $stmt->execute($data);
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

        $stmt = Connection::pdo()->prepare('SELECT contracts.*, clients.name AS client_name FROM contracts INNER JOIN clients ON clients.id = contracts.client_id WHERE contracts.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(int $id, array $data): void
    {
        if (!$this->dbAvailable()) {
            throw new \RuntimeException('Database not configured.');
        }
        $data['id'] = $id;
        $stmt = Connection::pdo()->prepare('UPDATE contracts SET client_id = :client_id, contract_name = :contract_name, contract_number = :contract_number, start_date = :start_date, end_date = :end_date, status = :status, contract_file_url = :contract_file_url, contract_file_name = :contract_file_name, value = :value, maintenance_billing_period = :maintenance_billing_period, maintenance_amount = :maintenance_amount, auto_renewal = :auto_renewal, reminder_days = :reminder_days, notes = :notes WHERE id = :id');
        $stmt->execute($data);
    }

    public function delete(int $id): void
    {
        if (!$this->dbAvailable()) {
            throw new \RuntimeException('Database not configured.');
        }
        $stmt = Connection::pdo()->prepare('DELETE FROM contracts WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function dbAvailable(): bool
    {
        return in_array('mysql', \PDO::getAvailableDrivers(), true) && getenv('DB_DATABASE') !== false && getenv('DB_DATABASE') !== '';
    }

    private function allFromExport(): array
    {
        $clients = ExportStore::data()['Client'] ?? [];
        $byId = [];
        foreach ($clients as $index => $client) {
            $byId[(string)($client['id'] ?? $index + 1)] = $client['name'] ?? '';
        }
        $rows = ExportStore::data()['Contract'] ?? [];
        return array_map(static function (array $row, int $index) use ($byId): array {
            $clientId = (string)($row['client_id'] ?? '');
            return [
                'id' => $row['id'] ?? $index + 1,
                'client_id' => $clientId,
                'client_name' => $byId[$clientId] ?? $clientId,
                'contract_name' => $row['contract_name'] ?? '',
                'contract_number' => $row['contract_number'] ?? null,
                'start_date' => $row['start_date'] ?? '',
                'end_date' => $row['end_date'] ?? '',
                'status' => $row['status'] ?? 'active',
                'value' => $row['value'] ?? null,
                'maintenance_billing_period' => $row['maintenance_billing_period'] ?? 'none',
                'maintenance_amount' => $row['maintenance_amount'] ?? null,
                'auto_renewal' => !empty($row['auto_renewal']) ? 1 : 0,
                'reminder_days' => $row['reminder_days'] ?? 14,
                'notes' => $row['notes'] ?? null,
            ];
        }, $rows, array_keys($rows));
    }

    private function filterRows(array $rows, string $status, int $clientId): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($status, $clientId): bool {
            if ($status !== 'all' && (string) ($row['status'] ?? 'active') !== $status) {
                return false;
            }
            if ($clientId > 0 && (int) ($row['client_id'] ?? 0) !== $clientId) {
                return false;
            }
            return true;
        }));
    }
}
