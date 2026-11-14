<?php
/**
 * Shared helpers: escaping, flash messages (post/redirect/get), friendly DB errors.
 * Include with: require 'includes/ui.php';
 */
if (session_status() === PHP_SESSION_NONE) session_start();

if (!function_exists('h')) {
    function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

function flash_set(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function flash_get(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function post_str(string $key): string {
    return trim((string)($_POST[$key] ?? ''));
}

/** Turn a MySQL exception into something a faculty member can act on. */
function db_error_message(mysqli_sql_exception $e, string $thing = 'record'): string {
    switch ($e->getCode()) {
        case 1062: return "That $thing already exists. Use a different code or name.";
        case 1451: return "This $thing is still used by scheduled classes. Remove those classes first.";
        case 1452: return "One of the selected options no longer exists. Refresh and try again.";
        default:   return "Could not save the $thing. Please try again.";
    }
}

/* ---------- CSRF: every POST form carries a token; every POST handler calls csrf_verify() ---------- */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}
function csrf_verify(): void {
    $sent = (string)($_POST['csrf_token'] ?? '');
    if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
        flash_set('error', 'Your session expired. Please try that again.');
        redirect(basename($_SERVER['SCRIPT_NAME']));
    }
}

/** Readable temporary password (no 0/O/1/l/I lookalikes). */
function temp_password(int $len = 10): string {
    $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $len; $i++) $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    return $out;
}

/** URL of a file in /assets with a version stamp, so browsers never keep a stale copy after an update. */
function asset(string $file): string {
    $path = __DIR__ . '/../assets/' . $file;
    return '../assets/' . $file . (is_file($path) ? '?v=' . filemtime($path) : '');
}
