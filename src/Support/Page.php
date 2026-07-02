<?php
declare(strict_types=1);

namespace App\Support;

final class Page
{
    /**
     * @param array<int, array{label:string, href:string, icon?:string, active?:bool}> $navItems
     */
    public static function render(string $title, string $subtitle, string $content, array $navItems, string $actions = '', string $pageClass = ''): void
    {
        header('Content-Type: text/html; charset=UTF-8');

        echo '<!doctype html><html lang="hr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>PontaDesk - ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
        echo '<link rel="icon" href="/favicon.ico" sizes="any">';
        echo '<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">';
        echo '<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">';
        echo '<link rel="apple-touch-icon" href="/apple-touch-icon.png">';
        echo '<link rel="manifest" href="/site.webmanifest">';
        echo '<meta name="theme-color" content="#111827">';
        echo '<meta name="mobile-web-app-capable" content="yes">';
        echo '<meta name="apple-mobile-web-app-capable" content="yes">';
        echo '<meta name="apple-mobile-web-app-title" content="PontaDesk">';
        echo '<style>
            :root{--panel:#fff;--nav:#0d1629;--nav2:#17233d;--text:#10233f;--muted:#6f7f97;--line:#dbe3ee;--blue:#3f6df6;--blue2:#5a82ff;--green:#1fae57;--red:#e74b4b;--shadow:0 10px 30px rgba(16,35,63,.08)}
            *{box-sizing:border-box}
            body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:linear-gradient(180deg,#f7f9fc 0,#eef3f9 100%);color:var(--text)}
            a{text-decoration:none;color:inherit}
            .topbar{height:46px;background:linear-gradient(180deg,var(--nav) 0,var(--nav2) 100%);color:#fff;display:flex;align-items:center;padding:0 18px;position:sticky;top:0;z-index:50;box-shadow:0 8px 24px rgba(3,8,20,.16)}
            .brand{display:flex;align-items:center;gap:8px;font-weight:800;font-size:13px}
            .brand-logo{width:28px;height:28px;border-radius:8px;object-fit:cover;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.16)}
            .brand-badge{width:18px;height:18px;border-radius:6px;background:linear-gradient(180deg,var(--blue2),var(--blue));display:grid;place-items:center;font-size:11px}
            .nav{display:flex;align-items:center;gap:6px;margin:0 auto;padding:0 18px;overflow:auto}
            .nav a{display:inline-flex;align-items:center;gap:6px;padding:7px 10px;border-radius:9px;color:rgba(255,255,255,.78);font-size:13px;font-weight:700;white-space:nowrap}
            .nav-ico{display:inline-grid;place-items:center;width:15px;font-size:14px;line-height:1;opacity:.9}
            .nav-label{display:inline}
            .nav a.active{background:linear-gradient(180deg,#4d79ff 0,#3566f1 100%);color:#fff;box-shadow:0 8px 18px rgba(65,106,238,.3)}
            .nav a:hover{background:rgba(255,255,255,.06);color:#fff}
            .top-actions{display:flex;align-items:center;gap:10px}
            .icon-btn{width:30px;height:30px;border-radius:10px;border:1px solid rgba(255,255,255,.14);display:grid;place-items:center;color:#fff;background:rgba(255,255,255,.04)}
            .page{max-width:1180px;margin:0 auto;padding:24px 22px 46px}
            .hero{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:22px}
            .hero h1{margin:0;font-size:30px;line-height:1.08;letter-spacing:-.02em}
            .hero p{margin:5px 0 0;color:var(--muted);font-size:13px}
            .hero-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
            .panel{background:var(--panel);border:1px solid var(--line);border-radius:12px;box-shadow:0 4px 16px rgba(16,35,63,.05)}
            .panel.pad{padding:18px}
            .btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;height:40px;padding:0 14px;border-radius:12px;background:linear-gradient(180deg,var(--blue2),var(--blue));color:#fff;font-weight:700;border:0}
            .btn.secondary{background:#fff;color:var(--text);border:1px solid var(--line)}
            .input,select,textarea{width:100%;border:1px solid var(--line);background:#fff;color:var(--text);border-radius:12px;padding:10px 12px;font-size:14px;outline:none;box-shadow:0 1px 2px rgba(16,35,63,.03)}
            .input:focus,select:focus,textarea:focus{border-color:#b7c9ff;box-shadow:0 0 0 4px rgba(63,109,246,.10)}
            .section-title{display:flex;justify-content:space-between;align-items:center;gap:12px;margin:0 0 16px}
            .section-title h2{margin:0;font-size:16px}
            .muted{color:var(--muted)}
            .content{display:grid;gap:18px}
            table{width:100%;border-collapse:separate;border-spacing:0}
            thead th{font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#6d7e96;text-align:left;padding:12px 12px;border-bottom:1px solid var(--line);background:#fbfcfe}
            tbody td{padding:12px 12px;border-bottom:1px solid #edf1f6;vertical-align:middle;font-size:14px}
            tbody tr:hover{background:#fbfdff}
            .actions{display:flex;gap:8px;flex-wrap:wrap}
            .chip{display:inline-flex;align-items:center;gap:6px;height:28px;padding:0 10px;border-radius:999px;background:#eef4ff;color:#3761db;font-weight:700;font-size:12px}
            .chip.green{background:#e9f9ef;color:#1a8f4a}
            .chip.gray{background:#f1f5f9;color:#5c6f86}
            .grid-2{display:grid;grid-template-columns:1.05fr .95fr;gap:18px}
            .grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
            .grid-4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}
            .stat{padding:16px}
            .stat .label{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.08em}
            .stat .value{margin-top:8px;font-size:26px;font-weight:800;letter-spacing:-.04em}
            .stat .sub{margin-top:6px;color:var(--muted);font-size:13px}
            .searchbar{display:flex;gap:10px;align-items:center}
            .searchbar .input{padding-left:42px}
            .searchwrap{position:relative;flex:1}
            .searchicon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#8a99af}
            .toolbar{display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap}
            .toplinks{display:flex;gap:10px;flex-wrap:wrap}
            .mini-list{display:grid;gap:10px}
            .mini-item{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:14px 16px;border:1px solid #edf1f6;border-radius:14px;background:#fcfdff}
            .date-field{position:relative;display:flex;align-items:center;gap:8px}
            .date-field .date-display{padding-right:48px;background:linear-gradient(180deg,#fff 0,#fbfcff 100%);font-weight:600;letter-spacing:.02em}
            .date-field .date-native{position:absolute;inset:0;opacity:0;cursor:pointer}
            .date-field .date-toggle{position:absolute;right:8px;top:50%;transform:translateY(-50%);border:0;background:#eef4ff;color:#3761db;width:32px;height:32px;border-radius:10px;cursor:pointer;box-shadow:0 1px 2px rgba(16,35,63,.06)}
            .date-field .date-toggle:hover{background:#dfe9ff}
            .date-field:hover .date-display,.date-field:focus-within .date-display{border-color:#9cb7ff;box-shadow:0 0 0 4px rgba(63,109,246,.10)}
            .date-help{font-size:12px;color:var(--muted);margin-top:6px}
            @media (max-width: 1080px){.grid-2,.grid-3,.grid-4{grid-template-columns:1fr 1fr}.hero{flex-direction:column;align-items:flex-start}}
            @media (max-width: 760px){.page{padding:16px}.grid-2,.grid-3,.grid-4{grid-template-columns:1fr}.hero{align-items:flex-start}.hero h1{font-size:28px}.topbar{padding:0 10px}.brand span:last-child{display:none}.nav{gap:4px;padding:0 8px}.nav a{padding:8px 8px}.nav-label{display:none}.nav-ico{width:auto;font-size:17px}.hero-actions{width:100%}.hero-actions .btn{width:100%}}
        </style></head><body>';

        echo '<header class="topbar">';
        echo '<div class="brand"><img class="brand-logo" src="/assets/img/ponta-app-logo.png" alt="PontaDesk"><span>PontaDesk</span></div>';
        echo '<nav class="nav">';
        foreach ($navItems as $item) {
            $active = !empty($item['active']) ? ' active' : '';
            echo '<a class="' . $active . '" href="' . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') . '">'
                . '<span class="nav-ico" aria-hidden="true">' . htmlspecialchars((string) ($item['icon'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span>'
                . '<span class="nav-label">' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</span>'
                . '</a>';
        }
        echo '</nav>';
        echo '<div class="top-actions"><a class="icon-btn" href="/logout" title="Odjava">↗</a></div>';
        echo '</header>';

        $pageClassAttr = trim($pageClass) !== '' ? ' ' . htmlspecialchars($pageClass, ENT_QUOTES, 'UTF-8') : '';
        echo '<main class="page' . $pageClassAttr . '">';
        echo '<div class="hero"><div><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1><p>' . htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') . '</p></div>';
        if ($actions !== '') {
            echo '<div class="hero-actions">' . $actions . '</div>';
        }
        echo '</div>';
        echo $content;
        echo '<script>
        document.querySelectorAll("[data-date-field]").forEach(function (field) {
            const display = field.querySelector(".date-display");
            const native = field.querySelector(".date-native");
            const button = field.querySelector(".date-toggle");
            if (!display || !native) return;

            function pad(n) { return String(n).padStart(2, "0"); }
            function formatDate(value) {
                if (!value) return "";
                const parts = value.split("-");
                if (parts.length !== 3) return value;
                return parts[2] + "/" + parts[1] + "/" + parts[0];
            }
            function parseDate(value) {
                if (!value) return "";
                const parts = value.split("/");
                if (parts.length !== 3) return "";
                const d = parts[0].padStart(2, "0");
                const m = parts[1].padStart(2, "0");
                const y = parts[2];
                return y + "-" + m + "-" + d;
            }

            display.value = native.value ? formatDate(native.value) : display.value;
            native.addEventListener("change", function () {
                display.value = native.value ? formatDate(native.value) : "";
            });
            display.addEventListener("keydown", function (e) {
                e.preventDefault();
                native.showPicker ? native.showPicker() : native.focus();
            });
            display.addEventListener("click", function () {
                native.showPicker ? native.showPicker() : native.focus();
            });
            if (button) {
                button.addEventListener("click", function () {
                    native.showPicker ? native.showPicker() : native.focus();
                });
            }
            native.addEventListener("change", function () {
                display.value = native.value ? formatDate(native.value) : "";
            });
            field.closest("form")?.addEventListener("submit", function () {
                const parsed = parseDate(display.value.trim());
                if (parsed) native.value = parsed;
            });
        });
        </script>';
        echo '</main></body></html>';
    }
}
