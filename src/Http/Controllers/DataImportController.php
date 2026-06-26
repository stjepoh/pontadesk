<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\ImportMap;
use App\Services\ImportService;

final class DataImportController extends AdminController
{
    public function index(): void
    {
        $this->requireAdmin();
        $map = ImportMap::load();

        ob_start();
        ?>
        <section class="panel pad">
            <div class="section-title">
                <h2>Import podataka</h2>
                <span class="muted">Učitaj Base44 JSON direktno u hosting bazu</span>
            </div>

            <form method="post" action="/data-import" enctype="multipart/form-data" class="content">
                <div class="grid-2">
                    <div>
                        <label>JSON export *</label>
                        <input class="input" type="file" name="export_file" accept=".json,application/json" required>
                    </div>
                    <div>
                        <label>Reset baze</label><br>
                        <label><input type="checkbox" name="reset" value="1"> Očisti postojeće podatke prije importa</label>
                    </div>
                </div>

                <div class="panel pad">
                    <div class="section-title">
                        <h2>Import mapa</h2>
                        <a class="btn secondary" href="/import-map">Uredi mapu</a>
                    </div>
                    <p class="muted" style="margin-top:0">Ako neki ugovori ili radovi nisu povezani, ovdje treba biti upisana mapa starih Base44 ID-jeva prema nazivima klijenata.</p>
                    <pre style="white-space:pre-wrap;background:#f7f9fc;border:1px solid #dbe3ee;border-radius:14px;padding:14px;margin:0"><?= htmlspecialchars((string) json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?></pre>
                </div>

                <div class="actions">
                    <button class="btn" type="submit">Pokreni import</button>
                    <a class="btn secondary" href="/">Nazad</a>
                </div>
            </form>
        </section>
        <?php
        $content = (string) ob_get_clean();
        $this->renderPage('Import podataka', 'Uvezi stvarne Base44 podatke u MySQL bazu.', $content, 'notes');
    }

    public function store(): void
    {
        $this->requireAdmin();

        if (!isset($_FILES['export_file']) || !is_array($_FILES['export_file']) || ($_FILES['export_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo 'Nije učitana JSON datoteka.';
            return;
        }

        $tmp = (string) ($_FILES['export_file']['tmp_name'] ?? '');
        $json = file_get_contents($tmp);
        if ($json === false) {
            http_response_code(400);
            echo 'Ne mogu pročitati upload.';
            return;
        }

        $export = json_decode($json, true);
        if (!is_array($export)) {
            http_response_code(400);
            echo 'Upload nije valjani JSON.';
            return;
        }

        $map = ImportMap::load();
        $reset = isset($_POST['reset']);

        try {
            $result = (new ImportService())->import($export, $map, $reset);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Import nije uspio: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            return;
        }

        ob_start();
        ?>
        <section class="panel pad">
            <div class="section-title">
                <h2>Import završen</h2>
            </div>
            <div class="mini-list">
                <div class="mini-item"><strong>Klijenti:</strong> <span><?= (int) ($result['clients'] ?? 0) ?></span></div>
                <div class="mini-item"><strong>Ugovori:</strong> <span><?= (int) ($result['contracts'] ?? 0) ?></span></div>
                <div class="mini-item"><strong>Radovi:</strong> <span><?= (int) ($result['work_logs'] ?? 0) ?></span></div>
                <div class="mini-item"><strong>Nepovezani ID-jevi:</strong> <span><?= htmlspecialchars(implode(', ', $result['missing_ids'] ?? []), ENT_QUOTES, 'UTF-8') ?: 'Nema' ?></span></div>
            </div>
            <div class="actions" style="margin-top:18px">
                <a class="btn" href="/clients">Otvori klijente</a>
                <a class="btn secondary" href="/work-logs">Otvori radove</a>
                <a class="btn secondary" href="/contracts">Otvori ugovore</a>
            </div>
        </section>
        <?php
        $content = (string) ob_get_clean();
        $this->renderPage('Import završen', 'Podaci su učitani u bazu.', $content, 'notes');
    }
}
