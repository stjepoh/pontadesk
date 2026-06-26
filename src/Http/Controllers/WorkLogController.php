<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\ClientRepository;
use App\Repositories\WorkLogRepository;

final class WorkLogController extends AdminController
{
    public function index(): void
    {
        $this->requireAdmin();
        $rows = (new WorkLogRepository())->all();
        $this->renderPage('Radovi', 'Evidencija obavljenih radova.', $this->indexContent($rows), 'work');
    }

    public function create(): void
    {
        $this->requireAdmin();
        $clients = (new ClientRepository())->all();
        $selectedClientId = (int) ($_GET['client_id'] ?? 0);
        $selectedDate = (string) ($_GET['work_date'] ?? date('Y-m-d'));
        $this->renderPage('Novi rad', 'Dodavanje više zadataka za isti klijent i datum.', $this->formContent($clients, $selectedClientId, $selectedDate, null), 'work');
    }

    public function edit(): void
    {
        $this->requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);
        $row = (new WorkLogRepository())->find($id);
        if ($row === null) {
            http_response_code(404);
            echo 'Zapis nije pronađen';
            return;
        }

        $clients = (new ClientRepository())->all();
        $this->renderPage('Uredi rad', 'Ažuriranje postojećeg zapisa.', $this->formContent($clients, (int) ($row['client_id'] ?? 0), (string) ($row['work_date'] ?? ''), $row), 'work');
    }

    public function store(): void
    {
        $this->requireAdmin();
        $clientId = (int) ($_POST['client_id'] ?? 0);
        $workDate = (string) ($_POST['work_date'] ?? '');
        $billed = isset($_POST['billed']) ? 1 : 0;
        $durations = $_POST['duration_minutes'] ?? [];
        $descriptions = $_POST['description'] ?? [];

        if (!is_array($durations) || !is_array($descriptions)) {
            $this->redirect('/work-logs/create');
        }

        $repo = new WorkLogRepository();
        foreach ($durations as $index => $duration) {
            $description = trim((string) ($descriptions[$index] ?? ''));
            $durationMinutes = (int) $duration;

            if ($durationMinutes <= 0 && $description === '') {
                continue;
            }

            $repo->create([
                'client_id' => $clientId,
                'work_date' => $workDate,
                'duration_minutes' => max(1, $durationMinutes),
                'description' => $description !== '' ? $description : 'Radni zadatak',
                'billed' => $billed,
            ]);
        }

        $this->redirect('/work-logs');
    }

    public function update(): void
    {
        $this->requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);
        (new WorkLogRepository())->update($id, [
            'client_id' => (int) ($_POST['client_id'] ?? 0),
            'work_date' => (string) ($_POST['work_date'] ?? ''),
            'duration_minutes' => (int) ($_POST['duration_minutes'] ?? 0),
            'description' => (string) ($_POST['description'] ?? ''),
            'billed' => isset($_POST['billed']) ? 1 : 0,
        ]);
        $this->redirect('/work-logs');
    }

    public function delete(): void
    {
        $this->requireAdmin();
        (new WorkLogRepository())->delete((int) ($_GET['id'] ?? 0));
        $this->redirect('/work-logs');
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path);
        exit;
    }

    private function indexContent(array $rows): string
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = (string) ($row['client_name'] ?? $row['client_id'] ?? 'Nepoznato') . '|' . (string) ($row['work_date'] ?? '');
            $groups[$key]['client_name'] = (string) ($row['client_name'] ?? $row['client_id'] ?? 'Nepoznato');
            $groups[$key]['date'] = (string) ($row['work_date'] ?? '');
            $groups[$key]['items'][] = $row;
            $groups[$key]['total_minutes'] = ($groups[$key]['total_minutes'] ?? 0) + (int) ($row['duration_minutes'] ?? 0);
        }

        uasort($groups, static function (array $a, array $b): int {
            return strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''));
        });

        ob_start();
        ?>
        <section class="content">
            <?php if ($groups === []): ?>
                <div class="panel pad">
                    <div class="muted">Nema radnih sati.</div>
                </div>
            <?php else: ?>
                <?php foreach ($groups as $group): ?>
                    <?php
                    $dateValue = (string) ($group['date'] ?? '');
                    $dateLabel = $this->formatDate($dateValue);
                    $weekdayLabel = $this->weekdayLabel($dateValue);
                    $totalMinutes = (int) ($group['total_minutes'] ?? 0);
                    $totalHours = number_format($totalMinutes / 60, 1, ',', '.');
                    $clientId = (string) ($group['items'][0]['client_id'] ?? '');
                    ?>
                    <div class="section-title" style="margin-top:22px">
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                            <span style="font-size:18px">📅</span>
                            <h2 style="margin:0"><?= htmlspecialchars($weekdayLabel, ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8') ?></h2>
                        </div>
                    </div>
                    <section class="panel pad" style="margin-bottom:18px">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:18px;flex-wrap:wrap">
                            <div>
                                <span class="chip" style="background:#eaf1ff;color:#1f4ed8"><?= htmlspecialchars((string) $group['client_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="muted" style="margin-left:10px">🕒 <?= $totalMinutes ?> min (<?= $totalHours ?> h)</span>
                            </div>
                            <div class="actions">
                                <a class="chip" href="/work-logs/create?client_id=<?= urlencode($clientId) ?>&work_date=<?= urlencode($dateValue) ?>">+ Dodaj zadatak</a>
                                <a class="chip gray" href="/work-logs/create?client_id=<?= urlencode($clientId) ?>&work_date=<?= urlencode($dateValue) ?>">Uredi dan</a>
                            </div>
                        </div>

                        <div style="margin-top:16px">
                            <?php foreach ($group['items'] as $index => $item): ?>
                                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:18px;padding:12px 0;border-top:1px solid #eef2f7">
                                    <div style="display:flex;gap:10px;align-items:flex-start;flex:1;min-width:0">
                                        <div style="color:#8a98b0;font-weight:700;min-width:24px"><?= (int) $index + 1 ?>.</div>
                                        <div style="min-width:0;flex:1">
                                            <div style="font-weight:600;color:#14213d;line-height:1.5"><?= htmlspecialchars((string) ($item['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                        </div>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:12px;flex-shrink:0">
                                        <span class="muted"><?= (int) ($item['duration_minutes'] ?? 0) ?> min</span>
                                        <a class="chip gray" href="/work-logs/edit?id=<?= (int) $item['id'] ?>">Uredi</a>
                                        <a class="chip gray" href="/work-logs/delete?id=<?= (int) $item['id'] ?>" onclick="return confirm('Obrisati zapis?')">Briši</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function groupPaid(array $items): bool
    {
        foreach ($items as $item) {
            if ((int) ($item['billed'] ?? 0) === 1) {
                return true;
            }
        }
        return false;
    }

    private function weekdayLabel(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }

        return [
            1 => 'ponedjeljak',
            2 => 'utorak',
            3 => 'srijeda',
            4 => 'četvrtak',
            5 => 'petak',
            6 => 'subota',
            7 => 'nedjelja',
        ][(int) date('N', $timestamp)] ?? '';
    }

    private function formContent(array $clients, int $selectedClientId, string $selectedDate, ?array $row): string
    {
        $singleMode = $row !== null;
        ob_start();
        ?>
        <form method="post" action="<?= $singleMode ? '/work-logs/update?id=' . (int) $row['id'] : '/work-logs' ?>" class="content" id="work-form">
            <section class="panel pad">
                <div class="section-title">
                    <h2><?= $singleMode ? 'Uredi rad' : 'Novi rad' ?></h2>
                    <?php if (!$singleMode): ?><button class="btn secondary" type="button" id="add-task-btn">+ Dodaj zadatak</button><?php endif; ?>
                </div>

                <div class="grid-2">
                    <div>
                        <label>Klijent *</label>
                        <select class="input" name="client_id" required>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= (int) $client['id'] ?>" <?= ((int) $client['id'] === $selectedClientId) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $client['name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Datum *</label>
                        <input class="input" type="date" name="work_date" required value="<?= htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <?php if ($singleMode): ?>
                    <div class="grid-2" style="margin-top:18px">
                        <div>
                            <label>Trajanje (min) *</label>
                            <input class="input" type="number" min="1" name="duration_minutes" required value="<?= (int) ($row['duration_minutes'] ?? 0) ?>">
                        </div>
                        <div>
                            <label>Opis rada *</label>
                            <textarea class="input" name="description" rows="4" required><?= htmlspecialchars((string) ($row['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="margin-top:18px">
                        <div class="section-title">
                            <h2>Zadaci za dan *</h2>
                            <div class="muted">Možeš dodati više zasebnih zadataka za isti klijent i datum.</div>
                        </div>
                        <div id="task-list" class="content"></div>
                    </div>
                <?php endif; ?>

                <div style="margin-top:18px">
                    <label><input type="checkbox" name="billed" <?= $singleMode && !empty($row['billed']) ? 'checked' : '' ?>> Naplaćeno</label>
                </div>
            </section>

            <div class="actions">
                <a class="btn secondary" href="/work-logs">Odustani</a>
                <button class="btn" type="submit"><?= $singleMode ? 'Spremi' : 'Dodaj' ?></button>
            </div>
        </form>

        <?php if (!$singleMode): ?>
            <template id="task-template">
                <section class="panel pad task-card">
                    <div class="section-title">
                        <h2>Zadatak 1</h2>
                        <button class="btn secondary remove-task" type="button" style="color:#e74b4b;border-color:#f1c0c0">Ukloni</button>
                    </div>
                    <div class="grid-2">
                        <div>
                            <label>Trajanje (min) *</label>
                            <input class="input" type="number" min="1" name="duration_minutes[]" required value="20">
                        </div>
                        <div>
                            <label>Opis rada *</label>
                            <textarea class="input" name="description[]" rows="4" required placeholder="Opišite obavljeni rad..."></textarea>
                        </div>
                    </div>
                </section>
            </template>

            <script>
            (function () {
                const list = document.getElementById('task-list');
                const template = document.getElementById('task-template');
                const addBtn = document.getElementById('add-task-btn');

                function updateTitles() {
                    list.querySelectorAll('.task-card').forEach((card, index) => {
                        const title = card.querySelector('h2');
                        if (title) title.textContent = 'Zadatak ' + (index + 1);
                    });
                }

                function addTask() {
                    const node = template.content.cloneNode(true);
                    const section = node.querySelector('.task-card');
                    if (section) {
                        const removeBtn = section.querySelector('.remove-task');
                        if (removeBtn) {
                            removeBtn.addEventListener('click', function () {
                                if (list.children.length > 1) {
                                    section.remove();
                                    updateTitles();
                                }
                            });
                        }
                    }
                    list.appendChild(node);
                    updateTitles();
                }

                addBtn.addEventListener('click', addTask);
                addTask();
            })();
            </script>
        <?php endif; ?>
        <?php
        return (string) ob_get_clean();
    }
}
