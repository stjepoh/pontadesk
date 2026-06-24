<?php
declare(strict_types=1);

namespace App\Data;

final class ImportMap
{
    public static function path(): string
    {
        return dirname(__DIR__, 2) . '/data/import-map.json';
    }

    public static function load(): array
    {
        $path = self::path();
        if (!is_file($path)) {
            return [];
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return [];
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    public static function clientLabel(string $id, ?string $fallback = null): string
    {
        $map = self::load();
        if (isset($map['clients'][$id]) && is_string($map['clients'][$id])) {
            return $map['clients'][$id];
        }
        return $fallback ?? $id;
    }
}
