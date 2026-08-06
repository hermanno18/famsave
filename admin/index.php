<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();

$main_bal    = main_balance();
$total_user  = (int) db_val(
    "SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='deposit' AND status='approved'"
) - (int) db_val(
    "SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='withdrawal' AND status='approved'"
);
$pending_dep = (int) db_val("SELECT COUNT(*) FROM transactions WHERE type='deposit'    AND status='pending'");
$pending_wit = (int) db_val("SELECT COUNT(*) FROM transactions WHERE type='withdrawal' AND status='pending'");
$members     = db_rows("SELECT * FROM users WHERE role='member' ORDER BY full_name");

page_start('Admin Dashboard', 'admin');
?>

<div class="py-5 space-y-5 fade-up">

  <h2 class="text-2xl font-bold text-slate-800">Dashboard</h2>

  <!-- Stats grid -->
  <div class="grid grid-cols-2 gap-3">
    <div class="bg-gradient-to-br from-primary to-primary-dark rounded-2xl p-4 text-white col-span-2">
      <p class="text-teal-100 text-xs font-medium mb-1"><?= t('main_balance') ?></p>
      <p class="text-3xl font-extrabold" data-countup="<?= $main_bal ?>"><?= fmt_money($main_bal) ?></p>
      <a href="<?= APP_URL ?>/admin/balance.php"
         class="inline-block mt-3 text-xs bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-full transition">
        <?= t('update_balance') ?> →
      </a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-4">
      <p class="text-xs text-slate-500 mb-1">Total Saved (all members)</p>
      <p class="text-xl font-bold text-slate-800"><?= fmt_money($total_user) ?></p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-4">
      <p class="text-xs text-slate-500 mb-1">Active Members</p>
      <p class="text-xl font-bold text-slate-800"><?= count($members) ?></p>
    </div>

    <a href="<?= APP_URL ?>/admin/deposits.php"
       class="bg-white rounded-2xl shadow-sm p-4 flex flex-col
              <?= $pending_dep > 0 ? 'ring-2 ring-amber-400' : '' ?>">
      <p class="text-xs text-slate-500 mb-1"><?= t('pending_deposits') ?></p>
      <p class="text-2xl font-bold <?= $pending_dep > 0 ? 'text-amber-600' : 'text-slate-800' ?>">
        <?= $pending_dep ?>
      </p>
      <?php if ($pending_dep > 0): ?>
      <span class="mt-1 text-xs text-amber-600 font-semibold">Needs review →</span>
      <?php endif; ?>
    </a>

    <a href="<?= APP_URL ?>/admin/withdrawals.php"
       class="bg-white rounded-2xl shadow-sm p-4 flex flex-col
              <?= $pending_wit > 0 ? 'ring-2 ring-red-400' : '' ?>">
      <p class="text-xs text-slate-500 mb-1"><?= t('pending_withdrawals') ?></p>
      <p class="text-2xl font-bold <?= $pending_wit > 0 ? 'text-red-600' : 'text-slate-800' ?>">
        <?= $pending_wit ?>
      </p>
      <?php if ($pending_wit > 0): ?>
      <span class="mt-1 text-xs text-red-500 font-semibold">Needs review →</span>
      <?php endif; ?>
    </a>
  </div>

  <!-- Members overview -->
  <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
      <p class="font-semibold text-slate-700 text-sm"><?= t('all_members') ?></p>
      <a href="<?= APP_URL ?>/admin/users.php" class="text-xs text-primary font-medium"><?= t('view_all') ?></a>
    </div>
    <ul class="divide-y divide-slate-100">
      <?php foreach ($members as $m): ?>
      <?php $mb = user_balance((int)$m['id']); ?>
      <li class="px-4 py-3 flex items-center gap-3">
        <div class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center
                    flex-shrink-0 text-primary font-bold text-sm">
          <?= strtoupper(substr($m['full_name'], 0, 1)) ?>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold text-slate-800 truncate"><?= htmlspecialchars($m['full_name']) ?></p>
          <p class="text-xs text-slate-400">@<?= htmlspecialchars($m['username']) ?></p>
        </div>
        <div class="text-right">
          <p class="text-sm font-bold <?= $mb >= 0 ? 'text-emerald-600' : 'text-red-500' ?>">
            <?= fmt_money($mb) ?>
          </p>
          <?php if (!$m['is_active']): ?>
          <span class="text-xs text-red-400">Inactive</span>
          <?php endif; ?>
        </div>
      </li>
      <?php endforeach; ?>
      <?php if (empty($members)): ?>
      <li class="px-4 py-6 text-center text-slate-400 text-sm">No members yet.
        <a href="<?= APP_URL ?>/admin/users.php" class="text-primary underline">Add one →</a>
      </li>
      <?php endif; ?>
    </ul>
  </div>

</div>

<?php page_end('admin'); ?>
