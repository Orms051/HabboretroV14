<?php
/**
 * HabboretroV14 — Collecteur d'erreurs JS (porté de Cadurix)
 * Autonome (N'INCLUT PAS boot.php) : reçoit les erreurs du site ET de l'admin
 * via navigator.sendBeacon, les écrit en JSONL dans _runtime/js_errors.log.
 * Lu par l'onglet admin « Journal & diagnostics ».
 */
declare(strict_types=1);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && (($_GET['test'] ?? '') === '1')) {
    echo json_encode(['ok' => true, 'test' => true]); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false]); exit; }

$raw = file_get_contents('php://input');
if ($raw === '' || $raw === false) { echo json_encode(['ok' => false, 'error' => 'empty']); exit; }
$data = json_decode($raw, true);
if (!is_array($data)) { parse_str((string)$raw, $data); }
if (!is_array($data) || !$data) { echo json_encode(['ok' => false, 'error' => 'invalid']); exit; }

$cut = fn($v, $n) => mb_substr((string)($v ?? ''), 0, $n);
$entry = [
    'time'      => date('Y-m-d H:i:s'),
    'type'      => preg_replace('/[^A-Z_]/', '', (string)($data['type'] ?? 'JS_ERROR')) ?: 'JS_ERROR',
    'message'   => $cut($data['message'] ?? '', 500),
    'source'    => $cut($data['source'] ?? '', 250),
    'line'      => (int)($data['line'] ?? 0),
    'col'       => (int)($data['col'] ?? 0),
    'stack'     => $cut($data['stack'] ?? '', 1000),
    'page_url'  => $cut($data['page_url'] ?? '', 250),
    'page_name' => $cut($data['page_name'] ?? '', 120),
    'ua'        => $cut($_SERVER['HTTP_USER_AGENT'] ?? '', 200),
    'ip'        => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
];

$dir = __DIR__ . '/_runtime';
if (!is_dir($dir)) @mkdir($dir, 0775, true);
$file = $dir . '/js_errors.log';

// Rotation simple : si le fichier dépasse 2 Mo, on ne garde que les 800 dernières lignes.
if (is_file($file) && (int)@filesize($file) > 2_000_000) {
    $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    @file_put_contents($file, implode("\n", array_slice($lines, -800)) . "\n", LOCK_EX);
}
@file_put_contents($file, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
echo json_encode(['ok' => true]);
