<?php
/**
 * Database — SQLite3 via PDO. Creates schema on first run.
 */

function get_db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    if (!is_dir(DATA_PATH)) mkdir(DATA_PATH, 0755, true);
    $file = DATA_PATH . '/famsave.db';

    $pdo = new PDO('sqlite:' . $file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');

    _create_schema($pdo);
    _migrate_schema($pdo);
    return $pdo;
}

function _create_schema(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            username    TEXT    NOT NULL UNIQUE,
            full_name   TEXT    NOT NULL,
            phone       TEXT,
            password    TEXT    NOT NULL,
            role        TEXT    NOT NULL DEFAULT 'member',
            is_active   INTEGER NOT NULL DEFAULT 1,
            must_change_password INTEGER NOT NULL DEFAULT 0,
            created_at  TEXT    NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS login_attempts (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            username     TEXT    NOT NULL,
            ip           TEXT,
            created_at   TEXT    NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS transactions (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id      INTEGER NOT NULL REFERENCES users(id),
            type         TEXT    NOT NULL CHECK(type IN ('deposit','withdrawal')),
            amount       INTEGER NOT NULL CHECK(amount > 0),
            note         TEXT,
            proof_file   TEXT,
            status       TEXT    NOT NULL DEFAULT 'pending'
                                  CHECK(status IN ('pending','approved','rejected')),
            admin_note   TEXT,
            reviewed_by  INTEGER REFERENCES users(id),
            reviewed_at  TEXT,
            created_at   TEXT    NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS balance_logs (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            amount       INTEGER NOT NULL,
            note         TEXT,
            proof_file   TEXT,
            logged_by    INTEGER NOT NULL REFERENCES users(id),
            created_at   TEXT    NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS notifications (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id      INTEGER NOT NULL REFERENCES users(id),
            message      TEXT    NOT NULL,
            link         TEXT,
            is_read      INTEGER NOT NULL DEFAULT 0,
            created_at   TEXT    NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS savings_goals (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            title         TEXT    NOT NULL,
            target_amount INTEGER NOT NULL CHECK(target_amount > 0),
            is_active     INTEGER NOT NULL DEFAULT 1,
            created_by    INTEGER NOT NULL REFERENCES users(id),
            created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
        );
    ");

    // Seed admin user if none exists
    $count = $db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
    if ((int)$count === 0) {
        $hash = password_hash('Admin1234!', PASSWORD_BCRYPT);
        $db->prepare("INSERT INTO users (username,full_name,password,role) VALUES (?,?,?,?)")
           ->execute(['admin', 'Administrator', $hash, 'admin']);
    }
}

/** Adds columns to pre-existing DBs that predate a schema change (SQLite has no IF NOT EXISTS on ALTER). */
function _migrate_schema(PDO $db): void {
    $cols = array_column($db->query("PRAGMA table_info(users)")->fetchAll(), 'name');
    if (!in_array('must_change_password', $cols, true)) {
        $db->exec("ALTER TABLE users ADD COLUMN must_change_password INTEGER NOT NULL DEFAULT 0");
    }
}

// ── Login rate limiting ──────────────────────────────────────────────────────────

const LOGIN_MAX_ATTEMPTS   = 5;
const LOGIN_LOCKOUT_SECS   = 900; // 15 minutes

/** True if this username has hit the failed-attempt ceiling within the lockout window. */
function is_login_locked(string $username): bool {
    $count = (int) db_val(
        "SELECT COUNT(*) FROM login_attempts
          WHERE username=? AND created_at > datetime('now', ?)",
        [$username, '-' . LOGIN_LOCKOUT_SECS . ' seconds']
    );
    return $count >= LOGIN_MAX_ATTEMPTS;
}

function record_failed_login(string $username): void {
    db_run("INSERT INTO login_attempts (username, ip) VALUES (?,?)",
           [$username, $_SERVER['REMOTE_ADDR'] ?? '']);
}

function clear_login_attempts(string $username): void {
    db_run("DELETE FROM login_attempts WHERE username=?", [$username]);
}

// ── Query helpers ──────────────────────────────────────────────────────────────

function db_row(string $sql, array $params = []): ?array {
    $st = get_db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row ?: null;
}

function db_rows(string $sql, array $params = []): array {
    $st = get_db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function db_run(string $sql, array $params = []): void {
    get_db()->prepare($sql)->execute($params);
}

function db_val(string $sql, array $params = []) {
    $st = get_db()->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}

// ── Business logic helpers ─────────────────────────────────────────────────────

/** Returns approved balance for a user (deposits - withdrawals). */
function user_balance(int $user_id): int {
    $dep = (int) db_val(
        "SELECT COALESCE(SUM(amount),0) FROM transactions
          WHERE user_id=? AND type='deposit' AND status='approved'", [$user_id]);
    $wit = (int) db_val(
        "SELECT COALESCE(SUM(amount),0) FROM transactions
          WHERE user_id=? AND type='withdrawal' AND status='approved'", [$user_id]);
    return $dep - $wit;
}

/** Returns the latest main account balance logged by admin. */
function main_balance(): int {
    return (int) db_val("SELECT COALESCE(amount,0) FROM balance_logs ORDER BY id DESC LIMIT 1");
}

/** Returns the current active savings goal, or null if none set. */
function active_goal(): ?array {
    return db_row("SELECT * FROM savings_goals WHERE is_active=1 ORDER BY id DESC LIMIT 1");
}

/** Deactivates any existing goal and creates a new active one. */
function set_savings_goal(string $title, int $target_amount, int $created_by): void {
    db_run("UPDATE savings_goals SET is_active=0 WHERE is_active=1");
    db_run("INSERT INTO savings_goals (title,target_amount,created_by) VALUES (?,?,?)",
           [$title, $target_amount, $created_by]);
}

/** Clears the active goal (no goal currently tracked). */
function clear_savings_goal(): void {
    db_run("UPDATE savings_goals SET is_active=0 WHERE is_active=1");
}

/** Push a notification to a user. */
function notify(int $user_id, string $message, string $link = ''): void {
    db_run("INSERT INTO notifications (user_id,message,link) VALUES (?,?,?)",
           [$user_id, $message, $link]);
}

/** Notify all active members. */
function notify_all_members(string $message, string $link = ''): void {
    $members = db_rows("SELECT id FROM users WHERE role='member' AND is_active=1");
    foreach ($members as $m) notify((int)$m['id'], $message, $link);
}
