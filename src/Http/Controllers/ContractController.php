<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\ClientRepository;
use App\Repositories\ContractRepository;

final class ContractController extends AdminController
{
    public function index(): void
    {
        $this->requireAdmin();
        $rows = (new ContractRepository())->all();
        $this->renderPage('Ugovori', 'Pregled i upravljanje ugovorima s klijentima.', $this->indexContent($rows), 'contracts');
    }

    public function create(): void
    {
        $this->requireAdmin();
        $clients = (new ClientRepository())->all();
        $this->renderPage('Novi ugovor', 'Unos novog ugovora i osnovnih podataka.', $this->formContent($clients, null), 'contracts');
    }

    public function edit(): void
    {
        $this->requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);
        $row = (new ContractRepository())->find($id);
        if ($row === null) {
            http_response_code(404);
            echo 'Ugovor nije pronađen';
            return;
        }

        $clients = (new ClientRepository())->all();
        $this->renderPage('Uredi ugovor', 'Ažuriranje postojećeg ugovora.', $this->formContent($clients, $row), 'contracts');
    }

    public function store(): void
    {
        $this->requireAdmin();
        (new ContractRepository())->create($this->payload($_POST));
        $this->redirect('/contracts');
    }

    public function update(): void
    {
        $this->requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);
        (new ContractRepository())->update($id, $this->payload($_POST));
        $this->redirect('/contracts');
    }

    public function delete(): void
    {
        $this->requireAdmin();
        (new ContractRepository())->delete((int) ($_GET['id'] ?? 0));
        $this->redirect('/contracts');
    }

    private function payload(array $input): array
    {
        return [
            'client_id' => (int) ($input['client_id'] ?? 0),
            'contract_name' => (string) ($input['contract_name'] ?? ''),
            'contract_number' => $this->n($input['contract_number'] ?? null),
            'start_date' => (string) ($input['start_date'] ?? ''),
            'end_date' => (string) ($input['end_date'] ?? ''),
            'status' => (string) ($input['status'] ?? 'active'),
            'contract_file_url' => $this->n($input['contract_file_url'] ?? null),
            'contract_file_name' => $this->n($input['contract_file_name'] ?? null),
            'value' => $this->n($input['value'] ?? null),
            'maintenance_billing_period' => (string) ($input['maintenance_billing_period'] ?? 'none'),
            'maintenance_amount' => $this->n($input['maintenance_amount'] ?? null),
            'auto_renewal' => isset($input['auto_renewal']) ? 1 : 0,
            'reminder_days' => (int) ($input['reminder_days'] ?? 14),
            'notes' => $this->n($input['notes'] ?? null),
        ];
    }

    private function n(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        return $value === '' ? null : $value;
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path);
        exit;
    }

    private function indexContent(array $rows): string
    {
        ob_start();
        ?>
        <section class="panel">
            <table>
                <thead>
                <tr>
                    <th>Klijent</th>
                    <th>Naziv</th>
                    <th>Rok</th>
                    <th>Status</th>
                    <th>Vrijednost</th>
                    <th>Akcije</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="6" class="muted">Nema ugovora.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars((string) ($row['client_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><?= htmlspecialchars((string) ($row['contract_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($this->formatDate((string) ($row['end_date'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="chip gray"><?= htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td style="color:#2b9c3d;font-weight:700"><?= number_format((float) ($row['value'] ?? 0), 2, ',', '.') ?> €</td>
                            <td><div class="actions"><a class="chip" href="/contracts/edit?id=<?= (int) $row['id'] ?>">Uredi</a><a class="chip gray" href="/contracts/delete?id=<?= (int) $row['id'] ?>" onclick="return confirm('Obrisati ugovor?')">Briši</a></div></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function formContent(array $clients, ?array $row): string
    {
        $isEdit = $row !== null;
        $value = static fn(string $key, string $default = '') => htmlspecialchars((string) ($row[$key] ?? $default), ENT_QUOTES, 'UTF-8');
        $selected = static function (?array $row, string $key, string $option, string $default = ''): string {
            return (($row[$key] ?? $default) === $option) ? 'selected' : '';
        };
        $checked = isset($row['auto_renewal']) ? ((int) $row['auto_renewal'] === 1) : false;
        $action = $isEdit ? '/contracts/update?id=' . (int) $row['id'] : '/contracts';

        ob_start();
        ?>
        <form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" class="content">
            <section class="panel pad">
                <div class="section-title">
                    <h2><?= $isEdit ? 'Uredi ugovor' : 'Novi ugovor' ?></h2>
                    <div class="muted">Detalji ugovora, rok i vrijednost.</div>
                </div>
                <div class="grid-2">
                    <div><label>Klijent</label><select class="input" name="client_id" required><?php foreach ($clients as $client): ?><option value="<?= (int) $client['id'] ?>" <?= ((int) ($row['client_id'] ?? 0) === (int) $client['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string) $client['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
                    <div><label>Naziv ugovora</label><input class="input" name="contract_name" required value="<?= $value('contract_name') ?>"></div>
                    <div><label>Broj ugovora</label><input class="input" name="contract_number" value="<?= $value('contract_number') ?>"></div>
                    <div><label>Status</label><select class="input" name="status"><?php foreach (['active','expired','terminated'] as $option): ?><option value="<?= $option ?>" <?= $selected($row, 'status', $option, 'active') ?>><?= $option ?></option><?php endforeach; ?></select></div>
                    <div><label>Početak</label><input class="input" name="start_date" type="date" required value="<?= $value('start_date') ?>"></div>
                    <div><label>Kraj</label><input class="input" name="end_date" type="date" required value="<?= $value('end_date') ?>"></div>
                    <div><label>Vrijednost</label><input class="input" name="value" inputmode="decimal" value="<?= $value('value') ?>"></div>
                    <div><label>Podsjetnik dana prije isteka</label><input class="input" name="reminder_days" type="number" value="<?= htmlspecialchars((string) ($row['reminder_days'] ?? 14), ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div><label>Period održavanja</label><select class="input" name="maintenance_billing_period"><?php foreach (['none','monthly','quarterly','annual'] as $option): ?><option value="<?= $option ?>" <?= $selected($row, 'maintenance_billing_period', $option, 'none') ?>><?= $option ?></option><?php endforeach; ?></select></div>
                    <div><label>Iznos održavanja</label><input class="input" name="maintenance_amount" inputmode="decimal" value="<?= $value('maintenance_amount') ?>"></div>
                    <div><label>Datoteka</label><input class="input" name="contract_file_name" value="<?= $value('contract_file_name') ?>"></div>
                    <div><label>URL datoteke</label><input class="input" name="contract_file_url" value="<?= $value('contract_file_url') ?>"></div>
                    <div style="grid-column:1/-1"><label>Bilješke</label><textarea class="input" name="notes" rows="6"><?= $value('notes') ?></textarea></div>
                    <div style="grid-column:1/-1"><label><input type="checkbox" name="auto_renewal" <?= $checked ? 'checked' : '' ?>> Automatsko obnavljanje</label></div>
                </div>
            </section>
            <div class="actions"><button class="btn" type="submit"><?= $isEdit ? 'Spremi promjene' : 'Spremi' ?></button><a class="btn secondary" href="/contracts">Nazad</a></div>
        </form>
        <?php
        return (string) ob_get_clean();
    }
}
