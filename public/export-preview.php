<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

header('Content-Type: text/html; charset=UTF-8');

$clients = (new App\Repositories\ClientRepository())->all();
$contracts = (new App\Repositories\ContractRepository())->all();
$workLogs = (new App\Repositories\WorkLogRepository())->all();

echo '<!doctype html><html lang="hr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>PontaDesk Preview</title><style>body{font-family:Arial,sans-serif;background:#0f172a;color:#e2e8f0;margin:0;padding:32px}.wrap{max-width:1200px;margin:0 auto}.card{background:#111827;border:1px solid #334155;border-radius:16px;padding:24px;margin-bottom:18px}table{width:100%;border-collapse:collapse}th,td{padding:10px;border-bottom:1px solid #233047;text-align:left}.muted{color:#94a3b8}a{color:#93c5fd}</style></head><body><div class="wrap"><div class="card"><h1>PontaDesk Preview</h1><p class="muted">Prikaz podataka iz JSON exporta.</p><p><a href="/">Nazad na aplikaciju</a></p></div><div class="card"><h2>Klijenti</h2><p class="muted">Ukupno: '.count($clients).'</p><table><tr><th>Naziv</th><th>Kontakt</th><th>Kategorija</th></tr>';
foreach (array_slice($clients, 0, 8) as $row) { echo '<tr><td>'.htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8').'</td><td>'.htmlspecialchars((string)($row['contact_person'] ?? ''), ENT_QUOTES, 'UTF-8').'</td><td>'.htmlspecialchars((string)$row['category'], ENT_QUOTES, 'UTF-8').'</td></tr>'; }
echo '</table></div><div class="card"><h2>Ugovori</h2><p class="muted">Ukupno: '.count($contracts).'</p><table><tr><th>Klijent</th><th>Naziv</th><th>Rok</th></tr>';
foreach (array_slice($contracts, 0, 8) as $row) { echo '<tr><td>'.htmlspecialchars((string)$row['client_name'], ENT_QUOTES, 'UTF-8').'</td><td>'.htmlspecialchars((string)$row['contract_name'], ENT_QUOTES, 'UTF-8').'</td><td>'.htmlspecialchars((new DateTimeImmutable((string)$row['end_date']))->format('d/m/Y'), ENT_QUOTES, 'UTF-8').'</td></tr>'; }
echo '</table></div><div class="card"><h2>Radni sati</h2><p class="muted">Ukupno: '.count($workLogs).'</p><table><tr><th>Klijent</th><th>Datum</th><th>Trajanje</th></tr>';
foreach (array_slice($workLogs, 0, 8) as $row) { echo '<tr><td>'.htmlspecialchars((string)$row['client_name'], ENT_QUOTES, 'UTF-8').'</td><td>'.htmlspecialchars((new DateTimeImmutable((string)$row['work_date']))->format('d/m/Y'), ENT_QUOTES, 'UTF-8').'</td><td>'.(int)$row['duration_minutes'].' min</td></tr>'; }
echo '</table></div></div></body></html>';
