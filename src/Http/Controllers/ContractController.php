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

        $status = $this->statusFilter((string) ($_GET['status'] ?? 'active'));
        $clientId = (int) ($_GET['client_id'] ?? 0);
        $clients = (new ClientRepository())->all();
        $rows = (new ContractRepository())->all($status, $clientId);
        $createUrl = '/contracts/create?' . http_build_query(array_filter([
            'client_id' => $clientId > 0 ? $clientId : null,
            'status' => $status !== 'all' ? $status : null,
        ]));

        $this->renderPage(
            'Ugovori',
            $this->contractsSubtitle($status, count($rows)),
            $this->indexContent($rows, $clients, $status, $clientId),
            'contracts',
            '<a class="btn" href="' . htmlspecialchars($createUrl, ENT_QUOTES, 'UTF-8') . '">+ Novi ugovor</a>'
        );
    }

    public function create(): void
    {
        $this->requireAdmin();
        $clients = (new ClientRepository())->all();
        $clientId = (int) ($_GET['client_id'] ?? 0);
        $status = $this->statusFilter((string) ($_GET['status'] ?? 'active'));
        $this->renderPage('Novi ugovor', 'Unos novog ugovora i osnovnih podataka.', $this->formContent($clients, null, $clientId, $status), 'contracts');
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
        $status = $this->statusFilter((string) ($_GET['status'] ?? ($row['status'] ?? 'active')));
        $this->renderPage('Uredi ugovor', 'Ažuriranje postojećeg ugovora.', $this->formContent($clients, $row, 0, $status), 'contracts');
    }

    public function store(): void
    {
        $this->requireAdmin();
        $payload = $this->payload($_POST);
        (new ContractRepository())->create($payload);
        $this->redirect('/contracts?' . http_build_query([
            'status' => $this->statusFilter((string) $payload['status']),
            'client_id' => (int) $payload['client_id'],
        ]));
    }

    public function update(): void
    {
        $this->requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);
        $payload = $this->payload($_POST);
        (new ContractRepository())->update($id, $payload);
        $this->redirect('/contracts?' . http_build_query([
            'status' => $this->statusFilter((string) ($_POST['return_status'] ?? $payload['status'])),
            'client_id' => (int) $payload['client_id'],
        ]));
    }

    public function delete(): void
    {
        $this->requireAdmin();
        (new ContractRepository())->delete((int) ($_GET['id'] ?? 0));
        $this->redirect('/contracts?' . http_build_query([
            'status' => $this->statusFilter((string) ($_GET['status'] ?? 'active')),
            'client_id' => (int) ($_GET['client_id'] ?? 0),
        ]));
    }

    private function payload(array $input): array
    {
        return [
            'client_id' => (int) ($input['client_id'] ?? 0),
            'contract_name' => (string) ($input['contract_name'] ?? ''),
            'contract_number' => $this->n($input['contract_number'] ?? null),
            'start_date' => $this->normalizeDate((string) ($input['start_date'] ?? '')),
            'end_date' => $this->normalizeDate((string) ($input['end_date'] ?? '')),
            'status' => $this->contractStatus((string) ($input['status'] ?? 'active')),
            'contract_file_url' => $this->n($input['contract_file_url'] ?? null),
            'contract_file_name' => $this->n($input['contract_file_name'] ?? null),
            'value' => $this->n($input['value'] ?? null),
            'maintenance_billing_period' => $this->maintenancePeriod((string) ($input['maintenance_billing_period'] ?? 'none')),
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

    private function statusFilter(string $status): string
    {
        return array_key_exists($status, $this->statusOptions()) ? $status : 'active';
    }

    private function contractStatus(string $status): string
    {
        return in_array($status, ['active', 'expired', 'terminated'], true) ? $status : 'active';
    }

    private function maintenancePeriod(string $period): string
    {
        return in_array($period, ['none', 'monthly', 'quarterly', 'annual'], true) ? $period : 'none';
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        return [
            'active' => 'Aktivni',
            'expired' => 'Istekli',
            'terminated' => 'Raskinuti',
            'all' => 'Svi',
        ];
    }

    private function statusLabel(string $status): string
    {
        return [
            'active' => 'Aktivan',
            'expired' => 'Istekao',
            'terminated' => 'Raskinut',
        ][$status] ?? $status;
    }

    private function maintenanceLabel(string $period): string
    {
        return [
            'none' => 'Nema',
            'monthly' => 'Mjesečno',
            'quarterly' => 'Kvartalno',
            'annual' => 'Godišnje',
        ][$period] ?? $period;
    }

    private function contractsSubtitle(string $status, int $count): string
    {
        $label = $this->statusOptions()[$status] ?? 'Aktivni';
        return $label . ' ugovori — ' . $count . ' prikazano';
    }

    private function indexContent(array $rows, array $clients, string $status, int $clientId): string
    {
        $statusOptions = $this->statusOptions();
        $statusUrl = static fn(string $newStatus): string => '/contracts?' . http_build_query([
            'status' => $newStatus,
            'client_id' => $clientId,
        ]);
        $createUrl = '/contracts/create?' . http_build_query(array_filter([
            'client_id' => $clientId > 0 ? $clientId : null,
            'status' => $status !== 'all' ? $status : null,
        ]));

        ob_start();
        ?>
        <style>
            .contracts-toolbar{display:flex;justify-content:space-between;gap:14px;align-items:center;margin-bottom:16px;flex-wrap:wrap}
            .contracts-filters{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
            .contracts-tab{height:36px;display:inline-flex;align-items:center;justify-content:center;border-radius:10px;padding:0 14px;font-size:13px;font-weight:800;color:#64748b;background:#fff;border:1px solid #dbe3ee}
            .contracts-tab.active{background:#3869f4;color:#fff;border-color:#3869f4;box-shadow:0 8px 18px rgba(56,105,244,.24)}
            .contracts-client-filter{width:240px;height:36px;border-radius:10px;font-size:13px;padding:7px 10px}
            .contracts-name{display:grid;gap:3px}
            .contracts-number{font-size:12px;color:#6f7f97}
            .contracts-dates{display:grid;gap:3px}
            .contracts-start{font-size:12px;color:#6f7f97}
            .contracts-actions{display:flex;gap:8px;flex-wrap:wrap}
            .contracts-action-danger{background:#fff1f1;color:#b42323}
            @media (max-width:760px){.contracts-toolbar{align-items:stretch}.contracts-filters,.contracts-client-filter,.contracts-toolbar .btn{width:100%}.contracts-tab{flex:1}.contracts-table-wrap{overflow:auto}}
        </style>

        <section class="panel pad contracts-toolbar">
            <form method="get" action="/contracts" class="contracts-filters" data-contracts-filter>
                <?php foreach ($statusOptions as $value => $label): ?>
                    <a class="contracts-tab <?= $status === $value ? 'active' : '' ?>" href="<?= htmlspecialchars($statusUrl($value), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
                <?php endforeach; ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">
                <select class="input contracts-client-filter" name="client_id">
                    <option value="0">Svi klijenti</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= (int) $client['id'] ?>" <?= $clientId === (int) $client['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $client['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a class="btn" href="<?= htmlspecialchars($createUrl, ENT_QUOTES, 'UTF-8') ?>">+ Novi ugovor</a>
        </section>

        <section class="panel">
            <div class="contracts-table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Klijent</th>
                    <th>Naziv</th>
                    <th>Razdoblje</th>
                    <th>Status</th>
                    <th>Vrijednost</th>
                    <th>Održavanje</th>
                    <th>Akcije</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="7" class="muted">Nema ugovora za odabrane filtere.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $editUrl = '/contracts/edit?' . http_build_query([
                            'id' => (int) $row['id'],
                            'status' => $status,
                            'client_id' => $clientId,
                        ]);
                        $deleteUrl = '/contracts/delete?' . http_build_query([
                            'id' => (int) $row['id'],
                            'status' => $status,
                            'client_id' => $clientId,
                        ]);
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars((string) ($row['client_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td>
                                <div class="contracts-name">
                                    <strong><?= htmlspecialchars((string) ($row['contract_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                    <?php if (!empty($row['contract_number'])): ?>
                                        <span class="contracts-number">Br. <?= htmlspecialchars((string) $row['contract_number'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="contracts-dates">
                                    <strong><?= htmlspecialchars($this->formatDate((string) ($row['end_date'] ?? '')), ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span class="contracts-start">od <?= htmlspecialchars($this->formatDate((string) ($row['start_date'] ?? '')), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </td>
                            <td><span class="chip <?= (string) ($row['status'] ?? '') === 'active' ? 'green' : 'gray' ?>"><?= htmlspecialchars($this->statusLabel((string) ($row['status'] ?? 'active')), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td style="color:#2b9c3d;font-weight:700"><?= number_format((float) ($row['value'] ?? 0), 2, ',', '.') ?> €</td>
                            <td><?= htmlspecialchars($this->maintenanceLabel((string) ($row['maintenance_billing_period'] ?? 'none')), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <div class="contracts-actions">
                                    <a class="chip" href="<?= htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') ?>">Uredi</a>
                                    <a class="chip contracts-action-danger" href="<?= htmlspecialchars($deleteUrl, ENT_QUOTES, 'UTF-8') ?>" onclick="return confirm('Obrisati ugovor?')">Briši</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
        </section>

        <script>
        (function () {
            const form = document.querySelector('[data-contracts-filter]');
            if (!form) return;
            form.querySelector('select[name="client_id"]')?.addEventListener('change', function () {
                form.requestSubmit ? form.requestSubmit() : form.submit();
            });
        })();
        </script>
        <?php
        return (string) ob_get_clean();
    }

    private function formContent(array $clients, ?array $row, int $selectedClientId = 0, string $returnStatus = 'active'): string
    {
        $isEdit = $row !== null;
        $value = static fn(string $key, string $default = '') => htmlspecialchars((string) ($row[$key] ?? $default), ENT_QUOTES, 'UTF-8');
        $selected = static function (?array $row, string $key, string $option, string $default = ''): string {
            return (($row[$key] ?? $default) === $option) ? 'selected' : '';
        };
        $checked = isset($row['auto_renewal']) ? ((int) $row['auto_renewal'] === 1) : false;
        $action = $isEdit ? '/contracts/update?id=' . (int) $row['id'] : '/contracts';
        $selectedClientId = (int) ($row['client_id'] ?? $selectedClientId);

        ob_start();
        ?>
        <form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" class="content">
            <input type="hidden" name="return_status" value="<?= htmlspecialchars($returnStatus, ENT_QUOTES, 'UTF-8') ?>">
            <section class="panel pad">
                <div class="section-title">
                    <h2><?= $isEdit ? 'Uredi ugovor' : 'Novi ugovor' ?></h2>
                    <div class="muted">Detalji ugovora, rok i vrijednost.</div>
                </div>
                <div class="grid-2">
                    <div><label>Klijent</label><select class="input" name="client_id" required><?php foreach ($clients as $client): ?><option value="<?= (int) $client['id'] ?>" <?= ($selectedClientId === (int) $client['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string) $client['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
                    <div><label>Naziv ugovora</label><input class="input" name="contract_name" required value="<?= $value('contract_name') ?>"></div>
                    <div><label>Broj ugovora</label><input class="input" name="contract_number" value="<?= $value('contract_number') ?>"></div>
                    <div><label>Status</label><select class="input" name="status"><?php foreach (['active','expired','terminated'] as $option): ?><option value="<?= $option ?>" <?= $selected($row, 'status', $option, 'active') ?>><?= htmlspecialchars($this->statusLabel($option), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
                    <div><label>Početak</label><?= $this->dateField('start_date', (string) ($row['start_date'] ?? ''), true) ?></div>
                    <div><label>Kraj</label><?= $this->dateField('end_date', (string) ($row['end_date'] ?? ''), true) ?></div>
                    <div><label>Vrijednost</label><input class="input" name="value" inputmode="decimal" value="<?= $value('value') ?>"></div>
                    <div><label>Podsjetnik dana prije isteka</label><input class="input" name="reminder_days" type="number" value="<?= htmlspecialchars((string) ($row['reminder_days'] ?? 14), ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div><label>Period održavanja</label><select class="input" name="maintenance_billing_period"><?php foreach (['none','monthly','quarterly','annual'] as $option): ?><option value="<?= $option ?>" <?= $selected($row, 'maintenance_billing_period', $option, 'none') ?>><?= htmlspecialchars($this->maintenanceLabel($option), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
                    <div><label>Iznos održavanja</label><input class="input" name="maintenance_amount" inputmode="decimal" value="<?= $value('maintenance_amount') ?>"></div>
                    <div><label>Datoteka</label><input class="input" name="contract_file_name" value="<?= $value('contract_file_name') ?>"></div>
                    <div><label>URL datoteke</label><input class="input" name="contract_file_url" value="<?= $value('contract_file_url') ?>"></div>
                    <div style="grid-column:1/-1"><label>Bilješke</label><textarea class="input" name="notes" rows="6"><?= $value('notes') ?></textarea></div>
                    <div style="grid-column:1/-1"><label><input type="checkbox" name="auto_renewal" <?= $checked ? 'checked' : '' ?>> Automatsko obnavljanje</label></div>
                </div>
            </section>
            <div class="actions"><button class="btn" type="submit"><?= $isEdit ? 'Spremi promjene' : 'Spremi' ?></button><a class="btn secondary" href="/contracts?status=<?= htmlspecialchars($returnStatus, ENT_QUOTES, 'UTF-8') ?>">Nazad</a></div>
        </form>
        <?php
        return (string) ob_get_clean();
    }
}
