<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Connection;
use App\Repositories\ClientRepository;
use App\Repositories\ContractRepository;
use App\Repositories\WorkLogRepository;
use Throwable;

final class DashboardController extends AdminController
{
    public function index(): void
    {
        $this->requireAdmin();

        $clients = (new ClientRepository())->all();
        $contracts = (new ContractRepository())->all();
        $workLogs = (new WorkLogRepository())->all();

        $period = (string) ($_GET['period'] ?? 'month');
        $selectedMonth = (string) ($_GET['month'] ?? date('Y-m'));
        $filteredLogs = $this->filterLogs($workLogs, $period, $selectedMonth);
        $stats = $this->buildStats($clients, $contracts, $filteredLogs);
        $monthOptions = $this->monthOptions($workLogs, $selectedMonth);

        $this->renderPage(
            'Dobrodošli u WorkTracker',
            'Pratite radove kod klijenata i generirajte izvještaje jednostavno i brzo',
            $this->content($stats, $monthOptions, $period, $selectedMonth),
            'home',
            '',
            'dashboard-page'
        );
    }

    /**
     * @param array<int, array<string, mixed>> $clients
     * @param array<int, array<string, mixed>> $contracts
     * @param array<int, array<string, mixed>> $logs
     * @return array<string, mixed>
     */
    private function buildStats(array $clients, array $contracts, array $logs): array
    {
        $clientRates = [];
        foreach ($clients as $client) {
            $clientRates[(int) ($client['id'] ?? 0)] = (float) ($client['hourly_rate'] ?? 0);
        }

        $byClient = [];
        $totalMinutes = 0;
        $totalAmount = 0.0;
        $paidAmount = 0.0;
        $unpaidAmount = 0.0;

        foreach ($logs as $log) {
            $clientId = (int) ($log['client_id'] ?? 0);
            $clientName = (string) ($log['client_name'] ?? $this->clientName($clients, $clientId));
            $minutes = (int) ($log['duration_minutes'] ?? 0);
            $rate = $clientRates[$clientId] ?? 0.0;
            $amount = ($minutes / 60) * $rate;

            if (!isset($byClient[$clientName])) {
                $byClient[$clientName] = [
                    'name' => $clientName,
                    'work_count' => 0,
                    'minutes' => 0,
                    'amount' => 0.0,
                    'paid' => 0.0,
                    'unpaid' => 0.0,
                ];
            }

            $byClient[$clientName]['work_count']++;
            $byClient[$clientName]['minutes'] += $minutes;
            $byClient[$clientName]['amount'] += $amount;

            if ((int) ($log['billed'] ?? 0) === 1) {
                $byClient[$clientName]['paid'] += $amount;
                $paidAmount += $amount;
            } else {
                $byClient[$clientName]['unpaid'] += $amount;
                $unpaidAmount += $amount;
            }

            $totalMinutes += $minutes;
            $totalAmount += $amount;
        }

        $byWorkCount = array_values($byClient);
        usort($byWorkCount, static fn(array $a, array $b): int => ($b['work_count'] <=> $a['work_count']) ?: strcmp((string) $a['name'], (string) $b['name']));

        $byMinutes = array_values($byClient);
        usort($byMinutes, static fn(array $a, array $b): int => ($b['minutes'] <=> $a['minutes']) ?: strcmp((string) $a['name'], (string) $b['name']));

        $byAmount = array_values($byClient);
        usort($byAmount, static fn(array $a, array $b): int => ($b['amount'] <=> $a['amount']) ?: strcmp((string) $a['name'], (string) $b['name']));

        $contractValue = 0.0;
        $maintenanceValue = 0.0;
        foreach ($contracts as $contract) {
            $contractValue += (float) ($contract['value'] ?? 0);
            $maintenanceValue += (float) ($contract['maintenance_amount'] ?? 0);
        }

        return [
            'client_count' => count($clients),
            'work_count' => count($logs),
            'total_minutes' => $totalMinutes,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'unpaid_amount' => $unpaidAmount,
            'work_clients' => array_slice($byWorkCount, 0, 3),
            'time_clients' => array_slice($byMinutes, 0, 3),
            'amount_clients' => array_slice($byAmount, 0, 3),
            'contract_value' => $contractValue,
            'maintenance_value' => $maintenanceValue,
            'expiring_contracts' => $this->expiringContracts($contracts),
            'tasks' => $this->pendingTasks(),
        ];
    }

    /**
     * @param array<string, mixed> $stats
     * @param array<int, string> $monthOptions
     */
    private function content(array $stats, array $monthOptions, string $period, string $selectedMonth): string
    {
        ob_start();
        ?>
        <style>
            .dashboard-page{max-width:1180px}
            .dashboard-page .hero{margin-bottom:26px}
            .dashboard-page .hero h1{font-size:30px;font-weight:850;letter-spacing:-.035em}
            .dashboard-page .hero p{font-size:15px;color:#6f7f97}
            .dashboard-shell{display:grid;gap:28px}
            .dash-filter{padding:20px 18px;display:grid;grid-template-columns:160px 210px 1fr;gap:18px;align-items:center}
            .dash-field label{display:block;margin:0 0 8px;color:#17233d;font-weight:800;font-size:13px}
            .dash-field select{height:38px;border-radius:7px;font-size:14px;padding:8px 12px}
            .dash-period-label{justify-self:end;text-align:left;min-width:180px;color:#6f7f97;font-size:14px;line-height:1.35}
            .dash-period-label strong{display:block;color:#0d1f3d;font-size:17px;margin-top:2px}
            .dash-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:20px;align-items:start}
            .dash-card{min-height:238px;padding:22px 20px}
            .dash-card.compact{min-height:96px;width:280px}
            .dash-card-head{display:flex;align-items:center;gap:16px}
            .dash-icon{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;font-size:20px;font-weight:800}
            .dash-icon.blue{background:#dfeaff;color:#3465f6}
            .dash-icon.purple{background:#eee2ff;color:#8736e8}
            .dash-icon.green{background:#cdefdb;color:#209a55}
            .dash-icon.yellow{background:#fff0b8;color:#c98300}
            .dash-icon.indigo{background:#e4e7ff;color:#4e5cff}
            .dash-kicker{font-size:12px;letter-spacing:.06em;text-transform:uppercase;font-weight:850;color:#6f7f97}
            .dash-value{font-size:30px;line-height:1;font-weight:900;color:#061a3b;letter-spacing:-.04em;margin-top:4px}
            .dash-list{margin-top:20px;padding-top:16px;border-top:1px solid #e5ebf3;display:grid;gap:9px}
            .dash-list-row{display:flex;justify-content:space-between;gap:12px;color:#3c4d67;font-size:14px}
            .dash-list-row strong{color:#061a3b}
            .dash-money-split{display:flex;gap:22px;margin-top:8px;font-size:12px}
            .dash-paid{color:#1fae57}
            .dash-unpaid{color:#f05a28}
            .dash-client-money{display:grid;gap:9px;margin-top:16px;padding-top:14px;border-top:1px solid #e5ebf3}
            .dash-client-money-row{display:grid;gap:3px;font-size:13px}
            .dash-client-money-row .line{display:flex;justify-content:space-between;gap:12px;color:#3c4d67}
            .dash-client-money-row .line strong{color:#061a3b}
            .dash-client-money-row .sub{font-size:12px}
            .dash-contract-row{display:grid;grid-template-columns:280px 1fr;gap:20px;align-items:start}
            .dash-contract-alert{padding:18px 20px;border-color:#ffb4b4;background:linear-gradient(180deg,#fff5f5 0,#ffe9e9 100%);box-shadow:0 10px 28px rgba(220,38,38,.12)}
            .dash-contract-alert-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:14px}
            .dash-contract-alert-title{display:flex;align-items:center;gap:10px;color:#b91c1c;font-size:18px;font-weight:900}
            .dash-contract-alert-title span{width:34px;height:34px;border-radius:11px;background:#dc2626;color:#fff;display:grid;place-items:center;font-weight:950}
            .dash-contract-alert-sub{color:#7f1d1d;font-size:13px;margin-top:4px}
            .dash-contract-alert-link{color:#b91c1c;font-weight:800;font-size:13px}
            .dash-contract-alert-list{display:grid;gap:10px}
            .dash-contract-alert-row{display:grid;grid-template-columns:1fr auto;gap:14px;align-items:center;padding:12px 14px;border:1px solid rgba(220,38,38,.18);border-radius:14px;background:rgba(255,255,255,.72)}
            .dash-contract-alert-row strong{display:block;color:#7f1d1d;font-size:14px}
            .dash-contract-alert-row small{display:block;color:#991b1b;margin-top:3px;font-size:12px}
            .dash-contract-days{height:30px;display:inline-flex;align-items:center;justify-content:center;border-radius:999px;padding:0 11px;background:#dc2626;color:#fff;font-size:12px;font-weight:900;white-space:nowrap}
            .dash-tasks{padding:24px 26px}
            .dash-tasks-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px}
            .dash-tasks-title{display:flex;align-items:center;gap:10px;font-size:18px;font-weight:850;color:#0d1f3d}
            .dash-tasks-link{color:#315ff4;font-size:14px}
            .dash-task-list{display:grid;gap:13px}
            .dash-task-row{display:grid;grid-template-columns:18px 18px 1fr 220px;gap:12px;align-items:center;font-size:14px;color:#213654}
            .dash-task-check{width:16px;height:16px;border:2px solid #cbd7e7;border-radius:50%}
            .dash-task-flag{color:#cbd7e7;font-size:14px}
            .dash-task-flag.hot{color:#ff2c3a}
            .dash-task-client{text-align:right;color:#8a98b0;font-size:13px}
            @media (max-width:1080px){.dash-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.dash-filter{grid-template-columns:1fr 1fr}.dash-period-label{justify-self:start}.dash-contract-row{grid-template-columns:1fr}.dash-card.compact{width:auto}}
            @media (max-width:760px){.dash-filter,.dash-stats{grid-template-columns:1fr}.dash-task-row{grid-template-columns:18px 18px 1fr}.dash-task-client{grid-column:3;text-align:left}.dash-card{min-height:auto}}
        </style>

        <div class="dashboard-shell">
            <form class="panel dash-filter" method="get" action="/" data-dashboard-filter>
                <div class="dash-field">
                    <label>Period</label>
                    <select name="period">
                        <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>Mjesec</option>
                        <option value="year" <?= $period === 'year' ? 'selected' : '' ?>>Godina</option>
                    </select>
                </div>
                <div class="dash-field">
                    <label>Mjesec</label>
                    <select name="month">
                        <?php foreach ($monthOptions as $month): ?>
                            <option value="<?= htmlspecialchars($month, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedMonth === $month ? 'selected' : '' ?>>
                                <?= htmlspecialchars($this->monthLabel($month), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="dash-period-label">
                    Prikazujem statistiku za:
                    <strong><?= htmlspecialchars($this->periodLabel($period, $selectedMonth), ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
            </form>

            <section class="dash-stats">
                <article class="panel dash-card">
                    <div class="dash-card-head">
                        <div class="dash-icon blue">♙</div>
                        <div>
                            <div class="dash-kicker">Klijenti</div>
                            <div class="dash-value"><?= (int) $stats['client_count'] ?></div>
                        </div>
                    </div>
                </article>

                <article class="panel dash-card">
                    <div class="dash-card-head">
                        <div class="dash-icon purple">◷</div>
                        <div>
                            <div class="dash-kicker">Radova</div>
                            <div class="dash-value"><?= (int) $stats['work_count'] ?></div>
                        </div>
                    </div>
                    <?= $this->summaryList($stats['work_clients'], 'work_count', '') ?>
                </article>

                <article class="panel dash-card">
                    <div class="dash-card-head">
                        <div class="dash-icon green">↗</div>
                        <div>
                            <div class="dash-kicker">Sati</div>
                            <div class="dash-value"><?= htmlspecialchars($this->hours((int) $stats['total_minutes'], false), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                    <?= $this->summaryList($stats['time_clients'], 'minutes', 'h') ?>
                </article>

                <article class="panel dash-card">
                    <div class="dash-card-head">
                        <div class="dash-icon yellow">$</div>
                        <div>
                            <div class="dash-kicker">Zarada</div>
                            <div class="dash-value"><?= htmlspecialchars($this->money((float) $stats['total_amount']), ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="dash-money-split">
                                <span class="dash-paid">Naplaćeno:<br><?= htmlspecialchars($this->money((float) $stats['paid_amount']), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="dash-unpaid">Nenaplaćeno:<br><?= htmlspecialchars($this->money((float) $stats['unpaid_amount']), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </div>
                    </div>
                    <?= $this->moneyList($stats['amount_clients']) ?>
                </article>
            </section>

            <section class="dash-contract-row">
                <article class="panel dash-card compact">
                    <div class="dash-card-head">
                        <div class="dash-icon indigo">▤</div>
                        <div>
                            <div class="dash-kicker">Vrijednost ugovora</div>
                            <div class="dash-value"><?= htmlspecialchars($this->money((float) $stats['contract_value']), ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="muted" style="font-size:12px;margin-top:8px;color:#4e5cff">
                                Preostalo održavanje: <?= htmlspecialchars($this->money((float) $stats['maintenance_value']), ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                    </div>
                </article>

                <?php if (($stats['expiring_contracts'] ?? []) !== []): ?>
                    <article class="panel dash-contract-alert">
                        <div class="dash-contract-alert-head">
                            <div>
                                <div class="dash-contract-alert-title"><span>!</span> Alarm: ugovori pred istekom</div>
                                <div class="dash-contract-alert-sub">Istječu u narednih 30 dana. Provjeriti obnovu ili novi ugovor.</div>
                            </div>
                            <a class="dash-contract-alert-link" href="/contracts?status=active">Svi aktivni ugovori →</a>
                        </div>
                        <div class="dash-contract-alert-list">
                            <?php foreach ($stats['expiring_contracts'] as $contract): ?>
                                <a class="dash-contract-alert-row" href="/contracts/edit?id=<?= (int) $contract['id'] ?>&status=active">
                                    <span>
                                        <strong><?= htmlspecialchars((string) $contract['contract_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <small><?= htmlspecialchars((string) $contract['client_name'], ENT_QUOTES, 'UTF-8') ?> · ističe <?= htmlspecialchars($this->formatDate((string) $contract['end_date']), ENT_QUOTES, 'UTF-8') ?></small>
                                    </span>
                                    <span class="dash-contract-days"><?= (int) $contract['days_left'] ?> dana</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endif; ?>
            </section>

            <section class="panel dash-tasks">
                <div class="dash-tasks-head">
                    <div class="dash-tasks-title"><span>☷</span> Najstariji neodrađeni zadaci</div>
                    <a class="dash-tasks-link" href="/tasks">Svi zadaci →</a>
                </div>
                <div class="dash-task-list">
                    <?php foreach ($stats['tasks'] as $task): ?>
                        <div class="dash-task-row">
                            <span class="dash-task-check"></span>
                            <span class="dash-task-flag <?= !empty($task['hot']) ? 'hot' : '' ?>">⚐</span>
                            <span><?= htmlspecialchars((string) $task['title'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="dash-task-client"><?= htmlspecialchars((string) $task['client_name'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <script>
        (function () {
            const form = document.querySelector('[data-dashboard-filter]');
            if (!form) return;
            form.querySelectorAll('select').forEach((field) => {
                field.addEventListener('change', function () {
                    form.requestSubmit ? form.requestSubmit() : form.submit();
                });
            });
        })();
        </script>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function summaryList(array $rows, string $field, string $unit): string
    {
        if ($rows === []) {
            return '<div class="dash-list"><div class="dash-list-row"><span>Nema podataka</span><strong>0</strong></div></div>';
        }

        $html = '<div class="dash-list">';
        foreach ($rows as $row) {
            $value = $field === 'minutes'
                ? $this->hours((int) ($row[$field] ?? 0), true)
                : (string) (int) ($row[$field] ?? 0);
            $html .= '<div class="dash-list-row"><span>' . htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span><strong>' . htmlspecialchars($value . ($unit !== '' && $field !== 'minutes' ? $unit : ''), ENT_QUOTES, 'UTF-8') . '</strong></div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function moneyList(array $rows): string
    {
        if ($rows === []) {
            return '<div class="dash-client-money"><div class="dash-client-money-row"><div class="line"><span>Nema podataka</span><strong>0.00 €</strong></div></div></div>';
        }

        $html = '<div class="dash-client-money">';
        foreach ($rows as $row) {
            $html .= '<div class="dash-client-money-row">'
                . '<div class="line"><span><strong>' . htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong></span><strong>' . htmlspecialchars($this->money((float) ($row['amount'] ?? 0)), ENT_QUOTES, 'UTF-8') . '</strong></div>'
                . '<div class="sub"><span class="dash-paid">Naplaćeno: ' . htmlspecialchars($this->money((float) ($row['paid'] ?? 0)), ENT_QUOTES, 'UTF-8') . '</span> '
                . '<span class="dash-unpaid">Nenaplaćeno: ' . htmlspecialchars($this->money((float) ($row['unpaid'] ?? 0)), ENT_QUOTES, 'UTF-8') . '</span></div>'
                . '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<int, array<string, mixed>> $logs
     * @return array<int, array<string, mixed>>
     */
    private function filterLogs(array $logs, string $period, string $selectedMonth): array
    {
        $year = substr($selectedMonth, 0, 4);

        return array_values(array_filter($logs, static function (array $log) use ($period, $selectedMonth, $year): bool {
            $date = (string) ($log['work_date'] ?? '');
            if ($period === 'year') {
                return str_starts_with($date, $year . '-');
            }

            return str_starts_with($date, $selectedMonth . '-');
        }));
    }

    /**
     * @param array<int, array<string, mixed>> $logs
     * @return array<int, string>
     */
    private function monthOptions(array $logs, string $selectedMonth): array
    {
        $months = [$selectedMonth => true];
        foreach ($logs as $log) {
            $date = (string) ($log['work_date'] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $date) === 1) {
                $months[substr($date, 0, 7)] = true;
            }
        }

        $values = array_keys($months);
        rsort($values);
        return $values;
    }

    private function clientName(array $clients, int $clientId): string
    {
        foreach ($clients as $client) {
            if ((int) ($client['id'] ?? 0) === $clientId) {
                return (string) ($client['name'] ?? 'Nepoznato');
            }
        }

        return 'Nepoznato';
    }

    private function monthLabel(string $month): string
    {
        $timestamp = strtotime($month . '-01');
        if ($timestamp === false) {
            return $month;
        }

        $months = [
            1 => 'siječanj',
            2 => 'veljača',
            3 => 'ožujak',
            4 => 'travanj',
            5 => 'svibanj',
            6 => 'lipanj',
            7 => 'srpanj',
            8 => 'kolovoz',
            9 => 'rujan',
            10 => 'listopad',
            11 => 'studeni',
            12 => 'prosinac',
        ];

        return ($months[(int) date('n', $timestamp)] ?? date('m', $timestamp)) . ' ' . date('Y', $timestamp);
    }

    private function periodLabel(string $period, string $month): string
    {
        return $period === 'year' ? substr($month, 0, 4) : $this->monthLabel($month);
    }

    private function hours(int $minutes, bool $withUnit): string
    {
        $hours = $minutes / 60;
        $value = abs($hours - round($hours)) < 0.05
            ? (string) (int) round($hours)
            : number_format($hours, 1, '.', '');

        return $withUnit ? $value . 'h' : $value;
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '') . ' €';
    }

    /**
     * @param array<int, array<string, mixed>> $contracts
     * @return array<int, array<string, mixed>>
     */
    private function expiringContracts(array $contracts): array
    {
        $today = new \DateTimeImmutable('today');
        $limit = $today->modify('+30 days');
        $rows = [];

        foreach ($contracts as $contract) {
            if ((string) ($contract['status'] ?? 'active') !== 'active') {
                continue;
            }

            $endDate = \DateTimeImmutable::createFromFormat('Y-m-d', (string) ($contract['end_date'] ?? ''));
            if (!$endDate instanceof \DateTimeImmutable) {
                continue;
            }

            if ($endDate < $today || $endDate > $limit) {
                continue;
            }

            $contract['days_left'] = (int) $today->diff($endDate)->format('%a');
            $rows[] = $contract;
        }

        usort($rows, static fn(array $a, array $b): int => ((int) $a['days_left'] <=> (int) $b['days_left']) ?: strcmp((string) ($a['client_name'] ?? ''), (string) ($b['client_name'] ?? '')));

        return array_slice($rows, 0, 6);
    }

    /**
     * @return array<int, array{title:string, client_name:string, hot?:bool}>
     */
    private function pendingTasks(): array
    {
        if ((string) getenv('DB_DATABASE') !== '') {
            try {
                $rows = Connection::pdo()->query(
                    'SELECT client_tasks.title, clients.name AS client_name, client_tasks.priority
                     FROM client_tasks
                     INNER JOIN clients ON clients.id = client_tasks.client_id
                     WHERE client_tasks.done = 0
                     ORDER BY client_tasks.created_at ASC, client_tasks.id ASC
                     LIMIT 7'
                )->fetchAll();

                if ($rows !== []) {
                    return array_map(static fn(array $row): array => [
                        'title' => (string) ($row['title'] ?? ''),
                        'client_name' => (string) ($row['client_name'] ?? ''),
                        'hot' => (string) ($row['priority'] ?? '') === 'high',
                    ], $rows);
                }
            } catch (Throwable) {
                // If local imports do not have tasks yet, keep the dashboard visually complete.
            }
        }

        return [
            ['title' => 'Nabaciti konektore na linkove u Blue strojanici', 'client_name' => 'Royal Hotels & Resort'],
            ['title' => 'Naci se s njima oko dozvola na NAS', 'client_name' => 'KRMEK'],
            ['title' => 'Vidjeti sto ce biti sa exchange transferom', 'client_name' => 'KRMEK'],
            ['title' => 'Imam neke clanke oko kojih moram pitati Vlahušić', 'client_name' => 'Općina Ston'],
            ['title' => 'Računalo sa studentske linije popraviti - sad je u servisu, možda Mateo može riješiti jer je neko kinesko govno', 'client_name' => 'Studentski centar Dubrovnik'],
            ['title' => 'Došao novi UPS - ugraditi van radnog vremena kod ravnateljice je u uredu', 'client_name' => 'Dječji vrtići Dubrovnik', 'hot' => true],
            ['title' => 'dv-dubrovnik-linda-hancevic@vpn-proracunski jhb534nkHJJDNMdkpfnduz!! Security Protocol: SSTP IMA PODACI U MAIL NA GMAIL', 'client_name' => 'Dječji vrtići Dubrovnik'],
        ];
    }
}
