<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use PDO;

final class ImportService
{
    public function import(array $export, array $map = [], bool $reset = false): array
    {
        $pdo = Connection::pdo();
        $this->prepareCharset($pdo);
        if ($reset) {
            $this->truncate($pdo);
        }

        $pdo->beginTransaction();
        try {
            $clientRows = $export['Client'] ?? [];
            $clientIds = [];

            foreach ($clientRows as $index => $client) {
                $newId = $this->insertClient($pdo, $client);
                $oldId = (string) ($client['id'] ?? ('client-' . ($index + 1)));
                $clientIds[$oldId] = $newId;
            }

            $clientLookup = $this->buildClientLookup($pdo, $map, $clientRows, $clientIds);
            $contractStats = $this->importContracts($pdo, $export['Contract'] ?? [], $clientLookup);
            $workLogStats = $this->importWorkLogs($pdo, $export['WorkLog'] ?? [], $clientLookup);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return [
            'clients' => count($clientRows),
            'contracts' => $contractStats['inserted'] ?? 0,
            'work_logs' => $workLogStats['inserted'] ?? 0,
            'missing_ids' => array_values(array_unique(array_merge($contractStats['missing'] ?? [], $workLogStats['missing'] ?? []))),
        ];
    }

    private function truncate(PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (['client_tasks', 'client_notes', 'project_tasks', 'projects', 'work_logs', 'contracts', 'clients', 'users'] as $table) {
            $pdo->exec("TRUNCATE TABLE {$table}");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    private function prepareCharset(PDO $pdo): void
    {
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("SET CHARACTER SET utf8mb4");

        $database = getenv('DB_DATABASE');
        if (is_string($database) && $database !== '') {
            $quoted = $pdo->quote($database);
            $pdo->exec("ALTER DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        foreach (['users', 'clients', 'contracts', 'work_logs', 'projects', 'project_tasks', 'client_notes', 'client_tasks'] as $table) {
            $pdo->exec("ALTER TABLE {$table} CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
    }

    private function insertClient(PDO $pdo, array $client): int
    {
        $stmt = $pdo->prepare('INSERT INTO clients (name, contact_person, email, secondary_emails, phone, secondary_phones, address, city, postal_code, country, vat_id, website, category, sales_stage, industry, company_size, hourly_rate, track_work, notes) VALUES (:name, :contact_person, :email, :secondary_emails, :phone, :secondary_phones, :address, :city, :postal_code, :country, :vat_id, :website, :category, :sales_stage, :industry, :company_size, :hourly_rate, :track_work, :notes)');
        $stmt->execute([
            'name' => (string) ($client['name'] ?? ''),
            'contact_person' => $client['contact_person'] ?? null,
            'email' => $client['email'] ?? null,
            'secondary_emails' => !empty($client['secondary_emails']) ? json_encode($client['secondary_emails'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'phone' => $client['phone'] ?? null,
            'secondary_phones' => !empty($client['secondary_phones']) ? json_encode($client['secondary_phones'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'address' => $client['address'] ?? null,
            'city' => $client['city'] ?? null,
            'postal_code' => $client['postal_code'] ?? null,
            'country' => $client['country'] ?? null,
            'vat_id' => $client['vat_id'] ?? null,
            'website' => $client['website'] ?? null,
            'category' => $client['category'] ?? 'regular',
            'sales_stage' => $client['sales_stage'] ?? 'lead',
            'industry' => $client['industry'] ?? null,
            'company_size' => $client['company_size'] ?? null,
            'hourly_rate' => $client['hourly_rate'] ?? null,
            'track_work' => !empty($client['track_work']) ? 1 : 0,
            'notes' => $client['notes'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    private function buildClientLookup(PDO $pdo, array $map, array $clientRows, array $clientIds): array
    {
        $lookup = $clientIds;
        foreach (($map['clients'] ?? []) as $oldId => $clientName) {
            $stmt = $pdo->prepare('SELECT id FROM clients WHERE name = :name ORDER BY id ASC LIMIT 1');
            $stmt->execute(['name' => $clientName]);
            $id = $stmt->fetchColumn();
            if ($id !== false) {
                $lookup[(string) $oldId] = (string) $id;
            }
        }

        foreach ($clientRows as $index => $client) {
            $name = trim((string) ($client['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $stmt = $pdo->prepare('SELECT id FROM clients WHERE name = :name ORDER BY id ASC LIMIT 1');
            $stmt->execute(['name' => $name]);
            $id = $stmt->fetchColumn();
            if ($id !== false) {
                $lookup[(string) ($client['id'] ?? ('client-' . ($index + 1)))] = (string) $id;
            }
        }

        return $lookup;
    }

    private function importContracts(PDO $pdo, array $rows, array $lookup): array
    {
        $stmt = $pdo->prepare('INSERT INTO contracts (client_id, contract_name, contract_number, start_date, end_date, status, contract_file_url, contract_file_name, value, maintenance_billing_period, maintenance_amount, auto_renewal, reminder_days, notes) VALUES (:client_id, :contract_name, :contract_number, :start_date, :end_date, :status, :contract_file_url, :contract_file_name, :value, :maintenance_billing_period, :maintenance_amount, :auto_renewal, :reminder_days, :notes)');
        $inserted = 0;
        $missing = [];

        foreach ($rows as $row) {
            $oldClientId = (string) ($row['client_id'] ?? '');
            if (!isset($lookup[$oldClientId])) {
                $missing[] = $oldClientId;
                continue;
            }

            $stmt->execute([
                'client_id' => (int) $lookup[$oldClientId],
                'contract_name' => (string) ($row['contract_name'] ?? 'Ugovor'),
                'contract_number' => $row['contract_number'] ?? null,
                'start_date' => $row['start_date'] ?? date('Y-m-d'),
                'end_date' => $row['end_date'] ?? date('Y-m-d'),
                'status' => $row['status'] ?? 'active',
                'contract_file_url' => $row['contract_file_url'] ?? null,
                'contract_file_name' => $row['contract_file_name'] ?? null,
                'value' => $row['value'] ?? null,
                'maintenance_billing_period' => $row['maintenance_billing_period'] ?? 'none',
                'maintenance_amount' => $row['maintenance_amount'] ?? null,
                'auto_renewal' => !empty($row['auto_renewal']) ? 1 : 0,
                'reminder_days' => (int) ($row['reminder_days'] ?? 14),
                'notes' => $row['notes'] ?? null,
            ]);
            $inserted++;
        }

        return ['inserted' => $inserted, 'missing' => $missing];
    }

    private function importWorkLogs(PDO $pdo, array $rows, array $lookup): array
    {
        $stmt = $pdo->prepare('INSERT INTO work_logs (client_id, work_date, duration_minutes, description, billed) VALUES (:client_id, :work_date, :duration_minutes, :description, :billed)');
        $inserted = 0;
        $missing = [];

        foreach ($rows as $row) {
            $oldClientId = (string) ($row['client_id'] ?? '');
            if (!isset($lookup[$oldClientId])) {
                $missing[] = $oldClientId;
                continue;
            }

            $stmt->execute([
                'client_id' => (int) $lookup[$oldClientId],
                'work_date' => $row['date'] ?? date('Y-m-d'),
                'duration_minutes' => (int) ($row['duration_minutes'] ?? 0),
                'description' => (string) ($row['description'] ?? ''),
                'billed' => !empty($row['billed']) ? 1 : 0,
            ]);
            $inserted++;
        }

        return ['inserted' => $inserted, 'missing' => $missing];
    }
}
