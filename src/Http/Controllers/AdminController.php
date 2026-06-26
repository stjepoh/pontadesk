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
}
