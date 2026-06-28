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
        $clients = (new ClientRepository())->all();
        $selectedClientId = (int) ($_GET['client_id'] ?? 0);
        $showBilled = isset($_GET['billed']) && (string) $_GET['billed'] !== '0';
        $actions = '<a class="btn" href="/work-logs/create">+ Novi rad</a>';

        $this->renderPage(
            'Radovi',
            'Evidencija obavljenih radova.',
            $this->indexContent($rows, $clients, $selectedClientId, $showBilled),
            'work',
            $actions,
            'work-page'
        );
    }

    public function create(): void
    {
        $this->requireAdmin();

        $clients = (new ClientRepository())->all();
        $selectedClientId = (int) ($_GET['client_id'] ?? 0);
        if ($selectedClientId <= 0) {
            $selectedClientId = $this->preferredClientId($clients);
        }
        $selectedDate = $this->parseDateInput((string) ($_GET['work_date'] ?? date('Y-m-d')));
        $existingRows = $this->existingRowsForDay((new WorkLogRepository())->all(), $selectedClientId, $selectedDate);

        $this->renderPage(
            'Novi rad',
            'Dodavanje više zadataka za isti klijent i datum.',
            $this->formContent($clients, $selectedClientId, $selectedDate, null, $existingRows),
            'work',
            '',
            'work-page'
        );
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
        $this->renderPage(
            'Uredi rad',
            'Ažuriranje postojećeg zapisa.',
            $this->formContent($clients, (int) ($row['client_id'] ?? 0), $this->formatDate((string) ($row['work_date'] ?? '')), $row),
            'work',
            '',
            'work-page'
        );
    }

    public function store(): void
    {
        $this->requireAdmin();

        $clientId = (int) ($_POST['client_id'] ?? 0);
        $workDate = $this->parseDateInput((string) ($_POST['work_date'] ?? ''));
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
            'work_date' => $this->parseDateInput((string) ($_POST['work_date'] ?? '')),
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

    public function deleteDay(): void
    {
        $this->requireAdmin();

        $clientId = (int) ($_GET['client_id'] ?? 0);
        $workDate = $this->parseDateInput((string) ($_GET['work_date'] ?? ''));
        $repo = new WorkLogRepository();

        foreach ($repo->all() as $row) {
            if ((int) ($row['client_id'] ?? 0) === $clientId && (string) ($row['work_date'] ?? '') === $workDate) {
                $repo->delete((int) ($row['id'] ?? 0));
            }
        }

        $this->redirect('/work-logs');
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path);
        exit;
    }

    private function indexContent(array $rows, array $clients, int $selectedClientId, bool $showBilled): string
    {
        if ($selectedClientId > 0) {
            $rows = array_values(array_filter($rows, static fn(array $row): bool => (int) ($row['client_id'] ?? 0) === $selectedClientId));
        }

        if ($showBilled) {
            $rows = array_values(array_filter($rows, static fn(array $row): bool => (int) ($row['billed'] ?? 0) === 1));
        }

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
        <style>
            .work-page{max-width:820px}
            .work-filters{display:flex;gap:12px;align-items:end;margin-bottom:22px;flex-wrap:wrap}
            .work-filters select{width:220px}
            .work-filter-toggle{height:40px;display:inline-flex;align-items:center;gap:8px;border:1px solid #d8e0ec;border-radius:9px;padding:0 16px;background:#fff;color:#14213d;font-weight:800;box-shadow:0 2px 8px rgba(15,23,42,.04);cursor:pointer}
            .work-filter-toggle input{width:16px;height:16px;accent-color:#3f6df6}
            .work-day{margin-top:18px}
            .work-day-title{display:flex;align-items:center;gap:8px;margin:0 0 10px;color:#162946;font-weight:800;font-size:15px}
            .work-day-title .cal{color:#3f6df6;font-size:15px}
            .work-card{padding:14px 16px}
            .work-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap}
            .work-card-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
            .work-card-actions{display:flex;align-items:center;gap:8px}
            .work-icon-link{display:inline-grid;place-items:center;width:28px;height:28px;border-radius:8px;color:#6f7f97;font-weight:800}
            .work-icon-link:hover{background:#f2f6ff;color:#1f4ed8}
            .work-icon-link.danger{color:#e74b4b}
            .work-task{display:grid;grid-template-columns:26px 1fr 72px;gap:8px;align-items:start;padding:7px 0;font-size:13px}
            .work-task + .work-task{border-top:1px solid #eef2f7}
            .work-task-number{color:#8795ad;font-weight:700}
            .work-task-desc{line-height:1.45;color:#14213d}
            .work-task-duration{color:#8a98b0;font-weight:700;text-align:right;white-space:nowrap}
            @media (max-width:760px){.work-page{max-width:none}.work-filters select,.work-filters .btn,.work-filter-toggle{width:100%}.work-task{grid-template-columns:24px 1fr}.work-task-duration{grid-column:2;text-align:left}}
        </style>

        <form method="get" action="/work-logs" class="work-filters" data-auto-filter>
            <div>
                <label>Svi klijenti</label>
                <select class="input" name="client_id">
                    <option value="0">Svi klijenti</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= (int) $client['id'] ?>" <?= $selectedClientId === (int) $client['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $client['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <label class="work-filter-toggle">
                <input type="checkbox" name="billed" value="1" <?= $showBilled ? 'checked' : '' ?>>
                Prikaži naplaćene
            </label>
            <?php if ($selectedClientId > 0 || $showBilled): ?>
                <a class="btn secondary" href="/work-logs">Poništi</a>
            <?php endif; ?>
        </form>

        <script>
        (function () {
            const form = document.querySelector('[data-auto-filter]');
            if (!form) return;

            form.querySelectorAll('select, input[type="checkbox"]').forEach((field) => {
                field.addEventListener('change', function () {
                    if (form.requestSubmit) {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                });
            });
        })();
        </script>

        <section>
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
                    $taskCount = count($group['items'] ?? []);
                    $clientId = (string) ($group['items'][0]['client_id'] ?? '');
                    ?>
                    <div class="work-day">
                        <div class="work-day-title">
                            <span class="cal">📅</span>
                            <span><?= htmlspecialchars($weekdayLabel, ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <section class="panel work-card">
                            <div class="work-card-head">
                                <div class="work-card-meta">
                                    <span class="chip" style="background:#eaf1ff;color:#1f4ed8"><?= htmlspecialchars((string) $group['client_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="muted">🕒 <?= $totalMinutes ?> min (<?= $totalHours ?> h)</span>
                                    <span class="chip gray"><?= $taskCount ?> zadataka</span>
                                </div>
                                <div class="work-card-actions">
                                    <a class="work-icon-link" title="Dodaj zadatak" href="/work-logs/create?client_id=<?= urlencode($clientId) ?>&work_date=<?= urlencode($dateValue) ?>">+</a>
                                    <a class="work-icon-link" title="Uredi dan" href="/work-logs/create?client_id=<?= urlencode($clientId) ?>&work_date=<?= urlencode($dateValue) ?>">✎</a>
                                    <a class="work-icon-link danger" title="Obriši dan" href="/work-logs/delete-day?client_id=<?= urlencode($clientId) ?>&work_date=<?= urlencode($dateValue) ?>" onclick="return confirm('Obrisati sve radove za ovaj dan i klijenta?')">🗑</a>
                                </div>
                            </div>

                            <div style="margin-top:10px">
                                <?php foreach ($group['items'] as $index => $item): ?>
                                    <div class="work-task">
                                        <div class="work-task-number"><?= (int) $index + 1 ?>.</div>
                                        <div class="work-task-desc">
                                            <?= htmlspecialchars((string) ($item['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ((int) ($item['billed'] ?? 0) === 1): ?>
                                                <span class="chip green" style="margin-left:8px">Naplaćeno</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="work-task-duration"><?= (int) ($item['duration_minutes'] ?? 0) ?> min</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function existingRowsForDay(array $rows, int $clientId, string $workDate): array
    {
        if ($clientId <= 0 || $workDate === '') {
            return [];
        }

        $matches = array_values(array_filter($rows, static function (array $row) use ($clientId, $workDate): bool {
            return (int) ($row['client_id'] ?? 0) === $clientId && (string) ($row['work_date'] ?? '') === $workDate;
        }));

        usort($matches, static fn(array $a, array $b): int => (int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0));
        return $matches;
    }

    private function preferredClientId(array $clients): int
    {
        foreach ($clients as $client) {
            if (strcasecmp(trim((string) ($client['name'] ?? '')), 'Royal Hotels & Resort') === 0) {
                return (int) ($client['id'] ?? 0);
            }
        }

        return (int) ($clients[0]['id'] ?? 0);
    }

    private function parseDateInput(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        foreach (['d/m/Y', 'Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof \DateTimeImmutable) {
                return $date->format('Y-m-d');
            }
        }

        $timestamp = strtotime($value);
        return $timestamp !== false ? date('Y-m-d', $timestamp) : $value;
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

    private function formContent(array $clients, int $selectedClientId, string $selectedDate, ?array $row, array $existingRows = []): string
    {
        $singleMode = $row !== null;
        $existingMinutes = array_sum(array_map(static fn(array $item): int => (int) ($item['duration_minutes'] ?? 0), $existingRows));
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
                        <?= $this->dateField('work_date', $selectedDate, true) ?>
                    </div>
                </div>

                <?php if (!$singleMode && $existingRows !== []): ?>
                    <section class="panel pad" style="margin-top:18px;background:#fbfdff">
                        <div class="section-title">
                            <h2>Već upisani zadaci</h2>
                            <div class="muted"><?= count($existingRows) ?> zadataka, <?= $existingMinutes ?> min</div>
                        </div>
                        <div class="mini-list">
                            <?php foreach ($existingRows as $index => $item): ?>
                                <div class="mini-item" style="align-items:flex-start">
                                    <div style="display:flex;gap:10px;min-width:0">
                                        <span class="muted" style="font-weight:800"><?= (int) $index + 1 ?>.</span>
                                        <div style="min-width:0">
                                            <div style="font-weight:700;color:#14213d;line-height:1.45"><?= htmlspecialchars((string) ($item['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="muted" style="margin-top:4px"><?= (int) ($item['duration_minutes'] ?? 0) ?> min<?= ((int) ($item['billed'] ?? 0) === 1) ? ' · Naplaćeno' : '' ?></div>
                                        </div>
                                    </div>
                                    <a class="chip gray" href="/work-logs/edit?id=<?= (int) ($item['id'] ?? 0) ?>">Uredi</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

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
                            <h2>Novi zadaci za dan *</h2>
                            <div class="muted">Dodaje se na isti klijent i datum.</div>
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
                const form = document.getElementById('work-form');
                const list = document.getElementById('task-list');
                const template = document.getElementById('task-template');
                const addBtn = document.getElementById('add-task-btn');
                const clientSelect = form ? form.querySelector('select[name="client_id"]') : null;
                const dateInput = form ? form.querySelector('input[name="work_date"]') : null;

                function refreshForSelection() {
                    if (!clientSelect || !dateInput) return;
                    const params = new URLSearchParams();
                    params.set('client_id', clientSelect.value);
                    params.set('work_date', dateInput.value);
                    window.location.href = '/work-logs/create?' + params.toString();
                }

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
                if (clientSelect) {
                    clientSelect.addEventListener('change', refreshForSelection);
                }
                if (dateInput) {
                    dateInput.addEventListener('change', refreshForSelection);
                }
                addTask();
            })();
            </script>
        <?php endif; ?>
        <?php
        return (string) ob_get_clean();
    }
}
