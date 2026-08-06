<?php
/**
 * AJAX action endpoint — returns JSON for all admin & user quick-actions.
 * Also handles lang switch (GET, then redirects).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = current_user();

// ── Lang switch (GET, no CSRF needed — harmless) ──────────────────────────────
if (($_GET['action'] ?? '') === 'lang') {
    $to = $_GET['to'] ?? 'en';
    $_SESSION['lang'] = in_array($to, ['en', 'fr'], true) ? $to : 'en';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? APP_URL . '/'));
    exit;
}

// All other actions are POST + require login
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_err('Method not allowed', 405);
}
if (!$user) json_err('Unauthenticated', 401);

verify_csrf();

$action = $_POST['action'] ?? '';

// ── Mark notifications read (any logged-in user) ──────────────────────────────
if ($action === 'mark_read') {
    db_run("UPDATE notifications SET is_read=1 WHERE user_id=?", [$user['id']]);
    json_ok();
}

// ── Admin-only actions below this line ────────────────────────────────────────
if ($user['role'] !== 'admin') json_err('Forbidden', 403);

$id   = (int) ($_POST['id'] ?? 0);
$note = trim($_POST['note'] ?? '');
$type = $_POST['type'] ?? '';   // 'deposit' | 'withdrawal'

// ── Approve / Reject transaction ──────────────────────────────────────────────
if (in_array($action, ['approve', 'reject'], true) && in_array($type, ['deposit','withdrawal'], true)) {
    if (!$id) json_err('Missing id');

    $tx = db_row("SELECT * FROM transactions WHERE id=? AND type=?", [$id, $type]);
    if (!$tx) json_err('Transaction not found');
    if ($tx['status'] !== 'pending') json_err('Already reviewed');

    $status = $action === 'approve' ? 'approved' : 'rejected';

    // Extra check: don't approve a withdrawal that exceeds member balance
    if ($action === 'approve' && $type === 'withdrawal') {
        $bal = user_balance((int)$tx['user_id']);
        if ($tx['amount'] > $bal) json_err('Member has insufficient balance.');
    }

    db_run(
        "UPDATE transactions
            SET status=?, admin_note=?, reviewed_by=?, reviewed_at=datetime('now')
          WHERE id=?",
        [$status, $note, $user['id'], $id]
    );

    // Notify the member
    $amount_str = fmt_money((int)$tx['amount']);
    // Map action+type to the correct lang key
    $notif_key  = $type . '_' . ($action === 'approve' ? 'approved' : 'rejected');
    $msg        = sprintf(t($notif_key), $amount_str);
    $link       = '/profile.php';
    notify((int)$tx['user_id'], $msg, $link);

    json_ok(['status' => $status]);
}

// ── Toggle user active/inactive ───────────────────────────────────────────────
if ($action === 'toggle_user') {
    if (!$id) json_err('Missing id');
    $member = db_row("SELECT * FROM users WHERE id=? AND role='member'", [$id]);
    if (!$member) json_err('Member not found');

    db_run("UPDATE users SET is_active = 1 - is_active WHERE id=?", [$id]);
    json_ok(['is_active' => !$member['is_active']]);
}

// ── Reset member password ─────────────────────────────────────────────────────
if ($action === 'reset_password') {
    if (!$id) json_err('Missing id');
    $member = db_row("SELECT * FROM users WHERE id=? AND role='member'", [$id]);
    if (!$member) json_err('Member not found');

    $temp = 'Temp1234!';
    db_run("UPDATE users SET password=? WHERE id=?",
           [password_hash($temp, PASSWORD_BCRYPT), $id]);
    json_ok(['temp_password' => $temp]);
}

json_err('Unknown action');
