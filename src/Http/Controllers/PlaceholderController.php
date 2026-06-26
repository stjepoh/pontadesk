<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\ClientRepository;
use App\Repositories\ContractRepository;
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
        $title = $this->reportPdfTitle($clients, $period, $range, $clientId);
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
        $this->render('Specifič.', 'notes', 'U pripremi. Ovdje dolaze bilješke i specifičnosti klijenata.');
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
        <section class="panel pad" style="margin-bottom:18px">
            <div class="section-title">
                <h2>Radovi po klijentu</h2>
                <a class="btn secondary" href="<?= htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8') ?>">PDF</a>
            </div>
            <form method="get" action="/reports" class="grid-4" style="align-items:end">
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
                <div class="actions">
                    <button class="btn secondary" type="submit" name="billed" value="1"><?= $billedOnly ? 'Naplaćeno' : 'Sve' ?></button>
                    <a class="btn secondary" href="/reports">Poništi</a>
                </div>
            </form>
            <div class="muted" style="margin-top:12px">Prikaz: <?= htmlspecialchars($periodLabel . ' · ' . $rangeLabel . ' · ' . $selectedClient, ENT_QUOTES, 'UTF-8') ?></div>
        </section>

        <section class="grid-4">
            <div class="panel stat"><div class="label">Radova</div><div class="value"><?= (int) $totals['count'] ?></div><div class="sub">Broj zapisa</div></div>
            <div class="panel stat"><div class="label">Ukupno vrijeme</div><div class="value"><?= htmlspecialchars(number_format($totals['minutes'] / 60, 1, ',', '.'), ENT_QUOTES, 'UTF-8') ?> h</div><div class="sub"><?= (int) $totals['minutes'] ?> min</div></div>
            <div class="panel stat"><div class="label">Cijena/sat</div><div class="value"><?= htmlspecialchars(number_format((float) $totals['hourly_rate'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?> €</div><div class="sub">Za odabrani klijent</div></div>
            <div class="panel stat" style="background:linear-gradient(180deg,#39b54a 0,#2f9d42 100%);color:#fff"><div class="label" style="color:rgba(255,255,255,.8)">Ukupan iznos</div><div class="value"><?= htmlspecialchars(number_format($totals['amount'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?> €</div><div class="sub" style="color:rgba(255,255,255,.86)">Naplaćeno / obračun</div></div>
        </section>

        <section class="panel" style="margin-top:18px">
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
                    <tr><td colspan="5" class="muted">Nema radova za odabrane kriterije.</td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($this->formatDate((string) ($row['work_date'] ?? '')), ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= htmlspecialchars((string) ($row['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(number_format(((int) ($row['duration_minutes'] ?? 0)) / 60, 1, ',', '.'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td style="font-weight:700"><?= htmlspecialchars(number_format((float) ($row['amount'] ?? 0), 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?> €</td>
                        <td><span class="chip <?= ((int) ($row['billed'] ?? 0) === 1) ? 'green' : 'gray' ?>"><?= ((int) ($row['billed'] ?? 0) === 1) ? 'Da' : 'Ne' ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function filterWorkLogs(array $rows, array $clients, string $period, string $range, int $clientId, bool $billedOnly): array
    {
        $rateByClient = [];
        foreach ($clients as $client) {
            $rateByClient[(int) ($client['id'] ?? 0)] = (float) ($client['hourly_rate'] ?? 0);
        }

        $rows = array_map(function (array $row): array {
            $minutes = (int) ($row['duration_minutes'] ?? 0);
            $rate = (float) ($row['hourly_rate'] ?? 0);
            $row['amount'] = round(($minutes / 60) * $rate, 2);
            return $row;
        }, array_map(static function (array $row) use ($rateByClient): array {
            $row['hourly_rate'] = $rateByClient[(int) ($row['client_id'] ?? 0)] ?? 0;
            return $row;
        }, $rows));

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
        if (!preg_match('/^(\\d{4})-W(\\d{2})$/', $range, $matches)) {
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
        if (!preg_match('/^(\\d{4})-(\\d{2})$/', $range, $m)) {
            return $range;
        }

        $months = [1 => 'siječanj', 2 => 'veljača', 3 => 'ožujak', 4 => 'travanj', 5 => 'svibanj', 6 => 'lipanj', 7 => 'srpanj', 8 => 'kolovoz', 9 => 'rujan', 10 => 'listopad', 11 => 'studeni', 12 => 'prosinac'];
        return ($months[(int) $m[2]] ?? $m[2]) . ' ' . $m[1];
    }

    private function reportPdfTitle(array $clients, string $period, string $range, int $clientId): string
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
        $lines = [];
        $lines[] = $this->pdfText($title);
        $lines[] = $this->pdfText('Ukupno radova: ' . (int) $data['totals']['count']);
        $lines[] = $this->pdfText('Ukupno minuta: ' . (int) $data['totals']['minutes']);
        $lines[] = $this->pdfText('Ukupan iznos: ' . number_format((float) $data['totals']['amount'], 2, '.', '') . ' EUR');
        $lines[] = '';
        foreach ($data['rows'] as $row) {
            $lines[] = $this->pdfText($this->formatDate((string) ($row['work_date'] ?? '')) . ' | ' . (string) ($row['description'] ?? '') . ' | ' . number_format(((int) ($row['duration_minutes'] ?? 0)) / 60, 1, '.', '') . ' h | ' . number_format((float) ($row['amount'] ?? 0), 2, '.', '') . ' EUR | ' . (((int) ($row['billed'] ?? 0) === 1) ? 'Da' : 'Ne'));
        }

        return $this->makeSimplePdf($lines);
    }

    private function pdfText(string $value): string
    {
        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT', $value);
        return $converted !== false ? $converted : $value;
    }

    private function makeSimplePdf(array $lines): string
    {
        $content = "BT /F1 12 Tf 50 790 Td ";
        $first = true;
        foreach ($lines as $line) {
            if ($line === '') {
                $content .= "T* ";
                continue;
            }
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            if (!$first) {
                $content .= "T* ";
            }
            $content .= '(' . $escaped . ') Tj ';
            $first = false;
        }
        $content .= "ET";

        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>';
        $objects[] = '<< /Length ' . strlen($content) . ' >> stream' . "\n" . $content . "\n" . 'endstream';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

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
