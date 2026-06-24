<?php
declare(strict_types=1);

namespace App\Data;

final class ExportStore
{
    public static function path(): ?string
    {
        $candidates = array_filter([
            getenv('PONTADESK_EXPORT_JSON') ?: null,
            'C:\\Users\\Stjepo\\Desktop\\fda81651f_pontadesk_backup_2026-06-24.json',
            dirname(__DIR__, 2) . '/data/pontadesk_backup.json',
        ]);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public static function data(): array
    {
        $path = self::path();
        if ($path === null) {
            return [];
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return [];
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }
}
