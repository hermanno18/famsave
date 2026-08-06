<?php
/**
 * Helpers — flash messages, redirects, formatting, file uploads.
 */

// ── Flash messages ────────────────────────────────────────────────────────────

function flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function get_flash(): ?array {
    if (!isset($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

// ── Redirects ─────────────────────────────────────────────────────────────────

function redirect(string $path): void {
    // $path is app-relative, e.g. '/login.php'
    header('Location: ' . APP_URL . $path);
    exit();
}

// ── Formatting ────────────────────────────────────────────────────────────────

function fmt_money(int $amount): string {
    return number_format($amount, 0, '.', ' ') . ' ' . CURRENCY;
}

function fmt_date(string $dt): string {
    $ts = strtotime($dt);
    return date('d M Y, H:i', $ts);
}

function fmt_status_badge(string $status): string {
    $map = [
        'pending'  => 'bg-amber-100 text-amber-800',
        'approved' => 'bg-emerald-100 text-emerald-800',
        'rejected' => 'bg-red-100 text-red-800',
    ];
    $cls   = $map[$status] ?? 'bg-gray-100 text-gray-600';
    $label = t('status_' . $status);
    return "<span class=\"inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold $cls\">$label</span>";
}

// ── Passwords ──────────────────────────────────────────────────────────────

/** Cryptographically random temp password, no ambiguous chars (0/O, 1/l/I). */
function generate_temp_password(int $length = 10): string {
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $pw = '';
    for ($i = 0; $i < $length; $i++) {
        $pw .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $pw;
}

// ── File uploads ──────────────────────────────────────────────────────────────

const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
const MAX_UPLOAD_BYTES = 5 * 1024 * 1024; // 5 MB

function handle_upload(string $field): ?string {
    if (empty($_FILES[$field]['tmp_name'])) return null;

    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(t('upload_error'));
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException(t('upload_too_large'));
    }

    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, ALLOWED_MIME, true)) {
        throw new RuntimeException(t('upload_invalid_type'));
    }

    $ext  = $mime === 'application/pdf' ? 'pdf' : 'jpg';
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = UPLOAD_PATH . '/' . $name;

    if (!is_dir(UPLOAD_PATH)) mkdir(UPLOAD_PATH, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException(t('upload_move_fail'));
    }

    return $name;
}

// ── JSON response ─────────────────────────────────────────────────────────────

function json_ok(array $data = []): void {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true] + $data);
    exit();
}

function json_err(string $msg, int $code = 400): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit();
}

// ── Unread notification count for current user ────────────────────────────────

function unread_count(int $user_id): int {
    return (int) db_val(
        "SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0", [$user_id]
    );
}
