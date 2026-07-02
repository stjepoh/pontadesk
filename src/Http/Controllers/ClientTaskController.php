<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\ClientRepository;
use App\Repositories\ClientTaskRepository;

final class ClientTaskController extends AdminController
{
    public function index(): void
    {
        $this->requireAdmin();

        $clients = (new ClientRepository())->all();
        $tasks = (new ClientTaskRepository())->all();
        $status = (string) ($_GET['status'] ?? 'all');
        $clientId = (int) ($_GET['client_id'] ?? 0);

        $content = $this->content($clients, $tasks, $status, $clientId);
        $this->renderPage(
            'To-Do po klijentima',
            $this->subtitle($tasks),
            $content,
            'tasks',
            '<a class="btn secondary" href="' . htmlspecialchars($this->mailto($tasks), ENT_QUOTES, 'UTF-8') . '">✉ Pošalji na email</a>',
            'todo-page'
        );
    }

    public function store(): void
    {
        $this->requireAdmin();

        $clientId = (int) ($_POST['client_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $priority = (string) ($_POST['priority'] ?? 'normal');

        if ($clientId > 0 && $title !== '') {
            (new ClientTaskRepository())->create($clientId, $title, $priority);
        }

        $this->redirect($this->backUrl($clientId));
    }

    public function toggle(): void
    {
        $this->requireAdmin();

        $id = (int) ($_POST['id'] ?? 0);
        $clientId = (int) ($_POST['client_id'] ?? 0);
        if ($id > 0) {
            (new ClientTaskRepository())->toggle($id);
        }

        $this->redirect($this->backUrl($clientId));
    }

    public function delete(): void
    {
        $this->requireAdmin();

        $id = (int) ($_POST['id'] ?? 0);
        $clientId = (int) ($_POST['client_id'] ?? 0);
        if ($id > 0) {
            (new ClientTaskRepository())->delete($id);
        }

        $this->redirect($this->backUrl($clientId));
    }

    private function content(array $clients, array $tasks, string $status, int $clientId): string
    {
        $groups = $this->groups($clients, $tasks, $status, $clientId);
        $openCount = count(array_filter($tasks, static fn(array $task): bool => (int) ($task['done'] ?? 0) === 0));
        $statusUrl = static fn(string $newStatus): string => '/tasks?' . http_build_query([
            'status' => $newStatus,
            'client_id' => $clientId,
        ]);

        ob_start();
        ?>
        <style>
            .todo-page{max-width:820px}
            .todo-page .hero{align-items:flex-start;margin-bottom:18px}
            .todo-page .hero h1{font-size:24px;letter-spacing:-.035em}
            .todo-page .hero p{font-size:13px}
            .todo-toolbar{display:flex;justify-content:space-between;gap:14px;align-items:center;margin-bottom:14px;flex-wrap:wrap}
            .todo-filters{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
            .todo-tab{height:32px;display:inline-flex;align-items:center;justify-content:center;border-radius:7px;padding:0 13px;font-size:12px;font-weight:800;color:#6f7f97;background:#fff;border:1px solid #e1e7f2}
            .todo-tab.active{background:#3869f4;color:#fff;border-color:#3869f4;box-shadow:0 6px 14px rgba(56,105,244,.22)}
            .todo-client-filter{width:180px;height:32px;border-radius:7px;font-size:12px;padding:6px 10px}
            .todo-list{display:grid;gap:12px}
            .todo-card{padding:0;overflow:hidden;border-radius:13px}
            .todo-card-head{height:44px;display:grid;grid-template-columns:18px 1fr auto 86px;gap:10px;align-items:center;padding:0 16px;border-bottom:1px solid #edf1f6}
            .todo-chevron{font-size:12px;color:#8a98b0}
            .todo-client{display:flex;align-items:center;gap:9px;min-width:0}
            .todo-client strong{font-size:13px;color:#182b49;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
            .todo-client a{color:#7c8da6;font-size:12px}
            .todo-done-count{font-size:11px;color:#8a98b0;white-space:nowrap}
            .todo-progress{height:4px;background:#edf2f8;border-radius:999px;overflow:hidden}
            .todo-progress span{display:block;height:100%;background:#52d273;border-radius:999px}
            .todo-items{display:grid;padding:8px 16px 10px}
            .todo-row{display:grid;grid-template-columns:24px 18px 1fr 58px 28px;gap:8px;align-items:center;min-height:30px;font-size:12px;color:#2d405f}
            .todo-row.done{color:#b6c0cf}
            .todo-toggle{width:16px;height:16px;border-radius:50%;border:1.8px solid #53c878;background:#fff;color:#53c878;display:grid;place-items:center;font-size:10px;line-height:1;cursor:pointer;padding:0}
            .todo-row.done .todo-toggle{background:#eaf8ef}
            .todo-priority{font-size:12px;color:#d0d8e5}
            .todo-priority.high{color:#ef4b4b}
            .todo-date{text-align:right;color:#c2ccda;font-size:11px}
            .todo-delete{border:0;background:transparent;color:#d6deea;cursor:pointer;font-size:13px;padding:4px}
            .todo-delete:hover{color:#e74b4b}
            .todo-add{border-top:1px solid #f0f3f8;margin-top:4px;padding-top:6px}
            .todo-add summary{list-style:none;cursor:pointer;color:#9aa9bd;font-size:12px;padding:7px 0}
            .todo-add summary::-webkit-details-marker{display:none}
            .todo-add-form{display:grid;grid-template-columns:1fr 110px auto;gap:8px;align-items:center;padding:4px 0 8px}
            .todo-add-form .input,.todo-add-form select{height:34px;border-radius:8px;font-size:12px;padding:7px 10px}
            .todo-empty{padding:8px 0 10px;color:#b0bdce;font-size:12px}
            @media (max-width:760px){.todo-page{max-width:none}.todo-toolbar{align-items:stretch}.todo-filters,.todo-client-filter{width:100%}.todo-tab{flex:1}.todo-card-head{grid-template-columns:16px 1fr;min-height:50px;height:auto;padding:10px 14px}.todo-done-count,.todo-progress{grid-column:2}.todo-row{grid-template-columns:22px 16px 1fr}.todo-date,.todo-delete{grid-column:3;text-align:left}.todo-add-form{grid-template-columns:1fr}.todo-add-form .btn{width:100%}}
        </style>

        <section class="todo-toolbar">
            <form method="get" action="/tasks" class="todo-filters" data-todo-filter>
                <a class="todo-tab <?= $status === 'all' ? 'active' : '' ?>" href="<?= htmlspecialchars($statusUrl('all'), ENT_QUOTES, 'UTF-8') ?>">Sve</a>
                <a class="todo-tab <?= $status === 'open' ? 'active' : '' ?>" href="<?= htmlspecialchars($statusUrl('open'), ENT_QUOTES, 'UTF-8') ?>">Neodrađeno</a>
                <a class="todo-tab <?= $status === 'done' ? 'active' : '' ?>" href="<?= htmlspecialchars($statusUrl('done'), ENT_QUOTES, 'UTF-8') ?>">Odrađeno</a>
                <input type="hidden" name="status" value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">
                <select class="input todo-client-filter" name="client_id">
                    <option value="0">Svi klijenti</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= (int) $client['id'] ?>" <?= $clientId === (int) $client['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $client['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <div class="muted" style="font-size:12px"><?= $openCount ?> neodrađenih zadataka</div>
        </section>

        <section class="todo-list">
            <?php foreach ($groups as $group): ?>
                <?php
                $client = $group['client'];
                $clientTasks = $group['tasks'];
                $done = (int) $group['done'];
                $total = (int) $group['total'];
                $progress = $total > 0 ? (int) round(($done / $total) * 100) : 0;
                ?>
                <article class="panel todo-card">
                    <div class="todo-card-head">
                        <span class="todo-chevron">⌄</span>
                        <div class="todo-client">
                            <strong><?= htmlspecialchars((string) $client['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <a href="/clients/detail?id=<?= (int) $client['id'] ?>" title="Otvori klijenta">↗</a>
                        </div>
                        <div class="todo-done-count"><?= $done ?>/<?= $total ?> odrađeno</div>
                        <div class="todo-progress"><span style="width:<?= $progress ?>%"></span></div>
                    </div>
                    <div class="todo-items">
                        <?php if ($clientTasks === []): ?>
                            <div class="todo-empty">Nema zadataka za odabrani filter.</div>
                        <?php endif; ?>
                        <?php foreach ($clientTasks as $task): ?>
                            <?php $isDone = (int) ($task['done'] ?? 0) === 1; ?>
                            <div class="todo-row <?= $isDone ? 'done' : '' ?>">
                                <form method="post" action="/tasks/toggle">
                                    <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                                    <input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>">
                                    <button class="todo-toggle" type="submit" title="<?= $isDone ? 'Vrati u neodrađeno' : 'Označi odrađeno' ?>"><?= $isDone ? '✓' : '' ?></button>
                                </form>
                                <span class="todo-priority <?= (string) ($task['priority'] ?? '') === 'high' ? 'high' : '' ?>">⚐</span>
                                <span><?= htmlspecialchars((string) ($task['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="todo-date"><?= htmlspecialchars($this->shortDate((string) ($task['done_date'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                                <form method="post" action="/tasks/delete" onsubmit="return confirm('Obrisati ovaj zadatak?')">
                                    <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                                    <input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>">
                                    <button class="todo-delete" type="submit" title="Obriši">×</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                        <details class="todo-add">
                            <summary>+ Dodaj zadatak</summary>
                            <form method="post" action="/tasks" class="todo-add-form">
                                <input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>">
                                <input class="input" name="title" required placeholder="Upiši novi zadatak...">
                                <select class="input" name="priority">
                                    <option value="normal">Normalno</option>
                                    <option value="high">Hitno</option>
                                    <option value="low">Nisko</option>
                                </select>
                                <button class="btn" type="submit">Dodaj</button>
                            </form>
                        </details>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <script>
        (function () {
            const form = document.querySelector('[data-todo-filter]');
            if (!form) return;
            form.querySelector('select[name="client_id"]')?.addEventListener('change', function () {
                form.requestSubmit ? form.requestSubmit() : form.submit();
            });
        })();
        </script>
        <?php

        return (string) ob_get_clean();
    }

    private function groups(array $clients, array $tasks, string $status, int $clientId): array
    {
        if ($clientId > 0) {
            $clients = array_values(array_filter($clients, static fn(array $client): bool => (int) ($client['id'] ?? 0) === $clientId));
        }

        $tasksByClient = [];
        foreach ($tasks as $task) {
            $taskClientId = (int) ($task['client_id'] ?? 0);
            $done = (int) ($task['done'] ?? 0) === 1;
            if ($status === 'open' && $done) {
                continue;
            }
            if ($status === 'done' && !$done) {
                continue;
            }
            $tasksByClient[$taskClientId][] = $task;
        }

        $allTasksByClient = [];
        foreach ($tasks as $task) {
            $allTasksByClient[(int) ($task['client_id'] ?? 0)][] = $task;
        }

        $groups = [];
        foreach ($clients as $client) {
            $id = (int) ($client['id'] ?? 0);
            $allClientTasks = $allTasksByClient[$id] ?? [];
            $done = count(array_filter($allClientTasks, static fn(array $task): bool => (int) ($task['done'] ?? 0) === 1));
            $groups[] = [
                'client' => $client,
                'tasks' => $tasksByClient[$id] ?? [],
                'done' => $done,
                'total' => count($allClientTasks),
            ];
        }

        return $groups;
    }

    private function subtitle(array $tasks): string
    {
        $open = count(array_filter($tasks, static fn(array $task): bool => (int) ($task['done'] ?? 0) === 0));
        return $open . ' neodrađenih zadataka';
    }

    private function shortDate(string $value): string
    {
        $formatted = $this->formatDate($value);
        if ($formatted === '') {
            return '';
        }

        return substr($formatted, 0, 5) . '.';
    }

    private function mailto(array $tasks): string
    {
        $open = array_values(array_filter($tasks, static fn(array $task): bool => (int) ($task['done'] ?? 0) === 0));
        $lines = array_map(static fn(array $task): string => '- ' . (string) ($task['client_name'] ?? '') . ': ' . (string) ($task['title'] ?? ''), array_slice($open, 0, 40));
        return 'mailto:?subject=' . rawurlencode('PontaDesk To-Do') . '&body=' . rawurlencode(implode("\n", $lines));
    }

    private function backUrl(int $clientId): string
    {
        $status = (string) ($_POST['status'] ?? $_GET['status'] ?? 'all');
        return '/tasks?' . http_build_query([
            'status' => in_array($status, ['all', 'open', 'done'], true) ? $status : 'all',
            'client_id' => $clientId,
        ]);
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path);
        exit;
    }
}
