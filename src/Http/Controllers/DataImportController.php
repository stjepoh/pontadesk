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
                <div class="panel pad" style="background:#f8fbff;border-style:dashed">
                    <div class="section-title">
                        <h2>Jedna datoteka</h2>
                        <span class="muted">Učitaj samo <strong>base44-bundle.json</strong></span>
                    </div>
                    <div style="max-width:560px">
                        <label>Bundle JSON *</label>
                        <input class="input" type="file" name="bundle_file" accept=".json,application/json" required>
                    </div>
                    <div style="margin-top:12px">
                        <label><input type="checkbox" name="reset" value="1"> Očisti postojeće podatke prije importa</label>
                    </div>
                </div>

                <div class="panel pad">
                    <div class="section-title">
                        <h2>Import mapa</h2>
                        <a class="btn secondary" href="/import-map">Uredi mapu</a>
                    </div>
                    <p class="muted" style="margin-top:0">Ovdje je automatski pripremljena mapa starih Base44 ID-jeva prema nazivima klijenata. To koristi import za povezivanje ugovora, radova, taskova i bilješki.</p>
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

        $map = ImportMap::load();
        $reset = isset($_POST['reset']);

        try {
            if (isset($_FILES['bundle_file']) && is_array($_FILES['bundle_file']) && ($_FILES['bundle_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $json = file_get_contents((string) $_FILES['bundle_file']['tmp_name']);
                if ($json === false) {
                    throw new \RuntimeException('Ne mogu pročitati bundle JSON.');
                }

                $bundle = json_decode($json, true);
                if (!is_array($bundle)) {
                    throw new \RuntimeException('Bundle JSON nije valjan.');
                }

                $result = (new ImportService())->importBundle($bundle, $map, $reset);
            } else {
            $bundled = [];
            foreach ([
                'Client' => 'client_file',
                'Contract' => 'contract_file',
                'WorkLog' => 'worklog_file',
                'Project' => 'project_file',
                'ClientNote' => 'clientnote_file',
                'ClientTask' => 'clienttask_file',
                'NotificationSettings' => 'notificationsettings_file',
            ] as $entity => $field) {
                if (!isset($_FILES[$field]) || !is_array($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    continue;
                }

                $json = file_get_contents((string) $_FILES[$field]['tmp_name']);
                if ($json === false) {
                    continue;
                }
                $data = json_decode($json, true);
                if (is_array($data)) {
                    $bundled[$entity] = $data;
                }
            }

            if (($bundled['Client'] ?? []) === []) {
                http_response_code(400);
                echo 'Nije učitan Client JSON.';
                return;
            }

            $result = (new ImportService())->importBundle($bundled, $map, $reset);
            }
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
