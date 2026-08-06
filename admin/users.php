<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();

// Create member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    verify_csrf();
    $username  = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $password  = trim($_POST['password'] ?? '') ?: generate_temp_password();

    if (!$username || !$full_name) {
        flash('error', 'Username and full name are required.');
    } else {
        $exists = db_val("SELECT COUNT(*) FROM users WHERE username=?", [$username]);
        if ($exists) {
            flash('error', 'Username already taken.');
        } else {
            db_run(
                "INSERT INTO users (username,full_name,phone,password,role,must_change_password) VALUES (?,?,?,?,?,1)",
                [$username, $full_name, $phone, password_hash($password, PASSWORD_BCRYPT), 'member']
            );
            flash('success', t('user_created') . " Temp password: $password (they'll be asked to change it on first login)");
        }
    }
    redirect('/admin/users.php');
}

$page          = current_page();
$q             = current_search();
$where         = '';
$params        = [];
if ($q !== '') {
    $where  = "AND (full_name LIKE ? OR username LIKE ? OR phone LIKE ?)";
    $like   = '%' . $q . '%';
    $params = [$like, $like, $like];
}
$total_members = (int) db_val("SELECT COUNT(*) FROM users WHERE role='member' $where", $params);
$members       = db_rows("
    SELECT * FROM users WHERE role='member' $where ORDER BY full_name
    LIMIT " . PAGE_SIZE . " OFFSET " . (($page - 1) * PAGE_SIZE),
    $params
);

page_start(t('nav_users'), 'users');
?>

<div class="py-5 space-y-5 fade-up">
  <h2 class="text-2xl font-bold text-slate-800"><?= t('all_members') ?></h2>

  <!-- Create member -->
  <div class="bg-white rounded-2xl shadow-sm overflow-hidden" x-data="{open:false}">
    <button @click="open=!open"
            class="w-full flex items-center justify-between px-4 py-3.5 text-left">
      <p class="font-semibold text-slate-700 text-sm flex items-center gap-2">
        <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        <?= t('create_member') ?>
      </p>
      <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-slate-400 transition-transform"
           fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
      </svg>
    </button>

    <div x-show="open" x-cloak>
      <form method="POST" action="" class="px-4 pb-4 pt-2 border-t border-slate-100 space-y-3">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="create">

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-slate-500 mb-1"><?= t('full_name') ?> *</label>
            <input type="text" name="full_name" required
                   class="w-full px-3 py-2.5 text-sm rounded-xl border border-slate-200
                          focus:outline-none focus:ring-2 focus:ring-primary bg-slate-50">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-500 mb-1"><?= t('username') ?> *</label>
            <input type="text" name="username" required
                   class="w-full px-3 py-2.5 text-sm rounded-xl border border-slate-200
                          focus:outline-none focus:ring-2 focus:ring-primary bg-slate-50"
                   placeholder="lowercase_name">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-500 mb-1"><?= t('phone') ?></label>
            <input type="tel" name="phone"
                   class="w-full px-3 py-2.5 text-sm rounded-xl border border-slate-200
                          focus:outline-none focus:ring-2 focus:ring-primary bg-slate-50">
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-500 mb-1"><?= t('password') ?></label>
            <input type="text" name="password"
                   placeholder="Leave blank to auto-generate"
                   class="w-full px-3 py-2.5 text-sm rounded-xl border border-slate-200
                          focus:outline-none focus:ring-2 focus:ring-primary bg-slate-50">
          </div>
        </div>

        <button type="submit"
                class="bg-primary text-white font-semibold text-sm px-5 py-2.5 rounded-xl
                       hover:bg-primary-dark transition active:scale-95">
          <?= t('create_member') ?>
        </button>
      </form>
    </div>
  </div>

  <?php render_search_box($q, 'Search by name, username, or phone...'); ?>

  <!-- Members list -->
  <div class="space-y-3">
    <?php foreach ($members as $m): ?>
    <?php $mb = user_balance((int)$m['id']); ?>
    <div class="bg-white rounded-2xl shadow-sm p-4" id="user-<?= $m['id'] ?>">
      <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center
                    flex-shrink-0 text-primary font-bold text-lg">
          <?= strtoupper(substr($m['full_name'], 0, 1)) ?>
        </div>
        <div class="flex-1 min-w-0">
          <p class="font-bold text-slate-800"><?= htmlspecialchars($m['full_name']) ?></p>
          <p class="text-xs text-slate-400">@<?= htmlspecialchars($m['username']) ?>
            <?php if ($m['phone']): ?> · <?= htmlspecialchars($m['phone']) ?><?php endif; ?>
          </p>
          <p class="text-xs text-slate-500 mt-0.5">
            <?= t('member_since') ?> <?= date('M Y', strtotime($m['created_at'])) ?>
          </p>
        </div>
        <div class="text-right flex-shrink-0">
          <p class="font-bold <?= $mb >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
            <?= fmt_money($mb) ?>
          </p>
          <span class="text-xs <?= $m['is_active'] ? 'text-emerald-500' : 'text-red-400' ?> font-medium">
            <?= $m['is_active'] ? t('active') : t('inactive') ?>
          </span>
        </div>
      </div>

      <!-- Actions -->
      <div class="mt-3 flex gap-2 flex-wrap"
           x-data="userAction(<?= $m['id'] ?>, '<?= csrf_token() ?>')">
        <button @click="toggle()"
                class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600
                       font-semibold px-3 py-1.5 rounded-lg transition">
          <?= $m['is_active'] ? 'Deactivate' : 'Activate' ?>
        </button>
        <button @click="resetPw()"
                class="text-xs bg-amber-100 hover:bg-amber-200 text-amber-700
                       font-semibold px-3 py-1.5 rounded-lg transition">
          <?= t('reset_password') ?>
        </button>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($members)): ?>
    <div class="bg-white rounded-2xl p-8 text-center text-slate-400 text-sm shadow-sm">
      <?= $q !== '' ? 'No members match your search.' : 'No members yet. Create one above!' ?>
    </div>
    <?php endif; ?>
  </div>
  <?php render_pager($page, $total_members); ?>
</div>

<script>
function userAction(id, csrf) {
  return {
    async toggle() {
      const r = await fetch('<?= APP_URL ?>/api/action.php', {
        method:'POST',
        body: new URLSearchParams({ csrf, action:'toggle_user', id })
      });
      const d = await r.json();
      if (d.ok) location.reload();
      else alert(d.error);
    },
    async resetPw() {
      if (!confirm('<?= t('confirm_action') ?>')) return;
      const r = await fetch('<?= APP_URL ?>/api/action.php', {
        method:'POST',
        body: new URLSearchParams({ csrf, action:'reset_password', id })
      });
      const d = await r.json();
      if (d.ok) alert('New temp password: ' + d.temp_password + '\n\nThey will be asked to change it on next login.');
      else alert(d.error);
    }
  }
}
</script>

<?php page_end('users'); ?>
