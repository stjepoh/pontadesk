<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\ClientNoteRepository;
use App\Repositories\ClientRepository;

final class ClientNoteController extends AdminController
{
    public function index(): void
    {
        $this->requireAdmin();

        $clients = (new ClientRepository())->all();
        $counts = (new ClientNoteRepository())->countsByClient();

        $this->renderPage(
            'Specifičnosti klijenata',
            'Odaberite klijenta za pregled specifičnosti',
            $this->indexContent($clients, $counts),
            'notes',
            '',
            'notes-list-page'
        );
    }

    public function detail(): void
    {
        $this->requireAdmin();

        $clientId = (int) ($_GET['client_id'] ?? 0);
        $client = (new ClientRepository())->find($clientId);
        if ($client === null) {
            $this->redirect('/notes');
        }

        $repository = new ClientNoteRepository();
        $category = $this->filterValue((string) ($_GET['category'] ?? 'all'), array_keys($repository->categoryOptions()));
        $importance = $this->filterValue((string) ($_GET['importance'] ?? 'all'), array_keys($repository->importanceOptions()));
        $notes = $repository->forClient($clientId, $category, $importance);
        $showForm = (string) ($_GET['add'] ?? '') === '1';

        $addUrl = '/notes/client?' . http_build_query(['client_id' => $clientId, 'add' => 1]);
        $actions = '<a class="btn notes-add-button" href="' . $this->h($addUrl) . '"><span>+</span> Dodaj</a>';

        $this->renderPage(
            (string) ($client['name'] ?? 'Klijent'),
            'Specifičnosti klijenta — ' . count($notes) . ' zapisa',
            $this->detailContent($client, $notes, $category, $importance, $showForm, $repository),
            'notes',
            $actions,
            'notes-detail-page'
        );
    }

    public function store(): void
    {
        $this->requireAdmin();

        $clientId = (int) ($_POST['client_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $content = trim((string) ($_POST['content'] ?? ''));

        if ($clientId > 0 && $title !== '' && (new ClientRepository())->find($clientId) !== null) {
            (new ClientNoteRepository())->create([
                'client_id' => $clientId,
                'title' => $title,
                'content' => $content,
                'category' => (string) ($_POST['category'] ?? 'other'),
                'importance' => (string) ($_POST['importance'] ?? 'normal'),
                'pinned' => !empty($_POST['pinned']),
            ]);
        }

        $this->redirect('/notes/client?' . http_build_query(['client_id' => $clientId]));
    }

    public function delete(): void
    {
        $this->requireAdmin();

        $id = (int) ($_POST['id'] ?? 0);
        $clientId = (int) ($_POST['client_id'] ?? 0);

        if ($id > 0) {
            (new ClientNoteRepository())->delete($id);
        }

        $this->redirect('/notes/client?' . http_build_query(['client_id' => $clientId]));
    }

    /**
     * @param array<int, array<string, mixed>> $clients
     * @param array<int, int> $counts
     */
    private function indexContent(array $clients, array $counts): string
    {
        ob_start();
        ?>
        <style>
            .notes-list-page{max-width:920px}
            .notes-list-page .hero{align-items:flex-start;margin-bottom:28px}
            .notes-list-page .hero h1{font-size:26px;letter-spacing:-.04em}
            .notes-list-page .hero p{font-size:15px}
            .notes-client-list{display:grid;gap:10px}
            .notes-client-row{display:grid;grid-template-columns:1fr auto 32px;align-items:center;gap:20px;min-height:68px;padding:0 20px;border:1px solid #dbe3ee;border-radius:13px;background:#fff;box-shadow:0 1px 2px rgba(16,35,63,.02)}
            .notes-client-row:hover{border-color:#c7d5eb;box-shadow:0 8px 22px rgba(16,35,63,.06);transform:translateY(-1px)}
            .notes-client-name{font-size:18px;font-weight:800;color:#0f2444;letter-spacing:-.02em}
            .notes-count{display:inline-flex;align-items:center;justify-content:center;min-width:82px;height:29px;border-radius:8px;background:#f3f6fa;color:#203a5c;font-size:12px;font-weight:800;box-shadow:0 2px 6px rgba(16,35,63,.12)}
            .notes-plus{width:30px;height:30px;display:grid;place-items:center;border-radius:8px;color:#3869f4;font-size:25px;line-height:1;font-weight:400}
            .notes-plus:hover{background:#eef4ff}
            .notes-empty{padding:18px;color:#6f7f97}
            @media (max-width:760px){.notes-list-page{max-width:none}.notes-client-row{grid-template-columns:1fr auto;gap:12px;padding:14px 16px}.notes-client-name{font-size:16px}.notes-plus{grid-column:2}.notes-count{min-width:74px}}
        </style>

        <section class="notes-client-list">
            <?php if ($clients === []): ?>
                <div class="panel notes-empty">Još nema unesenih klijenata.</div>
            <?php endif; ?>

            <?php foreach ($clients as $client): ?>
                <?php
                $clientId = (int) ($client['id'] ?? 0);
                $count = $counts[$clientId] ?? 0;
                $detailUrl = '/notes/client?' . http_build_query(['client_id' => $clientId]);
                $addUrl = '/notes/client?' . http_build_query(['client_id' => $clientId, 'add' => 1]);
                ?>
                <article class="notes-client-row">
                    <a class="notes-client-name" href="<?= $this->h($detailUrl) ?>"><?= $this->h((string) ($client['name'] ?? 'Klijent')) ?></a>
                    <span class="notes-count"><?= $count ?> zapisa</span>
                    <a class="notes-plus" href="<?= $this->h($addUrl) ?>" title="Dodaj specifičnost">+</a>
                </article>
            <?php endforeach; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $client
     * @param array<int, array<string, mixed>> $notes
     */
    private function detailContent(array $client, array $notes, string $category, string $importance, bool $showForm, ClientNoteRepository $repository): string
    {
        $clientId = (int) ($client['id'] ?? 0);
        $categoryOptions = $repository->categoryOptions();
        $importanceOptions = $repository->importanceOptions();
        $baseUrl = '/notes/client?' . http_build_query(['client_id' => $clientId]);

        ob_start();
        ?>
        <style>
            .notes-detail-page{max-width:920px;position:relative}
            .notes-detail-page .hero{padding-left:38px;margin-bottom:28px}
            .notes-detail-page .hero h1{font-size:26px;letter-spacing:-.04em}
            .notes-detail-page .hero p{font-size:15px}
            .notes-back{position:absolute;top:25px;left:0;width:28px;height:28px;border-radius:9px;display:grid;place-items:center;color:#6f7f97;font-size:32px;line-height:1}
            .notes-back:hover{background:#eef4ff;color:#315ff4}
            .notes-add-button{background:#111;color:#fff;border-radius:7px;height:40px;box-shadow:0 4px 10px rgba(0,0,0,.22)}
            .notes-add-button:hover{background:#1e1e1e}
            .notes-filters{display:flex;gap:10px;align-items:center;margin-bottom:20px;flex-wrap:wrap}
            .notes-filters select{width:180px;height:40px;border-radius:7px;background:#fff;font-size:13px;box-shadow:0 2px 5px rgba(16,35,63,.06)}
            .notes-form-panel{margin-bottom:20px;border-color:#cbd8ee}
            .notes-form-grid{display:grid;grid-template-columns:1fr 150px 150px auto;gap:12px;align-items:end}
            .notes-form-grid label{display:block;margin-bottom:7px;font-size:13px;font-weight:800;color:#243a5d}
            .notes-form-grid textarea{grid-column:1/-1;min-height:130px;line-height:1.45;resize:vertical}
            .notes-pinned-field{height:40px;display:flex;align-items:center;gap:8px;color:#304767;font-size:13px;font-weight:700}
            .notes-form-actions{grid-column:1/-1;display:flex;gap:10px;align-items:center}
            .notes-cards{display:grid;gap:18px}
            .notes-card{position:relative;border:1.5px solid #f3bf17;border-radius:13px;background:rgba(255,255,252,.82);padding:21px 30px 28px;box-shadow:0 8px 20px rgba(16,35,63,.05)}
            .notes-card.importance-high{border-color:#fb923c}
            .notes-card.importance-critical{border-color:#ef4444}
            .notes-card-head{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px}
            .notes-pin{color:#e1a900;font-size:17px;line-height:1}
            .notes-title{font-size:16px;font-weight:850;color:#10233f;letter-spacing:-.01em}
            .notes-pill{height:26px;display:inline-flex;align-items:center;border-radius:7px;padding:0 12px;background:#dce8ff;color:#244bd8;font-size:13px;font-weight:800;box-shadow:0 2px 6px rgba(49,95,244,.16)}
            .notes-pill.importance-low{background:#e9f7ef;color:#1f8f4a}
            .notes-pill.importance-high{background:#fff0d9;color:#b66100}
            .notes-pill.importance-critical{background:#ffe4e4;color:#c32020}
            .notes-body{white-space:pre-wrap;color:#334a68;line-height:1.52;font-size:16px}
            .notes-delete{position:absolute;right:14px;top:12px}
            .notes-delete button{border:0;background:transparent;color:#bcc7d8;font-size:13px;cursor:pointer;padding:6px}
            .notes-delete button:hover{color:#e74b4b}
            .notes-empty-detail{padding:22px;color:#6f7f97}
            @media (max-width:760px){.notes-detail-page{max-width:none}.notes-detail-page .hero{padding-left:0;padding-top:34px}.notes-back{top:16px;left:16px}.notes-filters select{width:100%}.notes-form-grid{grid-template-columns:1fr}.notes-card{padding:18px 18px 24px}.notes-body{font-size:15px}.notes-add-button{width:100%}}
        </style>

        <a class="notes-back" href="/notes" aria-label="Nazad">‹</a>

        <form method="get" action="/notes/client" class="notes-filters" data-notes-filter>
            <input type="hidden" name="client_id" value="<?= $clientId ?>">
            <select class="input" name="category">
                <option value="all" <?= $category === 'all' ? 'selected' : '' ?>>Sve kategorije</option>
                <?php foreach ($categoryOptions as $value => $label): ?>
                    <option value="<?= $this->h($value) ?>" <?= $category === $value ? 'selected' : '' ?>><?= $this->h($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="input" name="importance">
                <option value="all" <?= $importance === 'all' ? 'selected' : '' ?>>Sve važnosti</option>
                <?php foreach ($importanceOptions as $value => $label): ?>
                    <option value="<?= $this->h($value) ?>" <?= $importance === $value ? 'selected' : '' ?>><?= $this->h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <?php if ($showForm): ?>
            <section class="panel pad notes-form-panel">
                <form method="post" action="/notes" class="notes-form-grid">
                    <input type="hidden" name="client_id" value="<?= $clientId ?>">
                    <div>
                        <label>Naslov</label>
                        <input class="input" name="title" required placeholder="Npr. Unos termina">
                    </div>
                    <div>
                        <label>Kategorija</label>
                        <select class="input" name="category">
                            <?php foreach ($categoryOptions as $value => $label): ?>
                                <option value="<?= $this->h($value) ?>"><?= $this->h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Važnost</label>
                        <select class="input" name="importance">
                            <?php foreach ($importanceOptions as $value => $label): ?>
                                <option value="<?= $this->h($value) ?>" <?= $value === 'normal' ? 'selected' : '' ?>><?= $this->h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <label class="notes-pinned-field"><input type="checkbox" name="pinned" value="1"> Istaknuto</label>
                    <textarea class="input" name="content" placeholder="Upiši specifičnost, upute, pristupe ili sve što treba ostati zapamćeno za ovog klijenta..."></textarea>
                    <div class="notes-form-actions">
                        <button class="btn" type="submit">Spremi</button>
                        <a class="btn secondary" href="<?= $this->h($baseUrl) ?>">Odustani</a>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <section class="notes-cards">
            <?php if ($notes === []): ?>
                <div class="panel notes-empty-detail">
                    Nema zapisa za odabrane filtere. Kliknite <strong>Dodaj</strong> za unos prve specifičnosti.
                </div>
            <?php endif; ?>

            <?php foreach ($notes as $note): ?>
                <?php
                $noteImportance = (string) ($note['importance'] ?? 'normal');
                $noteCategory = (string) ($note['category'] ?? 'other');
                ?>
                <article class="notes-card importance-<?= $this->h($noteImportance) ?>">
                    <form method="post" action="/notes/delete" class="notes-delete" onsubmit="return confirm('Obrisati ovu specifičnost?')">
                        <input type="hidden" name="id" value="<?= (int) ($note['id'] ?? 0) ?>">
                        <input type="hidden" name="client_id" value="<?= $clientId ?>">
                        <button type="submit" title="Obriši">Obriši</button>
                    </form>
                    <div class="notes-card-head">
                        <span class="notes-pin" aria-hidden="true"><?= !empty($note['pinned']) ? '⚑' : '•' ?></span>
                        <span class="notes-title"><?= $this->h((string) ($note['title'] ?? 'Bilješka')) ?></span>
                        <span class="notes-pill"><?= $this->h($categoryOptions[$noteCategory] ?? $noteCategory) ?></span>
                        <span class="notes-pill importance-<?= $this->h($noteImportance) ?>"><?= $this->h($importanceOptions[$noteImportance] ?? $noteImportance) ?></span>
                    </div>
                    <div class="notes-body"><?= $this->h((string) ($note['content'] ?? '')) ?></div>
                </article>
            <?php endforeach; ?>
        </section>

        <script>
        (function () {
            const form = document.querySelector('[data-notes-filter]');
            if (!form) return;
            form.querySelectorAll('select').forEach(function (select) {
                select.addEventListener('change', function () {
                    form.requestSubmit ? form.requestSubmit() : form.submit();
                });
            });
        })();
        </script>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<int, string> $allowed
     */
    private function filterValue(string $value, array $allowed): string
    {
        if ($value === 'all') {
            return 'all';
        }

        return in_array($value, $allowed, true) ? $value : 'all';
    }

    private function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path);
        exit;
    }
}
