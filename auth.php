<?php
/**
 * Small session-based auth gate. Single role: 'faculty'.
 * Swap for real authentication (hashed passwords, DB-backed users) later.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

function currentRole(): ?string {
    return $_SESSION['role'] ?? null;
}

function requireRole(string $role): void {
    if (currentRole() !== $role) {
        header('Location: login.php');
        exit;
    }
}

function logoutUser(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
