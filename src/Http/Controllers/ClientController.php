<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use App\Repositories\ClientRepository;
use App\Repositories\ContractRepository;
use App\Repositories\WorkLogRepository;

final class ClientController extends AdminController
{
    public function index(): void
    {
        $this->requireAdmin();
        $search = trim((string) ($_GET['search'] ?? ''));
        $clients = (new ClientRepository())->all($search);
        $this->renderPage('Klijenti', 'Upravljanje šifarnikom klijenata i osnovnim podacima.', $this->indexContent($clients, $search), 'clients');
    }

    public function create(): void
    {
        $this->requireAdmin();
        $this->renderPage('Novi klijent', 'Unos novog klijenta u šifarnik.', $this->formContent(null, '/clients'), 'clients');
    }

    public function store(): void
    {
        $this->requireAdmin();
        (new ClientRepository())->create($this->fromRequest($_POST));
        $this->redirect('/clients');
    }

    public function detail(): void
    {
        $this->requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);
        $client = (new ClientRepository())->find($id);

        if ($client === null) {
            http_response_code(404);
            echo 'Klijent nije pronađen';
            return;
        }

        $contracts = array_values(array_filter((new ContractRepository())->allExportRows(), static fn(array $row): bool => (int) ($row['client_id'] ?? 0) === $id));
        $workLogs = array_values(array_filter((new WorkLogRepository())->allExportRows(), static fn(array $row): bool => (int) ($row['client_id'] ?? 0) === $id));

        $this->renderPage((string) $client['name'], 'Detaljan prikaz klijenta, ugovora i rada.', $this->detailContent($client, $contracts, $workLogs), 'clients');
    }

    public function edit(): void
    {
        $this->requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);
        $client = (new ClientRepository())->find($id);

        if ($client === null) {
            http_response_code(404);
            echo 'Klijent nije pronađen';
            return;
        }

        $this->renderPage('Uredi klijenta', 'Ažuriranje podataka postojećeg klijenta.', $this->formContent($client, '/clients/update?id=' . $id), 'clients');
    }

    public function update(): void
    {
        $this->requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);
        (new ClientRepository())->update($id, $this->fromRequest($_POST));
        $this->redirect('/clients');
    }

    public function delete(): void
    {
        $this->requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);
        if ($id > 0) {
            (new ClientRepository())->delete($id);
        }
        $this->redirect('/clients');
    }

    private function fromRequest(array $data): Client
    {
        return new Client(
            id: null,
            name: trim((string) ($data['name'] ?? '')),
            contactPerson: $this->n($data['contact_person'] ?? null),
            email: $this->n($data['email'] ?? null),
            phone: $this->n($data['phone'] ?? null),
            category: (string) ($data['category'] ?? 'regular'),
            salesStage: (string) ($data['sales_stage'] ?? 'lead'),
            industry: $this->n($data['industry'] ?? null),
            hourlyRate: $this->n($data['hourly_rate'] ?? null),
            trackWork: isset($data['track_work']),
            notes: $this->n($data['notes'] ?? null),
        );
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

    private function indexContent(array $clients, string $search): string
    {
        ob_start();
        ?>
        <section class="panel pad" style="margin-bottom:18px">
            <div class="toolbar">
                <form class="searchbar" method="get" action="/clients" style="flex:1">
                    <div class="searchwrap">
                        <span class="searchicon">⌕</span>
                        <input class="input" type="text" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Pretraži klijente...">
                    </div>
                    <button class="btn" type="submit">Traži</button>
                    <a class="btn secondary" href="/clients">Reset</a>
                </form>
                <a class="btn" href="/clients/create">+ Novi klijent</a>
            </div>
        </section>

        <section class="panel">
            <table>
                <thead>
                <tr>
                    <th>Naziv</th>
                    <th>Kategorija</th>
                    <th>Kontakt</th>
                    <th>Email</th>
                    <th>Cijena/sat</th>
                    <th>Akcije</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($clients === []): ?>
                    <tr><td colspan="6" class="muted">Još nema unesenih klijenata.</td></tr>
                <?php else: ?>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars((string) $client['name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><span class="chip <?= (($client['category'] ?? 'regular') === 'vip') ? 'green' : 'gray' ?>"><?= htmlspecialchars((string) $client['category'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars((string) ($client['contact_person'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string) ($client['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="color:#2b9c3d;font-weight:700"><?= number_format((float) ($client['hourly_rate'] ?? 0), 2, ',', '.') ?> €</td>
                            <td>
                                <div class="actions">
                                    <a class="chip" href="/clients/detail?id=<?= (int) $client['id'] ?>">Detalj</a>
                                    <a class="chip" href="/clients/edit?id=<?= (int) $client['id'] ?>">Uredi</a>
                                    <a class="chip gray" href="/clients/delete?id=<?= (int) $client['id'] ?>" onclick="return confirm('Obrisati klijenta?')">Briši</a>
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

    private function formContent(?array $client, string $action): string
    {
        $value = static fn(string $key, string $default = '') => htmlspecialchars((string) ($client[$key] ?? $default), ENT_QUOTES, 'UTF-8');
        $selected = static function (?array $client, string $key, string $option, string $default = ''): string {
            return (($client[$key] ?? $default) === $option) ? 'selected' : '';
        };
        $checked = isset($client['track_work']) ? ((int) $client['track_work'] === 1) : true;

        ob_start();
        ?>
        <form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" class="content">
            <section class="panel pad">
                <div class="grid-2">
                    <div><label>Naziv</label><input class="input" name="name" required value="<?= $value('name') ?>"></div>
                    <div><label>Kontakt osoba</label><input class="input" name="contact_person" value="<?= $value('contact_person') ?>"></div>
                    <div><label>Email</label><input class="input" name="email" type="email" value="<?= $value('email') ?>"></div>
                    <div><label>Telefon</label><input class="input" name="phone" value="<?= $value('phone') ?>"></div>
                    <div><label>Kategorija</label><select class="input" name="category"><?php foreach (['vip','regular','potential','inactive'] as $option): ?><option value="<?= $option ?>" <?= $selected($client, 'category', $option, 'regular') ?>><?= $option ?></option><?php endforeach; ?></select></div>
                    <div><label>Prodajna faza</label><select class="input" name="sales_stage"><?php foreach (['lead','qualified','proposal','negotiation','closed_won','closed_lost'] as $option): ?><option value="<?= $option ?>" <?= $selected($client, 'sales_stage', $option, 'lead') ?>><?= $option ?></option><?php endforeach; ?></select></div>
                    <div><label>Industrija</label><input class="input" name="industry" value="<?= $value('industry') ?>"></div>
                    <div><label>Satnica</label><input class="input" name="hourly_rate" inputmode="decimal" value="<?= $value('hourly_rate') ?>"></div>
                    <div class="grid-2" style="grid-column:1/-1">
                        <div><label>OIB</label><input class="input" name="vat_id" value="<?= $value('vat_id') ?>"></div>
                        <div><label>Web stranica</label><input class="input" name="website" value="<?= $value('website') ?>"></div>
                    </div>
                    <div class="grid-2" style="grid-column:1/-1">
                        <div><label>Adresa</label><input class="input" name="address" value="<?= $value('address') ?>"></div>
                        <div><label>Grad</label><input class="input" name="city" value="<?= $value('city') ?>"></div>
                    </div>
                    <div class="grid-2" style="grid-column:1/-1">
                        <div><label>Poštanski broj</label><input class="input" name="postal_code" value="<?= $value('postal_code') ?>"></div>
                        <div><label>Država</label><input class="input" name="country" value="<?= $value('country') ?>"></div>
                    </div>
                    <div style="grid-column:1/-1"><label>Bilješke</label><textarea class="input" name="notes" rows="6"><?= $value('notes') ?></textarea></div>
                    <div style="grid-column:1/-1"><label><input type="checkbox" name="track_work" <?= $checked ? 'checked' : '' ?>> Prati rad za klijenta</label></div>
                </div>
            </section>
            <div class="actions">
                <button class="btn" type="submit">Spremi</button>
                <a class="btn secondary" href="/clients">Nazad</a>
            </div>
        </form>
        <?php
        return (string) ob_get_clean();
    }

    private function detailContent(array $client, array $contracts, array $workLogs): string
    {
        ob_start();
        ?>
        <div class="grid-2">
            <section class="panel pad">
                <div class="section-title"><h2>Osnovni podaci</h2><a class="muted" href="/clients/edit?id=<?= (int) $client['id'] ?>">Uredi</a></div>
                <div class="mini-list">
                    <div class="mini-item"><div><strong><?= htmlspecialchars((string) $client['name'], ENT_QUOTES, 'UTF-8') ?></strong><div class="muted">Naziv klijenta</div></div><span class="chip"><?= htmlspecialchars((string) ($client['category'] ?? 'regular'), ENT_QUOTES, 'UTF-8') ?></span></div>
                    <div class="mini-item"><div><strong><?= htmlspecialchars((string) ($client['contact_person'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></strong><div class="muted">Kontakt osoba</div></div><span class="chip gray"><?= htmlspecialchars((string) ($client['sales_stage'] ?? 'lead'), ENT_QUOTES, 'UTF-8') ?></span></div>
                    <div class="mini-item"><div><strong><?= htmlspecialchars((string) ($client['email'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></strong><div class="muted">Email</div></div><span class="chip gray">Email</span></div>
                    <div class="mini-item"><div><strong><?= htmlspecialchars((string) ($client['phone'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></strong><div class="muted">Telefon</div></div><span class="chip gray">Telefon</span></div>
                    <div class="mini-item"><div><strong><?= htmlspecialchars((string) ($client['industry'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></strong><div class="muted">Industrija</div></div><span class="chip gray">Industrija</span></div>
                    <div class="mini-item"><div><strong><?= number_format((float) ($client['hourly_rate'] ?? 0), 2, ',', '.') ?> €</strong><div class="muted">Satnica</div></div><span class="chip green">Rate</span></div>
                </div>
                <?php if (!empty($client['notes'])): ?>
                    <div style="margin-top:16px" class="panel pad">
                        <div class="section-title"><h2>Bilješke</h2></div>
                        <div class="muted" style="white-space:pre-wrap;line-height:1.6"><?= htmlspecialchars((string) $client['notes'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                <?php endif; ?>
            </section>
            <section class="panel pad">
                <div class="section-title"><h2>Brzi sažetak</h2></div>
                <div class="grid-2">
                    <div class="panel stat"><div class="label">Ugovori</div><div class="value"><?= count($contracts) ?></div><div class="sub">vezani ugovori</div></div>
                    <div class="panel stat"><div class="label">Radni sati</div><div class="value"><?= count($workLogs) ?></div><div class="sub">evidentirani zapisi</div></div>
                </div>
                <div style="margin-top:18px" class="panel pad">
                    <div class="section-title"><h2>Brze radnje</h2></div>
                    <div class="actions">
                        <a class="btn" href="/clients/edit?id=<?= (int) $client['id'] ?>">Uredi klijenta</a>
                        <a class="btn secondary" href="/contracts/create">Novi ugovor</a>
                        <a class="btn secondary" href="/work-logs/create">Novi radni sat</a>
                    </div>
                </div>
            </section>
        </div>

        <div class="grid-2" style="margin-top:18px">
            <section class="panel pad">
                <div class="section-title"><h2>Ugovori</h2></div>
                <div class="mini-list">
                    <?php if ($contracts === []): ?>
                        <div class="mini-item"><div><strong>Nema ugovora</strong><div class="muted">Za ovog klijenta nema evidentiranih ugovora.</div></div></div>
                    <?php else: foreach ($contracts as $row): ?>
                        <div class="mini-item">
                            <div>
                                <strong><?= htmlspecialchars((string) ($row['contract_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                <div class="muted"><?= htmlspecialchars((string) ($row['start_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($row['end_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <span class="chip gray"><?= htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </section>
            <section class="panel pad">
                <div class="section-title"><h2>Radni sati</h2></div>
                <div class="mini-list">
                    <?php if ($workLogs === []): ?>
                        <div class="mini-item"><div><strong>Nema radnih sati</strong><div class="muted">Za ovog klijenta nema zapisa rada.</div></div></div>
                    <?php else: foreach ($workLogs as $row): ?>
                        <div class="mini-item">
                            <div>
                                <strong><?= htmlspecialchars((string) ($row['date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                <div class="muted"><?= htmlspecialchars((string) ($row['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <span class="chip <?= !empty($row['billed']) ? 'green' : 'gray' ?>"><?= (int) ($row['duration_minutes'] ?? 0) ?> min</span>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </section>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
