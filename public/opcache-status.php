<?php
/**
 * Endpoint temporaneo per diagnostica OPcache.
 * DA RIMUOVERE dopo il test.
 */

// Protezione: solo se viene passato il token corretto
if (($_GET['token'] ?? '') !== 'sdb-opcache-check-2026') {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

header('Content-Type: application/json');

$status = opcache_get_status(false);
$config = opcache_get_configuration();

$result = [
    'opcache_enabled' => $status['opcache_enabled'] ?? false,
    'memory' => [
        'used_mb' => round(($status['memory_usage']['used_memory'] ?? 0) / 1024 / 1024, 1),
        'free_mb' => round(($status['memory_usage']['free_memory'] ?? 0) / 1024 / 1024, 1),
        'wasted_mb' => round(($status['memory_usage']['wasted_memory'] ?? 0) / 1024 / 1024, 1),
        'wasted_pct' => round($status['memory_usage']['current_wasted_percentage'] ?? 0, 1),
    ],
    'interned_strings' => [
        'used_mb' => round(($status['interned_strings_usage']['used_memory'] ?? 0) / 1024 / 1024, 1),
        'free_mb' => round(($status['interned_strings_usage']['free_memory'] ?? 0) / 1024 / 1024, 1),
        'buffer_size_mb' => round(($status['interned_strings_usage']['buffer_size'] ?? 0) / 1024 / 1024, 1),
    ],
    'statistics' => [
        'cached_scripts' => $status['opcache_statistics']['num_cached_scripts'] ?? 0,
        'max_cached_keys' => $status['opcache_statistics']['max_cached_keys'] ?? 0,
        'hits' => $status['opcache_statistics']['hits'] ?? 0,
        'misses' => $status['opcache_statistics']['misses'] ?? 0,
        'hit_rate' => round($status['opcache_statistics']['opcache_hit_rate'] ?? 0, 2),
    ],
    'jit' => [
        'enabled' => $status['jit']['enabled'] ?? false,
        'on' => $status['jit']['on'] ?? false,
        'kind' => $status['jit']['kind'] ?? 0,
        'buffer_size_mb' => round(($status['jit']['buffer_size'] ?? 0) / 1024 / 1024, 1),
        'buffer_free_mb' => round(($status['jit']['buffer_free'] ?? 0) / 1024 / 1024, 1),
    ],
    'config' => [
        'memory_consumption' => $config['directives']['opcache.memory_consumption'] ?? null,
        'max_accelerated_files' => $config['directives']['opcache.max_accelerated_files'] ?? null,
        'jit' => $config['directives']['opcache.jit'] ?? null,
        'validate_timestamps' => $config['directives']['opcache.validate_timestamps'] ?? null,
    ],
    'php_memory' => [
        'memory_limit' => ini_get('memory_limit'),
        'peak_usage_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 1),
        'current_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 1),
    ],
];

echo json_encode($result, JSON_PRETTY_PRINT);
