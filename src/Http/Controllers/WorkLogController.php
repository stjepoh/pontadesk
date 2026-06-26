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

        ob_start();
        ?>
        <section class="panel">
            <table>
                <thead>
                <tr>
                    <th>Klijent</th>
                    <th>Datum</th>
                    <th>Ukupno</th>
                    <th>Zadaci</th>
                    <th>Naplaćeno</th>
                    <th>Akcije</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($groups === []): ?>
                    <tr><td colspan="6" class="muted">Nema radnih sati.</td></tr>
                <?php else: ?>
                    <?php foreach ($groups as $group): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars((string) $group['client_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><?= htmlspecialchars($this->formatDate((string) $group['date']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= (int) ($group['total_minutes'] ?? 0) ?> min</td>
                            <td><?= count($group['items'] ?? []) ?></td>
                            <td><span class="chip <?= $this->groupPaid($group['items'] ?? []) ? 'green' : 'gray' ?>"><?= $this->groupPaid($group['items'] ?? []) ? 'Da' : 'Ne' ?></span></td>
                            <td><div class="actions"><a class="chip" href="/work-logs/create?client_id=<?= urlencode((string) ($group['items'][0]['client_id'] ?? '')) ?>&work_date=<?= urlencode((string) $group['date']) ?>">Dodaj zadatak</a></div></td>
                        </tr>
                        <tr>
                            <td colspan="6" style="padding-top:0">
                                <div class="mini-list" style="margin:0 0 12px 0">
                                    <?php foreach ($group['items'] as $item): ?>
                                        <div class="mini-item">
                                            <div>
                                                <strong><?= htmlspecialchars((string) ($item['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                                <div class="muted"><?= (int) ($item['duration_minutes'] ?? 0) ?> min</div>
                                            </div>
                                            <div class="actions">
                                                <a class="chip gray" href="/work-logs/edit?id=<?= (int) $item['id'] ?>">Uredi</a>
                                                <a class="chip gray" href="/work-logs/delete?id=<?= (int) $item['id'] ?>" onclick="return confirm('Obrisati zapis?')">Briši</a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
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
