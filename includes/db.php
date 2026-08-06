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
            created_at  TEXT    NOT NULL DEFAULT (datetime('now'))
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
    ");

    // Seed admin user if none exists
    $count = $db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
    if ((int)$count === 0) {
        $hash = password_hash('Admin1234!', PASSWORD_BCRYPT);
        $db->prepare("INSERT INTO users (username,full_name,password,role) VALUES (?,?,?,?)")
           ->execute(['admin', 'Administrator', $hash, 'admin']);
    }
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
