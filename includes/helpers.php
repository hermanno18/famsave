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

// ── Search ───────────────────────────────────────────────────────────

function current_search(): string {
    return trim($_GET['q'] ?? '');
}

/** Renders a GET search box; preserves every other query param (e.g. status filter) except 'q' and 'page'. */
function render_search_box(string $q, string $placeholder = 'Search...'): void {
    $hidden = $_GET;
    unset($hidden['q'], $hidden['page']);
    ?>
    <form method="GET" action="" class="flex gap-2">
      <?php foreach ($hidden as $k => $v): ?>
      <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
      <?php endforeach; ?>
      <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="<?= htmlspecialchars($placeholder) ?>"
             class="flex-1 px-4 py-2.5 text-sm rounded-xl border border-slate-200 bg-white
                    focus:outline-none focus:ring-2 focus:ring-primary">
      <button type="submit"
              class="bg-primary hover:bg-primary-dark text-white text-sm font-semibold px-4 py-2.5
                     rounded-xl transition active:scale-95">
        Search
      </button>
      <?php if ($q !== ''): ?>
      <a href="?<?= htmlspecialchars(http_build_query($hidden)) ?>"
         class="text-xs text-slate-400 self-center flex-shrink-0 hover:text-slate-600">Clear</a>
      <?php endif; ?>
    </form>
    <?php
}

// ── Pagination ───────────────────────────────────────────────────────────

const PAGE_SIZE = 20;

function current_page(): int {
    return max(1, (int) ($_GET['page'] ?? 1));
}

/** Renders a Prev/Next pager, preserving any other query params (e.g. status filter). */
function render_pager(int $page, int $total_items, int $page_size = PAGE_SIZE): void {
    $total_pages = (int) ceil($total_items / $page_size);
    if ($total_pages <= 1) return;
    $base = $_GET;
    $prev_href = '?' . http_build_query(['page' => $page - 1] + $base);
    $next_href = '?' . http_build_query(['page' => $page + 1] + $base);
    ?>
    <div class="flex items-center justify-between pt-1">
      <?php if ($page > 1): ?>
      <a href="<?= htmlspecialchars($prev_href) ?>"
         class="text-xs font-semibold bg-white border border-slate-200 text-slate-600
                px-4 py-2 rounded-lg hover:border-primary hover:text-primary transition">&larr; Prev</a>
      <?php else: ?>
      <span class="text-xs font-semibold text-slate-300 px-4 py-2">&larr; Prev</span>
      <?php endif; ?>

      <span class="text-xs text-slate-400">Page <?= $page ?> of <?= $total_pages ?></span>

      <?php if ($page < $total_pages): ?>
      <a href="<?= htmlspecialchars($next_href) ?>"
         class="text-xs font-semibold bg-white border border-slate-200 text-slate-600
                px-4 py-2 rounded-lg hover:border-primary hover:text-primary transition">Next &rarr;</a>
      <?php else: ?>
      <span class="text-xs font-semibold text-slate-300 px-4 py-2">Next &rarr;</span>
      <?php endif; ?>
    </div>
    <?php
}

// ── Savings goal ───────────────────────────────────────────────────────────

/** Renders a progress card for the active savings goal against $current_amount. Silent no-op if $goal is null.
 *  $label is an optional small eyebrow tag (e.g. 'My Goal' vs 'Family Goal') shown above the title. */
function render_goal_progress(?array $goal, int $current_amount, string $label = ''): void {
    if (!$goal) return;
    $target  = (int) $goal['target_amount'];
    $pct     = $target > 0 ? min(100, (int) round($current_amount / $target * 100)) : 0;
    ?>
    <div class="bg-white rounded-2xl shadow-sm p-4">
      <?php if ($label !== ''): ?>
      <p class="text-[10px] font-bold text-primary uppercase tracking-wide mb-1"><?= htmlspecialchars($label) ?></p>
      <?php endif; ?>
      <div class="flex items-center justify-between mb-2">
        <p class="font-semibold text-slate-700 text-sm"><?= htmlspecialchars($goal['title']) ?></p>
        <p class="text-xs font-bold text-primary"><?= $pct ?>%</p>
      </div>
      <div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">
        <div class="h-full bg-primary rounded-full transition-all" style="width: <?= $pct ?>%"></div>
      </div>
      <p class="text-xs text-slate-400 mt-1.5">
        <?= fmt_money($current_amount) ?> of <?= fmt_money($target) ?>
      </p>
    </div>
    <?php
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
