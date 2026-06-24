<?php
declare(strict_types=1);

require __DIR__ . '/../src/Support/Env.php';

use App\Support\Env;

Env::load(dirname(__DIR__) . '/.env');

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

function usage(): void
{
    $message = <<<TXT
PontaDesk import alat

Koristi:
  php scripts/import-export.php --export="C:\\putanja\\do\\exporta.json" --map="data\\import-map.json"

Opcije:
  --export   Putanja do Base44 JSON izvoza. Ako nije zadana, koristi se desktop export ili data/pontadesk_backup.json.
  --map      Putanja do import mape stare ID -> naziv klijenta. Zadano: data/import-map.json
  --reset    Očisti postojece podatke prije importa.

TXT;
    fwrite(STDOUT, $message);
}

function parseOptions(array $argv): array
{
    $options = [
        'export' => getenv('PONTADESK_EXPORT_JSON') ?: null,
        'map' => dirname(__DIR__) . '/data/import-map.json',
        'reset' => false,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--reset') {
            $options['reset'] = true;
            continue;
        }
        if (str_starts_with($arg, '--export=')) {
            $options['export'] = substr($arg, 9);
            continue;
        }
        if (str_starts_with($arg, '--map=')) {
            $options['map'] = substr($arg, 6);
            continue;
        }
    }

    return $options;
}

function resolveExportPath(?string $path): ?string
{
    if ($path !== null && $path !== '' && is_file($path)) {
        return $path;
    }

    $candidates = [
        dirname(__DIR__) . '/data/pontadesk_backup.json',
        'C:\\Users\\Stjepo\\Desktop\\fda81651f_pontadesk_backup_2026-06-24.json',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function decodeJsonFile(string $path): array
{
    $json = file_get_contents($path);
    if ($json === false) {
        throw new RuntimeException('Ne mogu procitati: ' . $path);
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        throw new RuntimeException('Neispravan JSON: ' . $path);
    }

    return $data;
}

function normalizeString(?string $value): string
{
    $value = trim((string) $value);
    return mb_strtolower($value);
}

function importMapPath(string $path): string
{
    return $path;
}

function loadClientMap(string $path): array
{
    if (!is_file($path)) {
        return ['clients' => []];
    }

    $data = decodeJsonFile($path);
    $data['clients'] = $data['clients'] ?? [];
    return $data;
}

function saveClientMap(string $path, array $map): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents($path, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
}

function pdo(): PDO
{
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $database = getenv('DB_DATABASE') ?: '';
    $username = getenv('DB_USERNAME') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';
    $charset = getenv('DB_CHARSET') ?: 'utf8mb4';

    if ($database === '') {
        throw new RuntimeException('DB_DATABASE nije postavljen u .env datoteci.');
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $database, $charset);
    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function truncateTables(PDO $pdo): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach (['client_tasks', 'client_notes', 'project_tasks', 'projects', 'work_logs', 'contracts', 'clients', 'users'] as $table) {
        $pdo->exec("TRUNCATE TABLE {$table}");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}

function clientKey(array $client): string
{
    return normalizeString((string) ($client['name'] ?? '')) . '|' . normalizeString((string) ($client['email'] ?? '')) . '|' . normalizeString((string) ($client['contact_person'] ?? ''));
}

function insertClient(PDO $pdo, array $client): int
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

function findClientIdByName(PDO $pdo, string $name): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM clients WHERE name = :name LIMIT 1');
    $stmt->execute(['name' => $name]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int) $id : null;
}

function buildClientLookup(PDO $pdo, array $map): array
{
    $lookup = [];
    foreach (($map['clients'] ?? []) as $oldId => $clientName) {
        $clientId = findClientIdByName($pdo, (string) $clientName);
        if ($clientId !== null) {
            $lookup[(string) $oldId] = $clientId;
        }
    }

    return $lookup;
}

function importContracts(PDO $pdo, array $export, array $lookup): array
{
    $inserted = 0;
    $skipped = [];
    $stmt = $pdo->prepare('INSERT INTO contracts (client_id, contract_name, contract_number, start_date, end_date, status, contract_file_url, contract_file_name, value, maintenance_billing_period, maintenance_amount, auto_renewal, reminder_days, notes) VALUES (:client_id, :contract_name, :contract_number, :start_date, :end_date, :status, :contract_file_url, :contract_file_name, :value, :maintenance_billing_period, :maintenance_amount, :auto_renewal, :reminder_days, :notes)');

    foreach (($export['Contract'] ?? []) as $row) {
        $oldClientId = (string) ($row['client_id'] ?? '');
        $clientId = $lookup[$oldClientId] ?? null;
        if ($clientId === null) {
            $skipped[$oldClientId] = true;
            continue;
        }

        $stmt->execute([
            'client_id' => $clientId,
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

    return ['inserted' => $inserted, 'skipped' => array_keys($skipped)];
}

function importWorkLogs(PDO $pdo, array $export, array $lookup): array
{
    $inserted = 0;
    $skipped = [];
    $stmt = $pdo->prepare('INSERT INTO work_logs (client_id, work_date, duration_minutes, description, billed) VALUES (:client_id, :work_date, :duration_minutes, :description, :billed)');

    foreach (($export['WorkLog'] ?? []) as $row) {
        $oldClientId = (string) ($row['client_id'] ?? '');
        $clientId = $lookup[$oldClientId] ?? null;
        if ($clientId === null) {
            $skipped[$oldClientId] = true;
            continue;
        }

        $stmt->execute([
            'client_id' => $clientId,
            'work_date' => $row['date'] ?? date('Y-m-d'),
            'duration_minutes' => (int) ($row['duration_minutes'] ?? 0),
            'description' => (string) ($row['description'] ?? ''),
            'billed' => !empty($row['billed']) ? 1 : 0,
        ]);
        $inserted++;
    }

    return ['inserted' => $inserted, 'skipped' => array_keys($skipped)];
}

$options = parseOptions($argv);

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    usage();
    exit(0);
}

$exportPath = resolveExportPath($options['export']);
if ($exportPath === null) {
    fwrite(STDERR, "Ne mogu pronaci export JSON.\n");
    exit(1);
}

$mapPath = importMapPath((string) $options['map']);
$export = decodeJsonFile($exportPath);
$map = loadClientMap($mapPath);
$pdo = pdo();

if ($options['reset']) {
    truncateTables($pdo);
}

$pdo->beginTransaction();

try {
    $clients = $export['Client'] ?? [];
    foreach ($clients as $client) {
        insertClient($pdo, $client);
    }

    $clientLookup = buildClientLookup($pdo, $map);

    $contractStats = importContracts($pdo, $export, $clientLookup);
    $workLogStats = importWorkLogs($pdo, $export, $clientLookup);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Import nije uspio: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Import zavrsen.\n");
fwrite(STDOUT, 'Klijenti: ' . count($clients) . PHP_EOL);
fwrite(STDOUT, 'Ugovori: ' . ($contractStats['inserted'] ?? 0) . PHP_EOL);
fwrite(STDOUT, 'Radovi: ' . ($workLogStats['inserted'] ?? 0) . PHP_EOL);

if (($contractStats['skipped'] ?? []) !== [] || ($workLogStats['skipped'] ?? []) !== []) {
    fwrite(STDOUT, "Neusklađeni stari client_id zapisi: " . implode(', ', array_values(array_unique(array_merge($contractStats['skipped'] ?? [], $workLogStats['skipped'] ?? [])))) . PHP_EOL);
    fwrite(STDOUT, "Dodaj ih u data/import-map.json kao mapiranje stare ID vrijednosti na naziv klijenta.\n");
}
