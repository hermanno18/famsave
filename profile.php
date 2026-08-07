<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'profile') {
    verify_csrf();
    $name  = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    if ($name) {
        db_run("UPDATE users SET full_name=?, phone=? WHERE id=?",
               [$name, $phone, $user['id']]);
        flash('success', t('profile_saved'));
    }
    redirect('/profile.php');
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'password') {
    verify_csrf();
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    if (!password_verify($current, $user['password'])) {
        flash('error', t('wrong_password'));
    } elseif (strlen($new) < 8) {
        flash('error', 'New password must be at least 8 characters.');
    } else {
        db_run("UPDATE users SET password=?, must_change_password=0 WHERE id=?",
               [password_hash($new, PASSWORD_BCRYPT), $user['id']]);
        flash('success', t('password_changed'));
    }
    redirect('/profile.php');
}

// Handle personal savings goal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_personal_goal') {
    verify_csrf();
    $title  = trim($_POST['goal_title'] ?? '');
    $target = (int) ($_POST['goal_target'] ?? 0);

    if ($title === '' || $target <= 0) {
        flash('error', 'Goal needs a title and a target amount above zero.');
    } else {
        set_savings_goal($title, $target, $user['id'], $user['id']);
        flash('success', 'Your savings goal is set!');
    }
    redirect('/profile.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_personal_goal') {
    verify_csrf();
    clear_savings_goal($user['id']);
    flash('success', 'Your savings goal was cleared.');
    redirect('/profile.php');
}

// All transactions for this user
$txs = db_rows(
    "SELECT * FROM transactions WHERE user_id=? ORDER BY id DESC",
    [$user['id']]
);
$balance    = user_balance((int)$user['id']);
$my_goal    = active_goal_for_user((int)$user['id']);

page_start(t('my_profile'), 'profile');
?>

<div class="py-5 space-y-5 fade-up">

  <!-- Profile header -->
  <div class="bg-gradient-to-br from-primary to-primary-dark rounded-3xl p-5 text-white flex items-center gap-4">
    <div class="w-16 h-16 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0 text-2xl font-bold">
      <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
    </div>
    <div>
      <p class="font-bold text-lg"><?= htmlspecialchars($user['full_name']) ?></p>
      <p class="text-teal-100 text-sm">@<?= htmlspecialchars($user['username']) ?></p>
      <p class="text-teal-200 text-xs mt-0.5"><?= t('member_since') ?> <?= date('M Y', strtotime($user['created_at'])) ?></p>
    </div>
  </div>

  <!-- Edit profile form -->
  <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100">
      <p class="font-semibold text-slate-700 text-sm"><?= t('my_profile') ?></p>
    </div>
    <form method="POST" action="" class="p-4 space-y-4">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="profile">

      <div>
        <label class="block text-xs font-semibold text-slate-500 mb-1"><?= t('full_name') ?></label>
        <input type="text" name="full_name" required
               value="<?= htmlspecialchars($user['full_name']) ?>"
               class="w-full px-4 py-2.5 rounded-xl border border-slate-200
                      focus:outline-none focus:ring-2 focus:ring-primary text-slate-800 bg-slate-50">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-500 mb-1"><?= t('phone') ?></label>
        <input type="tel" name="phone"
               value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
               class="w-full px-4 py-2.5 rounded-xl border border-slate-200
                      focus:outline-none focus:ring-2 focus:ring-primary text-slate-800 bg-slate-50"
               placeholder="+237 6xx xxx xxx">
      </div>
      <button type="submit"
              class="bg-primary text-white font-semibold text-sm px-5 py-2.5 rounded-xl
                     hover:bg-primary-dark transition active:scale-95">
        <?= t('save') ?>
      </button>
    </form>
  </div>

  <!-- My savings goal -->
  <div class="bg-white rounded-2xl shadow-sm p-5">
    <p class="font-semibold text-slate-700 text-sm mb-4">
      <?= $my_goal ? 'My Savings Goal' : 'Set a Personal Savings Goal' ?>
    </p>
    <?php if ($my_goal): ?>
    <?php render_goal_progress($my_goal, $balance); ?>
    <?php endif; ?>
    <form method="POST" action="" class="space-y-3 <?= $my_goal ? 'mt-4' : '' ?>">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="set_personal_goal">
      <div>
        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Goal Title</label>
        <input type="text" name="goal_title" required maxlength="80"
               value="<?= $my_goal ? htmlspecialchars($my_goal['title']) : '' ?>"
               placeholder="e.g. New Bike"
               class="w-full px-4 py-2.5 rounded-xl border border-slate-200
                      focus:outline-none focus:ring-2 focus:ring-primary text-sm">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Target Amount (<?= CURRENCY ?>)</label>
        <input type="number" name="goal_target" min="1" required
               value="<?= $my_goal ? (int)$my_goal['target_amount'] : '' ?>"
               class="w-full px-4 py-2.5 rounded-xl border border-slate-200
                      focus:outline-none focus:ring-2 focus:ring-primary text-sm font-semibold">
      </div>
      <button type="submit"
              class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-2.5 rounded-xl
                     transition active:scale-95 text-sm">
        <?= $my_goal ? 'Update Goal' : 'Set Goal' ?>
      </button>
    </form>
    <?php if ($my_goal): ?>
    <form method="POST" action="" class="mt-2">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="clear_personal_goal">
      <button type="submit"
              class="w-full bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold py-2.5 rounded-xl
                     transition active:scale-95 text-sm">
        Clear Goal
      </button>
    </form>
    <?php endif; ?>
  </div>

  <!-- Change password -->
  <div class="bg-white rounded-2xl shadow-sm overflow-hidden" x-data="{open:false}">
    <button @click="open=!open"
            class="w-full flex items-center justify-between px-4 py-3.5 text-left">
      <p class="font-semibold text-slate-700 text-sm"><?= t('change_password') ?></p>
      <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-slate-400 transition-transform"
           fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
      </svg>
    </button>
    <div x-show="open" x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100">
      <form method="POST" action="" class="px-4 pb-4 space-y-3 border-t border-slate-100 pt-3">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="password">
        <div>
          <label class="block text-xs font-semibold text-slate-500 mb-1"><?= t('current_password') ?></label>
          <input type="password" name="current_password" required
                 class="w-full px-4 py-2.5 rounded-xl border border-slate-200
                        focus:outline-none focus:ring-2 focus:ring-primary text-slate-800 bg-slate-50">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 mb-1"><?= t('new_password') ?></label>
          <input type="password" name="new_password" required minlength="8"
                 class="w-full px-4 py-2.5 rounded-xl border border-slate-200
                        focus:outline-none focus:ring-2 focus:ring-primary text-slate-800 bg-slate-50">
        </div>
        <button type="submit"
                class="bg-slate-800 text-white font-semibold text-sm px-5 py-2.5 rounded-xl
                       hover:bg-slate-700 transition active:scale-95">
          <?= t('change_password') ?>
        </button>
      </form>
    </div>
  </div>

  <!-- Full transaction history -->
  <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
      <p class="font-semibold text-slate-700 text-sm">Transaction History</p>
      <span class="text-xs bg-primary/10 text-primary font-bold px-2 py-0.5 rounded-full">
        <?= fmt_money($balance) ?>
      </span>
    </div>
    <?php if (empty($txs)): ?>
    <div class="px-4 py-8 text-center text-slate-400 text-sm"><?= t('no_transactions') ?></div>
    <?php else: ?>
    <ul class="divide-y divide-slate-100">
      <?php foreach ($txs as $tx): ?>
      <li class="px-4 py-3 flex items-center gap-3">
        <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0
                    <?= $tx['type']==='deposit' ? 'bg-emerald-100' : 'bg-red-100' ?>">
          <svg class="w-4 h-4 <?= $tx['type']==='deposit' ? 'text-emerald-600' : 'text-red-500' ?>"
               fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <?php if ($tx['type']==='deposit'): ?>
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            <?php else: ?>
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
            <?php endif; ?>
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-slate-800">
            <?= $tx['type']==='deposit' ? t('type_deposit') : t('type_withdrawal') ?>
          </p>
          <p class="text-xs text-slate-400"><?= fmt_date($tx['created_at']) ?></p>
          <?php if ($tx['admin_note']): ?>
          <p class="text-xs text-slate-500 italic mt-0.5">"<?= htmlspecialchars($tx['admin_note']) ?>"</p>
          <?php endif; ?>
        </div>
        <div class="text-right flex-shrink-0">
          <p class="text-sm font-bold <?= $tx['type']==='deposit' ? 'text-emerald-600' : 'text-red-500' ?>">
            <?= $tx['type']==='deposit' ? '+' : '−' ?><?= fmt_money((int)$tx['amount']) ?>
          </p>
          <?= fmt_status_badge($tx['status']) ?>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>

  <!-- Logout -->
  <a href="<?= APP_URL ?>/logout.php"
     class="block w-full text-center bg-slate-100 hover:bg-red-50 hover:text-red-600
            text-slate-600 font-semibold py-3.5 rounded-2xl transition text-sm">
    <?= t('logout') ?>
  </a>
</div>

<?php page_end('profile'); ?>
