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
            status        TEXT    NOT NULL DEFAULT 'active' CHECK(status IN ('active','completed')),
            completed_at  TEXT,
            is_pinned     INTEGER NOT NULL DEFAULT 0,
            user_id       INTEGER REFERENCES users(id),
            created_by    INTEGER NOT NULL REFERENCES users(id),
            created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
        );

        -- Which family (admin-owned) goals an admin has chosen to surface on a given member's dashboard.
        CREATE TABLE IF NOT EXISTS goal_pins (
            goal_id    INTEGER NOT NULL REFERENCES savings_goals(id),
            user_id    INTEGER NOT NULL REFERENCES users(id),
            created_at TEXT    NOT NULL DEFAULT (datetime('now')),
            PRIMARY KEY (goal_id, user_id)
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

    $goal_cols = array_column($db->query("PRAGMA table_info(savings_goals)")->fetchAll(), 'name');
    if (!in_array('user_id', $goal_cols, true)) {
        $db->exec("ALTER TABLE savings_goals ADD COLUMN user_id INTEGER REFERENCES users(id)");
    }
    if (!in_array('status', $goal_cols, true)) {
        $db->exec("ALTER TABLE savings_goals ADD COLUMN status TEXT NOT NULL DEFAULT 'active'");
    }
    if (!in_array('completed_at', $goal_cols, true)) {
        $db->exec("ALTER TABLE savings_goals ADD COLUMN completed_at TEXT");
    }
    if (!in_array('is_pinned', $goal_cols, true)) {
        $db->exec("ALTER TABLE savings_goals ADD COLUMN is_pinned INTEGER NOT NULL DEFAULT 0");
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS goal_pins (
            goal_id    INTEGER NOT NULL REFERENCES savings_goals(id),
            user_id    INTEGER NOT NULL REFERENCES users(id),
            created_at TEXT    NOT NULL DEFAULT (datetime('now')),
            PRIMARY KEY (goal_id, user_id)
        )
    ");
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

/**
 * ── Savings goals (multi-goal) ──────────────────────────────────────────────
 *
 * A goal is either PERSONAL (user_id = a member's id) or FAMILY-WIDE
 * (user_id IS NULL, admin-owned). Each user/scope can have many goals at
 * once. Goals move from 'active' -> 'completed' (kept forever for history)
 * or get soft-deleted (is_active=0, disappears everywhere).
 */

/** Creates a new active goal. $for_user = null means a family-wide (admin) goal. */
function create_goal(string $title, int $target_amount, int $created_by, ?int $for_user = null): int {
    db_run("INSERT INTO savings_goals (title,target_amount,created_by,user_id) VALUES (?,?,?,?)",
           [$title, $target_amount, $created_by, $for_user]);
    return (int) get_db()->lastInsertId();
}

/** Fetches a single non-deleted goal by id, or null. */
function get_goal(int $goal_id): ?array {
    return db_row("SELECT * FROM savings_goals WHERE id=? AND is_active=1", [$goal_id]);
}

/** All active (in-progress) goals in a scope, oldest first. Family-wide when $for_user is null. */
function list_active_goals(?int $for_user): array {
    if ($for_user === null) {
        return db_rows("SELECT * FROM savings_goals WHERE is_active=1 AND status='active' AND user_id IS NULL ORDER BY id");
    }
    return db_rows("SELECT * FROM savings_goals WHERE is_active=1 AND status='active' AND user_id=? ORDER BY id", [$for_user]);
}

/** All completed goals in a scope, most recently completed first — the "history". */
function list_completed_goals(?int $for_user): array {
    if ($for_user === null) {
        return db_rows("SELECT * FROM savings_goals WHERE is_active=1 AND status='completed' AND user_id IS NULL ORDER BY completed_at DESC");
    }
    return db_rows("SELECT * FROM savings_goals WHERE is_active=1 AND status='completed' AND user_id=? ORDER BY completed_at DESC", [$for_user]);
}

/**
 * Picks which goals should be "featured" (shown on the dashboard) out of a list of active goals:
 * the single goal closest to completion (smallest remaining amount against $current_amount),
 * plus any goal the owner has explicitly pinned. Deduplicated, closest-first.
 */
function featured_goals(array $active_goals, int $current_amount): array {
    if (empty($active_goals)) return [];

    $remaining = function (array $g) use ($current_amount): int {
        return max(0, (int)$g['target_amount'] - $current_amount);
    };
    usort($active_goals, function ($a, $b) use ($remaining) {
        $cmp = $remaining($a) <=> $remaining($b);
        if ($cmp !== 0) return $cmp;
        $cmp = (int)$a['target_amount'] <=> (int)$b['target_amount'];
        if ($cmp !== 0) return $cmp;
        return (int)$a['id'] <=> (int)$b['id'];
    });

    $featured = [$active_goals[0]];
    foreach ($active_goals as $g) {
        if ((int)$g['id'] === (int)$featured[0]['id']) continue;
        if ((int)$g['is_pinned'] === 1) $featured[] = $g;
    }
    return $featured;
}

/** Family goals an admin has pinned to a specific member's dashboard. */
function pinned_family_goals_for_user(int $user_id): array {
    return db_rows("
        SELECT g.* FROM savings_goals g
        JOIN goal_pins p ON p.goal_id = g.id
        WHERE g.is_active=1 AND g.status='active' AND g.user_id IS NULL AND p.user_id=?
        ORDER BY g.id
    ", [$user_id]);
}

/** Toggles whether $goal_id is pinned (owner's own dashboard, for personal goals). */
function toggle_goal_pin(int $goal_id): void {
    db_run("UPDATE savings_goals SET is_pinned = 1 - is_pinned WHERE id=?", [$goal_id]);
}

/** Member ids a family goal is currently pinned to, for the admin's checkbox UI. */
function goal_pin_member_ids(int $goal_id): array {
    return array_map('intval', array_column(
        db_rows("SELECT user_id FROM goal_pins WHERE goal_id=?", [$goal_id]), 'user_id'
    ));
}

/** Admin sets the exact list of member ids a family goal is pinned to. */
function set_goal_pins(int $goal_id, array $member_ids): void {
    db_run("DELETE FROM goal_pins WHERE goal_id=?", [$goal_id]);
    foreach (array_unique(array_map('intval', $member_ids)) as $uid) {
        db_run("INSERT INTO goal_pins (goal_id, user_id) VALUES (?,?)", [$goal_id, $uid]);
    }
}

/** Marks a goal completed — moves it into history, stays there forever (unless deleted). */
function complete_goal(int $goal_id): void {
    db_run("UPDATE savings_goals SET status='completed', completed_at=datetime('now') WHERE id=?", [$goal_id]);
}

/** Re-opens a completed goal back to active (in case it was marked done by mistake). */
function reopen_goal(int $goal_id): void {
    db_run("UPDATE savings_goals SET status='active', completed_at=NULL WHERE id=?", [$goal_id]);
}

/** Soft-deletes a goal (and its pins) so it disappears everywhere, including history. */
function delete_goal(int $goal_id): void {
    db_run("UPDATE savings_goals SET is_active=0 WHERE id=?", [$goal_id]);
    db_run("DELETE FROM goal_pins WHERE goal_id=?", [$goal_id]);
}

/** Progress percentage toward a goal, capped 0-100. */
function goal_percent(array $goal, int $current_amount): int {
    $target = (int) $goal['target_amount'];
    return $target > 0 ? min(100, max(0, (int) round($current_amount / $target * 100))) : 0;
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