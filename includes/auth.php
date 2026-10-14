<?php
/**
 * Session auth with two roles:
 *   'admin'   -> /admin/*    full control (courses, rooms, instructors, schedules)
 *   'faculty' -> /faculty/*  limited: own schedule (read-only), own availability, own password
 * Each role can only open its own area; the wrong role is bounced to its own home.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

/** URL path of the app root, e.g. "/ee-workload-system" (works from /, /admin, /faculty, /data). */
function appBase(): string {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $dir = preg_replace('#/(admin|faculty|data)$#', '', $dir);
    return rtrim($dir, '/');
}
function appUrl(string $path = ''): string { return appBase() . '/' . ltrim($path, '/'); }

function currentRole(): ?string { return $_SESSION['role'] ?? null; }
function currentUserId(): int   { return (int)($_SESSION['user_id'] ?? 0); }
function currentUserName(): string { return (string)($_SESSION['user_name'] ?? ''); }

function homeFor(?string $role): string {
    return $role === 'admin' ? 'admin/index.php' : ($role === 'faculty' ? 'faculty/index.php' : 'login.php');
}

function loginUser(string $role, int $id, string $name, bool $mustChange = false): void {
    session_regenerate_id(true);               // prevents session fixation
    $_SESSION['role']      = $role;
    $_SESSION['user_id']   = $id;
    $_SESSION['user_name'] = $name;
    $_SESSION['must_change'] = $mustChange;
}

/** Gate a page. Not signed in -> login. Signed in as the other role -> that role's home. */
function requireRole(string $role): void {
    $cur = currentRole();
    if ($cur === $role) return;
    header('Location: ' . appUrl($cur === null ? 'login.php' : homeFor($cur)));
    exit;
}

/** Same gate for JSON endpoints: answers 401/403 instead of redirecting. */
function requireRoleApi(string $role): void {
    $cur = currentRole();
    if ($cur === $role) return;
    http_response_code($cur === null ? 401 : 403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not allowed']);
    exit;
}

function logoutUser(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
