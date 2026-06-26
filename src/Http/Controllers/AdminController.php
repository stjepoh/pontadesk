<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuthService;
use App\Support\Page;

abstract class AdminController
{
    protected function requireAdmin(): void
    {
        $auth = new AuthService();
        if (!$auth->check()) {
            header('Location: /login');
            exit;
        }

        if (!$auth->isAdmin()) {
            http_response_code(403);
            echo 'Pristup odbijen';
            exit;
        }
    }

    /**
     * @return array<int, array{label:string, href:string, active?:bool}>
     */
    protected function nav(string $active): array
    {
        $items = [
            ['label' => 'Početna', 'href' => '/', 'key' => 'home'],
            ['label' => 'Klijenti', 'href' => '/clients', 'key' => 'clients'],
            ['label' => 'Ugovori', 'href' => '/contracts', 'key' => 'contracts'],
            ['label' => 'Radovi', 'href' => '/work-logs', 'key' => 'work'],
            ['label' => 'Izvještaji', 'href' => '/reports', 'key' => 'reports'],
            ['label' => 'To-Do', 'href' => '/tasks', 'key' => 'tasks'],
            ['label' => 'Specifič.', 'href' => '/notes', 'key' => 'notes'],
            ['label' => 'Notif.', 'href' => '/notifications', 'key' => 'notifications'],
        ];

        return array_map(static function (array $item) use ($active): array {
            $item['active'] = ($item['key'] === $active);
            unset($item['key']);
            return $item;
        }, $items);
    }

    protected function renderPage(string $title, string $subtitle, string $content, string $activeNav): void
    {
        Page::render($title, $subtitle, $content, $this->nav($activeNav));
    }

    protected function formatDate(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $formats = ['Y-m-d', 'Y-m-d H:i:s', 'd/m/Y', DATE_ATOM];
        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof \DateTimeImmutable) {
                return $date->format('d/m/Y');
            }
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return date('d/m/Y', $timestamp);
        }

        return $value;
    }

    protected function dateField(string $name, ?string $value, bool $required = false): string
    {
        $isoValue = $this->normalizeDate($value ?? '');
        $displayValue = $isoValue !== '' ? $this->formatDate($isoValue) : '';
        $requiredAttr = $required ? ' required' : '';
        $id = 'date-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $name) . '-' . substr(md5($name), 0, 6);

        return '<div class="date-field" data-date-field>'
            . '<input type="text" class="input date-display" value="' . htmlspecialchars($displayValue, ENT_QUOTES, 'UTF-8') . '" placeholder="dd/mm/yyyy" inputmode="numeric" readonly>'
            . '<input type="date" class="date-native" id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($isoValue, ENT_QUOTES, 'UTF-8') . '"' . $requiredAttr . '>'
            . '<button type="button" class="date-toggle" aria-label="Otvori kalendar">📅</button>'
            . '</div>';
    }

    protected function normalizeDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        foreach (['Y-m-d', 'd/m/Y', 'd/m/Y H:i:s', DATE_ATOM] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof \DateTimeImmutable) {
                return $date->format('Y-m-d');
            }
        }

        $timestamp = strtotime($value);
        return $timestamp !== false ? date('Y-m-d', $timestamp) : $value;
    }
}
