<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\ClientRepository;
use App\Repositories\ContractRepository;
use App\Repositories\WorkLogRepository;

final class DashboardController extends AdminController
{
    public function index(): void
    {
        $this->requireAdmin();

        $clients = (new ClientRepository())->all();
        $contracts = (new ContractRepository())->all();
        $workLogs = (new WorkLogRepository())->all();

        $activeContracts = count(array_filter($contracts, static fn(array $row): bool => ($row['status'] ?? '') === 'active'));
        $billedWork = count(array_filter($workLogs, static fn(array $row): bool => (int) ($row['billed'] ?? 0) === 1));
        $unbilledWork = count($workLogs) - $billedWork;

        $content = '';
        $content .= '<section class="panel pad" style="background:linear-gradient(135deg,#fff 0,#f8fbff 100%);margin-bottom:18px">';
        $content .= '<div class="toolbar"><div><div class="chip">Lokalni pregled podataka</div><div class="muted" style="margin-top:8px">Aplikacija već čita JSON export i spremna je za daljnje modularno slaganje.</div></div><div class="toplinks"><a class="btn" href="/clients">Otvori klijente</a><a class="btn secondary" href="/contracts">Ugovori</a><a class="btn secondary" href="/work-logs">Radovi</a><a class="btn secondary" href="/export-preview.php">Export</a></div></div>';
        $content .= '</section>';

        $content .= '<section class="grid-4">';
        $content .= '<div class="panel stat"><div class="label">Klijenti</div><div class="value">' . count($clients) . '</div><div class="sub">Ukupno evidentiranih klijenata</div></div>';
        $content .= '<div class="panel stat"><div class="label">Ugovori</div><div class="value">' . count($contracts) . '</div><div class="sub">' . $activeContracts . ' aktivnih ugovora</div></div>';
        $content .= '<div class="panel stat"><div class="label">Radni sati</div><div class="value">' . count($workLogs) . '</div><div class="sub">' . $billedWork . ' naplaćeno, ' . $unbilledWork . ' nenaplaćeno</div></div>';
        $content .= '<div class="panel stat"><div class="label">Izvor</div><div class="value">JSON</div><div class="sub">Fallback na export</div></div>';
        $content .= '</section>';

        $content .= '<div class="grid-2" style="margin-top:18px">';
        $content .= '<section class="panel pad"><div class="section-title"><h2>Najnoviji klijenti</h2><a class="muted" href="/clients">Svi klijenti</a></div><div class="mini-list">';
        foreach (array_slice($clients, 0, 6) as $client) {
            $content .= '<div class="mini-item"><div><strong>' . htmlspecialchars((string) $client['name'], ENT_QUOTES, 'UTF-8') . '</strong><div class="muted">' . htmlspecialchars((string) ($client['contact_person'] ?? 'Nema kontakt osobe'), ENT_QUOTES, 'UTF-8') . '</div></div><a class="chip" href="/clients/edit?id=' . (int) $client['id'] . '">Otvori</a></div>';
        }
        if ($clients === []) {
            $content .= '<div class="mini-item"><div><strong>Nema klijenata</strong><div class="muted">Import podataka još nije učitan.</div></div></div>';
        }
        $content .= '</div></section>';

        $content .= '<section class="panel pad"><div class="section-title"><h2>Aktivnosti</h2><span class="muted">Ugovori i radni sati</span></div><div class="mini-list">';
        foreach (array_slice($contracts, 0, 4) as $contract) {
            $content .= '<div class="mini-item"><div><strong>' . htmlspecialchars((string) ($contract['contract_name'] ?? 'Ugovor'), ENT_QUOTES, 'UTF-8') . '</strong><div class="muted">' . htmlspecialchars((string) ($contract['client_name'] ?? ''), ENT_QUOTES, 'UTF-8') . ' · ' . htmlspecialchars($this->formatDate((string) ($contract['end_date'] ?? '')), ENT_QUOTES, 'UTF-8') . '</div></div><span class="chip gray">' . htmlspecialchars((string) ($contract['status'] ?? 'active'), ENT_QUOTES, 'UTF-8') . '</span></div>';
        }
        foreach (array_slice($workLogs, 0, 4) as $entry) {
            $content .= '<div class="mini-item"><div><strong>' . htmlspecialchars((string) ($entry['client_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong><div class="muted">' . htmlspecialchars($this->formatDate((string) ($entry['work_date'] ?? '')), ENT_QUOTES, 'UTF-8') . ' · ' . (int) ($entry['duration_minutes'] ?? 0) . ' min</div></div><span class="chip ' . (((int) ($entry['billed'] ?? 0) === 1) ? 'green' : 'gray') . '">' . (((int) ($entry['billed'] ?? 0) === 1) ? 'Naplaćeno' : 'Otvoreno') . '</span></div>';
        }
        if ($contracts === [] && $workLogs === []) {
            $content .= '<div class="mini-item"><div><strong>Nema aktivnosti</strong><div class="muted">Nema podataka za prikaz.</div></div></div>';
        }
        $content .= '</div></section>';
        $content .= '</div>';

        $this->renderPage(
            'Početna',
            'Prikaz glavnih brojki i najbitnijih zapisa iz sustava.',
            $content,
            'home'
        );
    }
}
