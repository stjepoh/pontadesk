<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\ExportStore;

final class ImportMapController extends AdminController
{
    private string $mapPath;

    public function __construct()
    {
        $this->mapPath = dirname(__DIR__, 3) . '/data/import-map.json';
    }

    public function index(): void
    {
        $this->requireAdmin();
        $map = $this->loadMap();
        $clients = ExportStore::data()['Client'] ?? [];

        $content = $this->indexContent($map, $clients);
        $this->renderPage('Import mapa', 'Poveži stare Base44 client_id vrijednosti s nazivima klijenata.', $content, 'notes');
    }

    public function store(): void
    {
        $this->requireAdmin();
        $oldIds = $_POST['old_id'] ?? [];
        $names = $_POST['client_name'] ?? [];

        $map = ['clients' => []];
        if (is_array($oldIds) && is_array($names)) {
            foreach ($oldIds as $index => $oldId) {
                $oldId = trim((string) $oldId);
                $name = trim((string) ($names[$index] ?? ''));
                if ($oldId === '' || $name === '') {
                    continue;
                }
                $map['clients'][$oldId] = $name;
            }
        }

        $dir = dirname($this->mapPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents(
            $this->mapPath,
            json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );

        header('Location: /import-map?saved=1');
        exit;
    }

    private function loadMap(): array
    {
        if (!is_file($this->mapPath)) {
            return ['clients' => []];
        }

        $json = file_get_contents($this->mapPath);
        if ($json === false) {
            return ['clients' => []];
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return ['clients' => []];
        }

        $data['clients'] = $data['clients'] ?? [];
        return $data;
    }

    private function indexContent(array $map, array $clients): string
    {
        $saved = isset($_GET['saved']);
        ob_start();
        ?>
        <section class="panel pad">
            <div class="section-title">
                <h2>Import mapa</h2>
                <a class="btn secondary" href="/work-logs">Provjeri radove</a>
            </div>
            <?php if ($saved): ?>
                <div class="chip green" style="margin-bottom:16px">Mapa je spremljena.</div>
            <?php endif; ?>
            <p class="muted" style="margin-top:0">Ovdje upisuješ stari Base44 `client_id` i biraš kojem klijentu pripada. To nam omogućuje da uvežemo ugovore i radove nakon importa.</p>

            <form method="post" action="/import-map" class="content">
                <div class="mini-list" id="map-list">
                    <?php foreach (($map['clients'] ?? []) as $oldId => $name): ?>
                        <?= $this->mapRow((string) $oldId, (string) $name, $clients) ?>
                    <?php endforeach; ?>
                    <?php if (($map['clients'] ?? []) === []): ?>
                        <?= $this->mapRow('', '', $clients) ?>
                    <?php endif; ?>
                </div>

                <div class="actions">
                    <button class="btn secondary" type="button" id="add-map-row">+ Dodaj mapiranje</button>
                    <button class="btn" type="submit">Spremi mapu</button>
                </div>
            </form>
        </section>

        <template id="map-row-template">
            <?= $this->mapRow('', '', $clients) ?>
        </template>

        <script>
        (function () {
            const list = document.getElementById('map-list');
            const template = document.getElementById('map-row-template');
            const addBtn = document.getElementById('add-map-row');

            function bindRemove(row) {
                const removeBtn = row.querySelector('[data-remove-map-row]');
                if (removeBtn) {
                    removeBtn.addEventListener('click', function () {
                        if (list.children.length > 1) {
                            row.remove();
                        }
                    });
                }
            }

            list.querySelectorAll('[data-map-row]').forEach(bindRemove);

            addBtn.addEventListener('click', function () {
                const node = template.content.cloneNode(true);
                const row = node.querySelector('[data-map-row]');
                list.appendChild(node);
                if (row) {
                    bindRemove(row);
                }
            });
        })();
        </script>
        <?php
        return (string) ob_get_clean();
    }

    private function mapRow(string $oldId, string $name, array $clients): string
    {
        ob_start();
        ?>
        <div class="mini-item" data-map-row>
            <div style="display:grid;grid-template-columns:1fr 1.2fr auto;gap:12px;width:100%;align-items:end">
                <div>
                    <label>Stari ID</label>
                    <input class="input" type="text" name="old_id[]" value="<?= htmlspecialchars($oldId, ENT_QUOTES, 'UTF-8') ?>" placeholder="69846da2...">
                </div>
                <div>
                    <label>Klijent</label>
                    <select class="input" name="client_name[]">
                        <option value="">Odaberi klijenta</option>
                        <?php foreach ($clients as $client): ?>
                            <?php $clientName = (string) ($client['name'] ?? ''); ?>
                            <option value="<?= htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8') ?>" <?= $clientName === $name ? 'selected' : '' ?>>
                                <?= htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button class="btn secondary" type="button" data-remove-map-row>Ukloni</button>
                </div>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
