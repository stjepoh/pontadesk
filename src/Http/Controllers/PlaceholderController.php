<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\ClientRepository;
use App\Repositories\WorkLogRepository;

final class PlaceholderController extends AdminController
{
    public function reports(): void
    {
        $this->requireAdmin();

        $clients = (new ClientRepository())->all();
        $workLogs = (new WorkLogRepository())->all();

        $period = (string) ($_GET['period'] ?? 'monthly');
        $clientId = (int) ($_GET['client_id'] ?? 0);
        $range = (string) ($_GET['range'] ?? '');
        $billedOnly = isset($_GET['billed']) && (string) $_GET['billed'] !== '0';

        $data = $this->filterWorkLogs($workLogs, $clients, $period, $range, $clientId, $billedOnly);
        $content = $this->reportsContent($clients, $data, $period, $range, $clientId, $billedOnly);
        $this->renderPage('Izvještaji', 'Generirajte detaljne izvještaje o radu i projektima.', $content, 'reports');
    }

    public function reportsPdf(): void
    {
        $this->requireAdmin();

        $clients = (new ClientRepository())->all();
        $workLogs = (new WorkLogRepository())->all();

        $period = (string) ($_GET['period'] ?? 'monthly');
        $clientId = (int) ($_GET['client_id'] ?? 0);
        $range = (string) ($_GET['range'] ?? '');
        $billedOnly = isset($_GET['billed']) && (string) $_GET['billed'] !== '0';

        $data = $this->filterWorkLogs($workLogs, $clients, $period, $range, $clientId, $billedOnly);
        $title = $this->reportTitle($clients, $period, $range, $clientId);
        $pdf = $this->buildPdf($title, $data);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="izvjestaj.pdf"');
        echo $pdf;
        exit;
    }

    public function tasks(): void
    {
        $this->render('To-Do', 'tasks', 'U pripremi. Ovdje dolazi lista zadataka po klijentima.');
    }

    public function notes(): void
    {
        $this->render('Specifičnosti', 'notes', 'U pripremi. Ovdje dolaze bilješke i specifičnosti klijenata.');
    }

    public function notifications(): void
    {
        $this->render('Notif.', 'notifications', 'U pripremi. Ovdje dolaze notifikacije i korisničke postavke.');
    }

    private function render(string $title, string $active, string $message): void
    {
        $this->requireAdmin();
        $content = '<section class="panel pad"><div class="muted">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div></section>';
        $this->renderPage($title, 'Ova sekcija još se razvija.', $content, $active);
    }

    private function reportsContent(array $clients, array $data, string $period, string $range, int $clientId, bool $billedOnly): string
    {
        $selectedClient = $clientId > 0 ? $this->clientNameById($clients, $clientId) : 'Svi klijenti';
        $periodLabel = match ($period) {
            'weekly' => 'Tjedni',
            'yearly' => 'Godišnji',
            default => 'Mjesečni',
        };
        $rangeLabel = $this->rangeLabel($period, $range);
        $titleLabel = $this->reportTitle($clients, $period, $range, $clientId);
        $rows = $data['rows'];
        $totals = $data['totals'];
        $pdfUrl = '/reports/pdf?' . http_build_query([
            'period' => $period,
            'range' => $range,
            'client_id' => $clientId,
            'billed' => $billedOnly ? '1' : '0',
        ]);

        ob_start();
        ?>
        <style>
            .reports-shell{display:grid;gap:16px}
            .reports-tabs{display:inline-flex;gap:0;background:#f4f6fb;border:1px solid #e1e7f2;border-radius:12px;padding:3px}
            .reports-tab{display:inline-flex;align-items:center;justify-content:center;min-width:146px;height:32px;padding:0 18px;border-radius:9px;font-weight:700;color:#6c7a90}
            .reports-tab.active{background:#fff;color:#0f2444;box-shadow:0 2px 8px rgba(16,35,63,.08)}
            .reports-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap}
            .reports-kicker{display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:800;color:#5a82ff}
            .reports-kicker .dot{width:8px;height:8px;border-radius:50%;background:#5a82ff;display:inline-block}
            .reports-title{margin:8px 0 0;font-size:30px;line-height:1.08;letter-spacing:-.04em}
            .reports-sub{margin-top:6px;color:#6f7f97;font-size:14px}
            .reports-panel{padding:18px}
            .reports-filter-grid{display:grid;grid-template-columns:1fr 1fr 1.15fr auto auto auto;gap:12px;align-items:end}
            .reports-filter-grid label{display:block;margin:0 0 8px;font-size:13px;font-weight:700;color:#294164}
            .reports-btn{height:40px;padding:0 16px;border-radius:12px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1px solid #dce3ee;background:#fff;color:#0f2444}
            .reports-btn.primary{background:linear-gradient(180deg,#47b34f 0,#36a549 100%);border-color:#36a549;color:#fff}
            .reports-btn.active{border-color:#bfd2ff;background:#eef4ff;color:#1f4ed8}
            .reports-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
            .reports-stat{display:flex;gap:14px;align-items:center;padding:16px;border-radius:20px}
            .reports-stat .icon{width:44px;height:44px;border-radius:14px;display:grid;place-items:center;font-size:20px;background:#e9f0ff;color:#3d6ef3;flex:0 0 auto}
            .reports-stat .icon.purple{background:#f0e9ff;color:#a057ff}
            .reports-stat .icon.gold{background:#fff3cd;color:#d49100}
            .reports-stat .icon.green{background:rgba(255,255,255,.16);color:#fff}
            .reports-stat .meta{min-width:0}
            .reports-stat .label{font-size:13px;letter-spacing:0;color:#6f7f97}
            .reports-stat .value{margin-top:4px;font-size:25px;line-height:1.1;font-weight:800;letter-spacing:-.04em}
            .reports-stat .sub{margin-top:4px;font-size:12px;color:#6f7f97}
            .reports-stat.lead{background:linear-gradient(180deg,#43b64d 0,#37a647 100%);color:#fff}
            .reports-stat.lead .label,.reports-stat.lead .sub{color:rgba(255,255,255,.82)}
            .reports-table{overflow:hidden}
            .reports-table table thead th{font-size:12px;color:#6a778f;background:#fbfcfe}
            .reports-table table tbody td{font-size:14px}
            .reports-date-row td{background:#fcfdff;font-weight:800;color:#142647;padding-top:14px;padding-bottom:10px}
            .reports-date-row .summary{font-weight:600;color:#6f7f97;font-size:13px}
            .reports-total-row td{background:#f5f8fd;font-weight:800}
            .reports-empty{padding:24px;color:#6f7f97}
            .reports-amount{font-weight:800}
            @media (max-width: 1260px){.reports-filter-grid{grid-template-columns:1fr 1fr;}.reports-summary{grid-template-columns:repeat(2,minmax(0,1fr));}}
            @media (max-width: 760px){.reports-filter-grid,.reports-summary{grid-template-columns:1fr;}.reports-title{font-size:25px}.reports-tab{min-width:0;flex:1}}
            .reports-shell .panel{border-radius:18px}
            .reports-shell .reports-panel{padding:14px}
            .reports-shell .reports-summary{gap:10px}
            .reports-shell .reports-stat{padding:12px 14px}
            .reports-shell .reports-stat .value{font-size:21px}
            .reports-shell .reports-stat .icon{width:38px;height:38px;font-size:17px;border-radius:13px}
            .reports-shell .reports-table table thead th{padding:9px 10px}
            .reports-shell .reports-table table tbody td{padding:9px 10px}
            .reports-shell .reports-date-row td{padding-top:10px;padding-bottom:7px}
            .reports-shell .reports-date-row .summary{font-size:11px}
            .reports-shell .reports-tab{height:28px;min-width:136px;font-size:13px}
            .reports-shell .reports-btn{height:36px;padding:0 12px;font-size:13px}
        </style>
        <div class="reports-shell">
            <section class="reports-head">
                <div>
                    <div class="reports-kicker"><span class="dot"></span><span>Izvještaji</span></div>
                    <h2 class="reports-title"><?= htmlspecialchars($titleLabel, ENT_QUOTES, 'UTF-8') ?></h2>
                    <div class="reports-sub">Generirajte detaljne izvještaje o radu i projektima</div>
                </div>
                <a class="reports-btn primary" href="<?= htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8') ?>">↓ PDF</a>
            </section>

            <div class="reports-tabs" role="tablist" aria-label="Izvještaji">
                <span class="reports-tab active">Radovi po klijentu</span>
                <span class="reports-tab">Projekti i vrijeme</span>
            </div>

            <section class="panel reports-panel">
                <form method="get" action="/reports" id="reports-filter-form">
                    <div class="reports-filter-grid">
                        <div>
                            <label>Period</label>
                            <select class="input" name="period">
                                <option value="weekly" <?= $period === 'weekly' ? 'selected' : '' ?>>Tjedni</option>
                                <option value="monthly" <?= $period === 'monthly' ? 'selected' : '' ?>>Mjesečni</option>
                                <option value="yearly" <?= $period === 'yearly' ? 'selected' : '' ?>>Godišnji</option>
                            </select>
                        </div>
                        <div>
                            <label><?= $this->rangeLabelName($period) ?></label>
                            <?php if ($period === 'weekly'): ?>
                                <input class="input" type="week" name="range" value="<?= htmlspecialchars($range, ENT_QUOTES, 'UTF-8') ?>">
                            <?php elseif ($period === 'yearly'): ?>
                                <input class="input" type="number" min="2000" max="2100" name="range" value="<?= htmlspecialchars($range ?: (string) date('Y'), ENT_QUOTES, 'UTF-8') ?>">
                            <?php else: ?>
                                <input class="input" type="month" name="range" value="<?= htmlspecialchars($range ?: date('Y-m'), ENT_QUOTES, 'UTF-8') ?>">
                            <?php endif; ?>
                        </div>
                        <div>
                            <label>Klijent</label>
                            <select class="input" name="client_id">
                                <option value="0">Svi klijenti</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= (int) $client['id'] ?>" <?= $clientId === (int) $client['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string) $client['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="reports-btn<?= $billedOnly ? ' active' : '' ?>" type="submit" name="billed" value="1">✓ Naplaćeno</button>
                        <a class="reports-btn" href="/reports">Sve</a>
                        <a class="reports-btn primary" href="<?= htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8') ?>">↓ PDF</a>
                    </div>
                </form>
            </section>

            <section class="reports-summary">
                <div class="panel reports-stat">
                    <div class="icon">📅</div>
                    <div class="meta"><div class="label">Radova</div><div class="value"><?= (int) $totals['count'] ?></div></div>
                </div>
                <div class="panel reports-stat">
                    <div class="icon purple">⏱</div>
                    <div class="meta"><div class="label">Ukupno vrijeme</div><div class="value"><?= htmlspecialchars(number_format($totals['minutes'] / 60, 1, ',', '.'), ENT_QUOTES, 'UTF-8') ?> h</div></div>
                </div>
                <div class="panel reports-stat">
                    <div class="icon gold">🧮</div>
                    <div class="meta"><div class="label">Cijena/sat</div><div class="value"><?= htmlspecialchars(number_format((float) $totals['hourly_rate'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?> €</div></div>
                </div>
                <div class="panel reports-stat lead">
                    <div class="icon green">€</div>
                    <div class="meta"><div class="label">Ukupan iznos</div><div class="value"><?= htmlspecialchars(number_format($totals['amount'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?> €</div></div>
                </div>
            </section>

            <section class="panel reports-table">
                <table>
                    <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Opis</th>
                        <th>Sati</th>
                        <th>Iznos</th>
                        <th>Naplaćeno</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="5" class="reports-empty">Nema radova za odabrane kriterije.</td></tr>
                    <?php else: ?>
                        <?php
                        $dayTotals = [];
                        foreach ($rows as $row) {
                            $dateKey = (string) ($row['work_date'] ?? '');
                            $dayTotals[$dateKey]['minutes'] = ($dayTotals[$dateKey]['minutes'] ?? 0) + (int) ($row['duration_minutes'] ?? 0);
                            $dayTotals[$dateKey]['amount'] = ($dayTotals[$dateKey]['amount'] ?? 0) + (float) ($row['amount'] ?? 0);
                        }
                        $currentDate = null;
                        foreach ($rows as $row):
                            $dateKey = (string) ($row['work_date'] ?? '');
                            if ($currentDate !== $dateKey):
                                $currentDate = $dateKey;
                                $dateSummaryMinutes = (int) ($dayTotals[$dateKey]['minutes'] ?? 0);
                                $dateSummaryAmount = (float) ($dayTotals[$dateKey]['amount'] ?? 0);
                                ?>
                                <tr class="reports-date-row">
                                    <td colspan="5">
                                        <?= htmlspecialchars($this->formatDate($dateKey), ENT_QUOTES, 'UTF-8') ?>
                                        <span class="summary"> · <?= htmlspecialchars(number_format($dateSummaryMinutes / 60, 1, ',', '.'), ENT_QUOTES, 'UTF-8') ?> h · <?= htmlspecialchars(number_format($dateSummaryAmount, 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?> €</span>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <td></td>
                                <td><?= htmlspecialchars((string) ($row['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars(number_format(((int) ($row['duration_minutes'] ?? 0)) / 60, 1, ',', '.'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="reports-amount"><?= htmlspecialchars(number_format((float) ($row['amount'] ?? 0), 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?> €</td>
                                <td><span class="chip <?= ((int) ($row['billed'] ?? 0) === 1) ? 'green' : 'gray' ?>"><?= ((int) ($row['billed'] ?? 0) === 1) ? 'Da' : 'Ne' ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="reports-total-row">
                            <td>UKUPNO</td>
                            <td></td>
                            <td><?= htmlspecialchars(number_format($totals['minutes'] / 60, 1, ',', '.'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(number_format((float) $totals['amount'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?> €</td>
                            <td></td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </div>
        <?php
        $html = (string) ob_get_clean();
        $html .= <<<HTML
<script>
(function () {
    const form = document.getElementById('reports-filter-form');
    if (!form) return;

    const autoFields = form.querySelectorAll('select[name="period"], select[name="client_id"], input[name="range"]');
    autoFields.forEach(function (field) {
        field.addEventListener('change', function () {
            form.requestSubmit ? form.requestSubmit() : form.submit();
        });
    });
})();
</script>
HTML;
        return $html;
    }

    private function filterWorkLogs(array $rows, array $clients, string $period, string $range, int $clientId, bool $billedOnly): array
    {
        $rateByClient = [];
        foreach ($clients as $client) {
            $rateByClient[(int) ($client['id'] ?? 0)] = (float) ($client['hourly_rate'] ?? 0);
        }

        $rows = array_map(static function (array $row) use ($rateByClient): array {
            $row['hourly_rate'] = $rateByClient[(int) ($row['client_id'] ?? 0)] ?? 0;
            $minutes = (int) ($row['duration_minutes'] ?? 0);
            $rate = (float) ($row['hourly_rate'] ?? 0);
            $row['amount'] = round(($minutes / 60) * $rate, 2);
            return $row;
        }, $rows);

        $rows = array_values(array_filter($rows, function (array $row) use ($period, $range, $clientId, $billedOnly): bool {
            if ($clientId > 0 && (int) ($row['client_id'] ?? 0) !== $clientId) {
                return false;
            }
            if ($billedOnly && (int) ($row['billed'] ?? 0) !== 1) {
                return false;
            }
            $date = (string) ($row['work_date'] ?? '');
            if ($date === '') {
                return false;
            }
            return $this->matchesPeriod($date, $period, $range);
        }));

        usort($rows, static fn(array $a, array $b): int => strcmp((string) ($a['work_date'] ?? ''), (string) ($b['work_date'] ?? '')));

        $minutes = array_sum(array_map(static fn(array $row): int => (int) ($row['duration_minutes'] ?? 0), $rows));
        $amount = array_sum(array_map(static fn(array $row): float => (float) ($row['amount'] ?? 0), $rows));
        $hourlyRate = 0.0;
        if ($clientId > 0 && $rows !== []) {
            $hourlyRate = (float) ($rows[0]['hourly_rate'] ?? 0);
        }

        return [
            'rows' => $rows,
            'totals' => [
                'count' => count($rows),
                'minutes' => $minutes,
                'amount' => $amount,
                'hourly_rate' => $hourlyRate,
            ],
        ];
    }

    private function matchesPeriod(string $date, string $period, string $range): bool
    {
        if ($range === '') {
            return true;
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return false;
        }

        return match ($period) {
            'weekly' => $this->matchesWeek($timestamp, $range),
            'yearly' => date('Y', $timestamp) === $range,
            default => date('Y-m', $timestamp) === $range,
        };
    }

    private function matchesWeek(int $timestamp, string $range): bool
    {
        if (!preg_match('/^(\d{4})-W(\d{2})$/', $range, $matches)) {
            return false;
        }

        $year = (int) $matches[1];
        $week = (int) $matches[2];
        $start = (new \DateTimeImmutable())->setISODate($year, $week, 1)->setTime(0, 0, 0)->getTimestamp();
        $end = (new \DateTimeImmutable())->setISODate($year, $week, 7)->setTime(23, 59, 59)->getTimestamp();
        return $timestamp >= $start && $timestamp <= $end;
    }

    private function rangeLabelName(string $period): string
    {
        return match ($period) {
            'weekly' => 'Tjedan',
            'yearly' => 'Godina',
            default => 'Mjesec',
        };
    }

    private function rangeLabel(string $period, string $range): string
    {
        if ($range === '') {
            return 'Sve razdoblje';
        }

        return match ($period) {
            'weekly' => 'Tjedan ' . $range,
            'yearly' => $range . '.',
            default => $this->monthLabel($range),
        };
    }

    private function monthLabel(string $range): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $range, $m)) {
            return $range;
        }

        $months = [1 => 'siječanj', 2 => 'veljača', 3 => 'ožujak', 4 => 'travanj', 5 => 'svibanj', 6 => 'lipanj', 7 => 'srpanj', 8 => 'kolovoz', 9 => 'rujan', 10 => 'listopad', 11 => 'studeni', 12 => 'prosinac'];
        return ($months[(int) $m[2]] ?? $m[2]) . ' ' . $m[1];
    }

    private function reportTitle(array $clients, string $period, string $range, int $clientId): string
    {
        $client = $clientId > 0 ? $this->clientNameById($clients, $clientId) : 'Svi klijenti';
        return 'Izvještaj ' . $client . ' ' . $this->rangeLabel($period, $range);
    }

    private function clientNameById(array $clients, int $clientId): string
    {
        foreach ($clients as $client) {
            if ((int) ($client['id'] ?? 0) === $clientId) {
                return (string) ($client['name'] ?? 'Klijent');
            }
        }
        return 'Klijent';
    }

    private function buildPdf(string $title, array $data): string
    {
        $totals = $data['totals'];
        $rows = $data['rows'];
        $pages = [];
        $content = '';
        $pageNumber = 1;
        $y = $this->pdfStartPage($content, $title, $totals, $pageNumber);
        $lastDate = null;

        foreach ($rows as $row) {
            $date = (string) ($row['work_date'] ?? '');
            $description = trim((string) ($row['description'] ?? ''));
            $descriptionLines = $this->wrapPdfText($description, 88);
            $rowHeight = max(24, 11 + (count($descriptionLines) * 10));
            $needsDateHeader = $date !== $lastDate;
            $blockHeight = $rowHeight + ($needsDateHeader ? 22 : 0);

            if ($y - $blockHeight < 58) {
                $this->pdfFooter($content, $pageNumber);
                $pages[] = $content;
                $content = '';
                $pageNumber++;
                $y = $this->pdfStartPage($content, $title, $totals, $pageNumber);
                $lastDate = null;
                $needsDateHeader = true;
            }

            if ($needsDateHeader) {
                $this->pdfRect($content, 42, $y - 16, 511, 18, '0.97 0.98 1');
                $this->pdfTextAt($content, 48, $y - 4, 9, $this->formatDate($date), true, '0.08 0.15 0.28');
                $y -= 22;
                $lastDate = $date;
            }

            $this->pdfLine($content, 42, $y + 5, 553, $y + 5, '0.88 0.91 0.95');
            $lineY = $y - 5;
            foreach ($descriptionLines as $line) {
                $this->pdfTextAt($content, 119, $lineY, 8, $line, false, '0.08 0.15 0.28');
                $lineY -= 10;
            }

            $hours = number_format(((int) ($row['duration_minutes'] ?? 0)) / 60, 1, '.', '');
            $amount = number_format((float) ($row['amount'] ?? 0), 2, '.', '') . ' EUR';
            $billed = ((int) ($row['billed'] ?? 0) === 1) ? 'Da' : 'Ne';

            $this->pdfTextAt($content, 474, $y - 5, 8, $hours, false, '0.08 0.15 0.28');
            $this->pdfTextAt($content, 518, $y - 5, 8, $amount, true, '0.08 0.15 0.28');
            $this->pdfTextAt($content, 548, $y - 5, 7, $billed, false, '0.42 0.50 0.60');
            $y -= $rowHeight;
        }

        if ($rows === []) {
            $this->pdfTextAt($content, 48, $y - 10, 9, 'Nema radova za odabrane kriterije.', false, '0.42 0.50 0.60');
        }

        $this->pdfLine($content, 42, $y, 553, $y, '0.75 0.80 0.88');
        $this->pdfTextAt($content, 48, $y - 17, 9, 'UKUPNO', true, '0.08 0.15 0.28');
        $this->pdfTextAt($content, 474, $y - 17, 9, number_format(((int) $totals['minutes']) / 60, 1, '.', ''), true, '0.08 0.15 0.28');
        $this->pdfTextAt($content, 518, $y - 17, 9, number_format((float) $totals['amount'], 2, '.', '') . ' EUR', true, '0.08 0.15 0.28');

        $this->pdfFooter($content, $pageNumber);
        $pages[] = $content;

        return $this->makeSimplePdf($pages);
    }

    private function pdfText(string $value): string
    {
        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT', $value);
        return $converted !== false ? $converted : $value;
    }

    private function pdfStartPage(string &$content, string $title, array $totals, int $pageNumber): int
    {
        $this->pdfRect($content, 0, 790, 595, 52, '0.05 0.09 0.17');
        $this->pdfTextAt($content, 42, 813, 16, 'PontaDesk', true, '1 1 1');
        $this->pdfTextAt($content, 42, 778, 17, $title, true, '0.08 0.15 0.28');
        $this->pdfTextAt($content, 42, 760, 9, 'Izvjestaj radova po odabranim kriterijima', false, '0.42 0.50 0.60');

        $this->pdfStatBox($content, 42, 704, 'Radova', (string) (int) $totals['count']);
        $this->pdfStatBox($content, 170, 704, 'Ukupno vrijeme', number_format(((int) $totals['minutes']) / 60, 1, '.', '') . ' h');
        $this->pdfStatBox($content, 298, 704, 'Cijena/sat', number_format((float) $totals['hourly_rate'], 2, '.', '') . ' EUR');
        $this->pdfStatBox($content, 426, 704, 'Ukupan iznos', number_format((float) $totals['amount'], 2, '.', '') . ' EUR', true);

        $this->pdfRect($content, 42, 668, 511, 24, '0.97 0.98 1');
        $this->pdfTextAt($content, 48, 676, 8, 'Datum', true, '0.42 0.50 0.60');
        $this->pdfTextAt($content, 119, 676, 8, 'Opis', true, '0.42 0.50 0.60');
        $this->pdfTextAt($content, 474, 676, 8, 'Sati', true, '0.42 0.50 0.60');
        $this->pdfTextAt($content, 518, 676, 8, 'Iznos', true, '0.42 0.50 0.60');

        if ($pageNumber > 1) {
            $this->pdfTextAt($content, 510, 778, 8, 'Stranica ' . $pageNumber, false, '0.42 0.50 0.60');
        }

        return 654;
    }

    private function pdfStatBox(string &$content, int $x, int $y, string $label, string $value, bool $green = false): void
    {
        $fill = $green ? '0.22 0.66 0.28' : '1 1 1';
        $labelColor = $green ? '0.88 1 0.90' : '0.42 0.50 0.60';
        $valueColor = $green ? '1 1 1' : '0.08 0.15 0.28';
        $this->pdfRect($content, $x, $y, 116, 52, $fill);
        $this->pdfStrokeRect($content, $x, $y, 116, 52, $green ? '0.22 0.66 0.28' : '0.86 0.90 0.95');
        $this->pdfTextAt($content, $x + 12, $y + 31, 8, $label, false, $labelColor);
        $this->pdfTextAt($content, $x + 12, $y + 13, 13, $value, true, $valueColor);
    }

    private function pdfFooter(string &$content, int $pageNumber): void
    {
        $this->pdfLine($content, 42, 38, 553, 38, '0.88 0.91 0.95');
        $this->pdfTextAt($content, 42, 24, 7, 'PontaDesk', false, '0.42 0.50 0.60');
        $this->pdfTextAt($content, 512, 24, 7, 'Stranica ' . $pageNumber, false, '0.42 0.50 0.60');
    }

    private function wrapPdfText(string $text, int $maxChars): array
    {
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';
        if ($text === '') {
            return [''];
        }

        $words = explode(' ', $text);
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if (strlen($candidate) > $maxChars && $line !== '') {
                $lines[] = $line;
                $line = $word;
                continue;
            }
            $line = $candidate;
        }
        $lines[] = $line;

        return array_slice($lines, 0, 8);
    }

    private function pdfTextAt(string &$content, int $x, int $y, int $size, string $text, bool $bold = false, string $color = '0 0 0'): void
    {
        $font = $bold ? 'F2' : 'F1';
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->pdfText($text));
        $content .= $color . " rg BT /{$font} {$size} Tf {$x} {$y} Td ({$escaped}) Tj ET\n";
    }

    private function pdfRect(string &$content, int $x, int $y, int $w, int $h, string $fill): void
    {
        $content .= "{$fill} rg {$x} {$y} {$w} {$h} re f\n";
    }

    private function pdfStrokeRect(string &$content, int $x, int $y, int $w, int $h, string $color): void
    {
        $content .= "{$color} RG 0.8 w {$x} {$y} {$w} {$h} re S\n";
    }

    private function pdfLine(string &$content, int $x1, int $y1, int $x2, int $y2, string $color): void
    {
        $content .= "{$color} RG 0.6 w {$x1} {$y1} m {$x2} {$y2} l S\n";
    }

    private function makeSimplePdf(array $pageContents): string
    {
        $pageCount = count($pageContents);
        $fontRegularObject = 3 + ($pageCount * 2);
        $fontBoldObject = $fontRegularObject + 1;

        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        foreach ($pageContents as $index => $pageContent) {
            $kids[] = (3 + ($index * 2)) . ' 0 R';
        }
        $objects[] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . ' >>';

        foreach ($pageContents as $index => $pageContent) {
            $pageObject = 3 + ($index * 2);
            $contentObject = $pageObject + 1;
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents ' . $contentObject . ' 0 R /Resources << /Font << /F1 ' . $fontRegularObject . ' 0 R /F2 ' . $fontBoldObject . ' 0 R >> >> >>';
            $objects[] = '<< /Length ' . strlen($pageContent) . ' >> stream' . "\n" . $pageContent . "\n" . 'endstream';
        }

        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

        return $pdf;
    }
}
