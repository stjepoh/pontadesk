<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use PDO;

final class ImportService
{
    /**
     * @param array<string, array<int, array<string, mixed>>> $bundledExports
     */
    public function importBundle(array $bundledExports, array $map = [], bool $reset = false): array
    {
        $clientExport = $bundledExports['Client'] ?? [];
        $contractExport = $bundledExports['Contract'] ?? [];
        $workLogExport = $bundledExports['WorkLog'] ?? [];
        $clientNoteExport = $bundledExports['ClientNote'] ?? [];
        $clientTaskExport = $bundledExports['ClientTask'] ?? [];
        $projectExport = $bundledExports['Project'] ?? [];
        $notificationSettingsExport = $bundledExports['NotificationSettings'] ?? [];

        $pdo = Connection::pdo();
        $this->prepareCharset($pdo);
        if ($reset) {
            $this->truncate($pdo);
        }

        $pdo->beginTransaction();
        try {
            $clients = $this->importClients($pdo, $clientExport);
            $clientLookup = $this->buildClientLookup($pdo, $map, $clientExport, $clients);

            $projects = $this->importProjects($pdo, $projectExport, $clientLookup);
            $contractStats = $this->importContracts($pdo, $contractExport, $clientLookup);
            $workLogStats = $this->importWorkLogs($pdo, $workLogExport, $clientLookup);
            $clientNoteStats = $this->importClientNotes($pdo, $clientNoteExport, $clientLookup);
            $clientTaskStats = $this->importClientTasks($pdo, $clientTaskExport, $clientLookup);
            $notificationSettingsCount = $this->importNotificationSettings($pdo, $notificationSettingsExport);

            $stats = [
                'clients' => count($clientExport),
                'contracts' => $contractStats['inserted'],
                'work_logs' => $workLogStats['inserted'],
                'client_notes' => $clientNoteStats['inserted'],
                'client_tasks' => $clientTaskStats['inserted'],
                'projects' => count($projects),
                'notification_settings' => $notificationSettingsCount,
                'missing_ids' => array_values(array_unique(array_merge($contractStats['missing'] ?? [], $workLogStats['missing'] ?? [], $clientNoteStats['missing'] ?? [], $clientTaskStats['missing'] ?? []))),
            ];
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $stats;
    }

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
            $clients = $this->importClients($pdo, $clientRows);
            $clientLookup = $this->buildClientLookup($pdo, $map, $clientRows, $clients);
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

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, int>
     */
    private function importClients(PDO $pdo, array $rows): array
    {
        $clients = [];
        foreach ($rows as $index => $client) {
            $newId = $this->insertClient($pdo, $client);
            $oldId = (string) ($client['id'] ?? ('client-' . ($index + 1)));
            $clients[$oldId] = $newId;
        }
        return $clients;
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
            $safeDatabase = preg_replace('/[^a-zA-Z0-9_]/', '', $database) ?: $database;
            $pdo->exec("ALTER DATABASE `{$safeDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
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

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, int|string> $clientLookup
     * @return array<int, int>
     */
    private function importProjects(PDO $pdo, array $rows, array $clientLookup): array
    {
        $stmt = $pdo->prepare('INSERT INTO projects (client_id, name, description, deadline, status, reminder, reminder_days, reminder_sent) VALUES (:client_id, :name, :description, :deadline, :status, :reminder, :reminder_days, :reminder_sent)');
        $projectIds = [];
        foreach ($rows as $index => $row) {
            $clientId = (string) ($row['client_id'] ?? '');
            if (!isset($clientLookup[$clientId])) {
                continue;
            }

            $stmt->execute([
                'client_id' => (int) $clientLookup[$clientId],
                'name' => (string) ($row['name'] ?? ('Projekt ' . ($index + 1))),
                'description' => $row['description'] ?? null,
                'deadline' => $row['deadline'] ?? null,
                'status' => $row['status'] ?? 'planning',
                'reminder' => !empty($row['reminder']) ? 1 : 0,
                'reminder_days' => (int) ($row['reminder_days'] ?? 3),
                'reminder_sent' => !empty($row['reminder_sent']) ? 1 : 0,
            ]);
            $projectIds[] = (int) $pdo->lastInsertId();
        }

        return $projectIds;
    }

    /**
     * @param array<int, int> $projectIds
     * @return array<int, int>
     */
    private function buildProjectLookup(array $projectIds): array
    {
        return $projectIds;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function importClientNotes(PDO $pdo, array $rows, array $clientLookup): array
    {
        $stmt = $pdo->prepare('INSERT INTO client_notes (client_id, title, content, category, importance, pinned, attachments) VALUES (:client_id, :title, :content, :category, :importance, :pinned, :attachments)');
        $inserted = 0;
        $missing = [];
        foreach ($rows as $row) {
            $clientId = (string) ($row['client_id'] ?? '');
            if (!isset($clientLookup[$clientId])) {
                $missing[] = $clientId;
                continue;
            }
            $stmt->execute([
                'client_id' => (int) $clientLookup[$clientId],
                'title' => (string) ($row['title'] ?? 'Bilješka'),
                'content' => $row['content'] ?? null,
                'category' => $row['category'] ?? 'other',
                'importance' => $row['importance'] ?? 'normal',
                'pinned' => !empty($row['pinned']) ? 1 : 0,
                'attachments' => !empty($row['attachments']) ? json_encode($row['attachments'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ]);
            $inserted++;
        }
        return ['inserted' => $inserted, 'missing' => $missing];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function importClientTasks(PDO $pdo, array $rows, array $clientLookup): array
    {
        $stmt = $pdo->prepare('INSERT INTO client_tasks (client_id, title, notes, done, done_date, priority) VALUES (:client_id, :title, :notes, :done, :done_date, :priority)');
        $inserted = 0;
        $missing = [];
        foreach ($rows as $row) {
            $clientId = (string) ($row['client_id'] ?? '');
            if (!isset($clientLookup[$clientId])) {
                $missing[] = $clientId;
                continue;
            }
            $stmt->execute([
                'client_id' => (int) $clientLookup[$clientId],
                'title' => (string) ($row['title'] ?? 'Zadatak'),
                'notes' => $row['notes'] ?? null,
                'done' => !empty($row['done']) ? 1 : 0,
                'done_date' => $row['done_date'] ?? null,
                'priority' => $row['priority'] ?? 'normal',
            ]);
            $inserted++;
        }
        return ['inserted' => $inserted, 'missing' => $missing];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function importNotificationSettings(PDO $pdo, array $rows): int
    {
        $stmt = $pdo->prepare('INSERT INTO notification_settings (user_id, project_deadline_warnings, project_status_changes, task_assignments, task_status_changes, client_interactions, calendar_reminders, email_notifications, in_app_notifications) VALUES (:user_id, :project_deadline_warnings, :project_status_changes, :task_assignments, :task_status_changes, :client_interactions, :calendar_reminders, :email_notifications, :in_app_notifications)');
        $count = 0;
        foreach ($rows as $row) {
            $stmt->execute([
                'user_id' => (string) ($row['user_id'] ?? 'stjepoh@gmail.com'),
                'project_deadline_warnings' => !empty($row['project_deadline_warnings']) ? 1 : 0,
                'project_status_changes' => !empty($row['project_status_changes']) ? 1 : 0,
                'task_assignments' => !empty($row['task_assignments']) ? 1 : 0,
                'task_status_changes' => !empty($row['task_status_changes']) ? 1 : 0,
                'client_interactions' => !empty($row['client_interactions']) ? 1 : 0,
                'calendar_reminders' => !empty($row['calendar_reminders']) ? 1 : 0,
                'email_notifications' => !empty($row['email_notifications']) ? 1 : 0,
                'in_app_notifications' => !empty($row['in_app_notifications']) ? 1 : 0,
            ]);
            $count++;
        }
        return $count;
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
