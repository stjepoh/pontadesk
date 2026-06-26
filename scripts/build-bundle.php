<?php
declare(strict_types=1);

$base = $argv[1] ?? 'C:/Users/Stjepo/Downloads/Base44 ponta podaci';
$out = $argv[2] ?? dirname(__DIR__) . '/data/base44-bundle.json';

$files = [
    'Client' => 'a1a70e5c8_Client.json',
    'Contract' => '2f2e9f571_Contract.json',
    'WorkLog' => '3a503637b_WorkLog.json',
    'Project' => '4987e4d5c_Project.json',
    'ClientNote' => '0d3c5e54f_ClientNote.json',
    'ClientTask' => '4dfad9eab_ClientTask.json',
    'NotificationSettings' => '0a676b195_NotificationSettings.json',
];

$bundle = [];
foreach ($files as $key => $file) {
    $path = rtrim($base, "\\/") . DIRECTORY_SEPARATOR . $file;
    if (!is_file($path)) {
        fwrite(STDERR, "Missing file: {$path}\n");
        exit(1);
    }

    $json = file_get_contents($path);
    if ($json === false) {
        fwrite(STDERR, "Cannot read: {$path}\n");
        exit(1);
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "Invalid JSON: {$path}\n");
        exit(1);
    }

    $bundle[$key] = $decoded;
}

$encoded = json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($encoded === false) {
    fwrite(STDERR, "Failed to encode bundle\n");
    exit(1);
}

file_put_contents($out, $encoded . PHP_EOL);
echo $out . PHP_EOL;
