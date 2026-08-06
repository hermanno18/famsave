<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user    = require_login();
$balance = user_balance((int)$user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $amount = (int) ($_POST['amount'] ?? 0);
    $note   = trim($_POST['note'] ?? '');

    if ($amount < 100) {
        flash('error', 'Minimum amount is 100 XAF.');
        redirect('/withdraw.php');
    }
    if ($amount > $balance) {
        flash('error', t('insufficient_funds'));
        redirect('/withdraw.php');
    }

    // Check no pending withdrawal already exists
    $pending = db_val(
        "SELECT COUNT(*) FROM transactions WHERE user_id=? AND type='withdrawal' AND status='pending'",
        [$user['id']]
    );
    if ((int)$pending > 0) {
        flash('error', 'You already have a pending withdrawal request. Please wait for it to be reviewed.');
        redirect('/withdraw.php');
    }

    db_run(
        "INSERT INTO transactions (user_id,type,amount,note) VALUES (?,?,?,?)",
        [$user['id'], 'withdrawal', $amount, $note]
    );

    $admins = db_rows("SELECT id FROM users WHERE role='admin'");
    $msg    = "Withdrawal request of " . fmt_money($amount) . " from {$user['full_name']}.";
    foreach ($admins as $a) notify((int)$a['id'], $msg, '/admin/withdrawals.php');

    flash('success', t('withdrawal_sent'));
    redirect('/dashboard.php');
}

page_start(t('request_withdrawal'), 'withdraw');
?>

<div class="py-5 fade-up">
  <h2 class="text-2xl font-bold text-slate-800 mb-1"><?= t('request_withdrawal') ?></h2>
  <p class="text-slate-500 text-sm mb-6">Request funds from your saved balance.</p>

  <!-- Balance chip -->
  <div class="bg-primary/10 rounded-2xl px-4 py-3 flex items-center gap-3 mb-6">
    <svg class="w-5 h-5 text-primary flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <p class="text-sm text-primary font-medium">
      <?= t('your_balance') ?>: <strong><?= fmt_money($balance) ?></strong>
    </p>
  </div>

  <?php if ($balance < 100): ?>
  <div class="bg-amber-50 border border-amber-200 rounded-2xl px-4 py-6 text-center">
    <p class="text-amber-700 font-semibold">No funds available to withdraw.</p>
    <a href="<?= APP_URL ?>/deposit.php"
       class="inline-block mt-3 bg-primary text-white text-sm font-semibold px-5 py-2 rounded-xl">
      Log a Deposit
    </a>
  </div>
  <?php else: ?>
  <form method="POST" action="" class="space-y-5" x-data="{amount:''}">
    <?= csrf_input() ?>

    <div>
      <label class="block text-sm font-semibold text-slate-700 mb-1.5"><?= t('amount') ?> *</label>
      <div class="relative">
        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-semibold text-sm">XAF</span>
        <input type="number" name="amount" x-model="amount"
               min="100" max="<?= $balance ?>" required
               class="w-full pl-14 pr-4 py-3.5 rounded-xl border border-slate-200
                      focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent
                      text-slate-800 bg-white transition text-lg font-bold"
               placeholder="10 000">
      </div>
      <!-- Quick amount buttons -->
      <div class="flex gap-2 mt-2 flex-wrap">
        <?php foreach ([5000, 10000, 25000, 50000] as $q): ?>
        <?php if ($q <= $balance): ?>
        <button type="button" @click="amount=<?= $q ?>"
                class="text-xs bg-slate-100 hover:bg-primary/10 hover:text-primary
                       px-3 py-1.5 rounded-full font-medium transition">
          <?= number_format($q, 0, '.', ' ') ?>
        </button>
        <?php endif; ?>
        <?php endforeach; ?>
        <button type="button" @click="amount=<?= $balance ?>"
                class="text-xs bg-slate-100 hover:bg-primary/10 hover:text-primary
                       px-3 py-1.5 rounded-full font-medium transition">
          Max
        </button>
      </div>
    </div>

    <div>
      <label class="block text-sm font-semibold text-slate-700 mb-1.5"><?= t('note') ?></label>
      <textarea name="note" rows="2"
                class="w-full px-4 py-3 rounded-xl border border-slate-200
                       focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent
                       text-slate-800 bg-white resize-none transition"
                placeholder="Reason for withdrawal..."></textarea>
    </div>

    <button type="submit"
            class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-4 rounded-2xl
                   transition-all active:scale-95 shadow-md shadow-red-500/30 text-base">
      <?= t('request_withdrawal') ?>
    </button>
  </form>
  <?php endif; ?>
</div>

<?php page_end('withdraw'); ?>
