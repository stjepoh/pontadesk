<?php
declare(strict_types=1);

namespace App\Http\Controllers;

final class PlaceholderController extends AdminController
{
    public function reports(): void
    {
        $this->render('Izvještaji', 'reports', 'U pripremi. Ovdje ćemo prikazati sažetke rada, naplate i ugovora.');
    }

    public function tasks(): void
    {
        $this->render('To-Do', 'tasks', 'U pripremi. Ovdje dolazi lista zadataka po klijentima.');
    }

    public function notes(): void
    {
        $this->render('Specifič.', 'notes', 'U pripremi. Ovdje dolaze bilješke i specifičnosti klijenata.');
    }

    public function notifications(): void
    {
        $this->render('Notif.', 'notifications', 'U pripremi. Ovdje dolaze notifikacije i korisničke postavke.');
    }

    private function render(string $title, string $active, string $message): void
    {
        $this->requireAdmin();
        $content = '<section class="panel pad"><div class="muted">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div></section>';
        $this->renderPage($title, 'Ova sekcija još se razvija.', $content, $active);
    }
}
