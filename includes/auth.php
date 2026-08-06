<?php
/**
 * Auth — session-based login, CSRF protection, role guards.
 */

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    return db_row("SELECT * FROM users WHERE id=? AND is_active=1", [$_SESSION['user_id']]);
}

function require_login(): array {
    $user = current_user();
    if (!$user) {
        flash('error', t('please_login'));
        redirect('/login.php');
    }
    if (!empty($user['must_change_password'])) {
        $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if (!in_array($current, ['profile.php', 'logout.php'], true)) {
            flash('error', t('must_change_password'));
            redirect('/profile.php');
        }
    }
    return $user;
}

function require_admin(): array {
    $user = require_login();
    if ($user['role'] !== 'admin') {
        flash('error', t('access_denied'));
        redirect('/dashboard.php');
    }
    return $user;
}

function login_user(string $username, string $password): bool {
    $user = db_row("SELECT * FROM users WHERE username=? AND is_active=1", [$username]);
    if (!$user || !password_verify($password, $user['password'])) {
        record_failed_login($username);
        return false;
    }

    clear_login_attempts($username);
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['csrf']    = bin2hex(random_bytes(16));
    return true;
}

function logout_user(): void {
    session_destroy();
    session_start();
    session_regenerate_id(true);
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_input(): string {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function verify_csrf(): void {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}
