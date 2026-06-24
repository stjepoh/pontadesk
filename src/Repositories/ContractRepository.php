<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Data\ExportStore;
use App\Database\Connection;

final class ContractRepository
{
    public function all(): array
    {
        if (!$this->dbAvailable()) {
            return $this->allFromExport();
        }
        return Connection::pdo()->query('SELECT contracts.*, clients.name AS client_name FROM contracts INNER JOIN clients ON clients.id = contracts.client_id ORDER BY end_date ASC')->fetchAll();
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
        return getenv('DB_DATABASE') !== false && getenv('DB_DATABASE') !== '';
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
                'client_name' => $byId[$clientId] ?? $clientId,
                'contract_name' => $row['contract_name'] ?? '',
                'end_date' => $row['end_date'] ?? '',
                'status' => $row['status'] ?? 'active',
            ];
        }, $rows, array_keys($rows));
    }
}
