<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? 'update_balance') === 'update_balance') {
    verify_csrf();
    $amount = (int) ($_POST['amount'] ?? 0);
    $note   = trim($_POST['note'] ?? '');

    if ($amount < 0) {
        flash('error', 'Balance cannot be negative.');
        redirect('/admin/balance.php');
    }

    try {
        $proof = handle_upload('proof');
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        redirect('/admin/balance.php');
    }

    db_run(
        "INSERT INTO balance_logs (amount,note,proof_file,logged_by) VALUES (?,?,?,?)",
        [$amount, $note, $proof, $admin['id']]
    );

    notify_all_members(t('balance_updated_notif'));
    flash('success', t('balance_updated'));
    redirect('/admin/balance.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'set_goal') {
    verify_csrf();
    $title  = trim($_POST['goal_title'] ?? '');
    $target = (int) ($_POST['goal_target'] ?? 0);

    if ($title === '' || $target <= 0) {
        flash('error', 'Goal needs a title and a target amount above zero.');
    } else {
        set_savings_goal($title, $target, $admin['id']);
        flash('success', 'Savings goal set!');
    }
    redirect('/admin/balance.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'clear_goal') {
    verify_csrf();
    clear_savings_goal();
    flash('success', 'Savings goal cleared.');
    redirect('/admin/balance.php');
}

$current = main_balance();
$goal    = active_goal();
$logs    = db_rows("SELECT b.*, u.full_name FROM balance_logs b JOIN users u ON u.id=b.logged_by ORDER BY b.id DESC LIMIT 20");

page_start(t('nav_balance'), 'balance');
?>

<div class="py-5 space-y-5 fade-up">
  <h2 class="text-2xl font-bold text-slate-800"><?= t('update_balance') ?></h2>

  <!-- Current balance -->
  <div class="bg-gradient-to-br from-amber-500 to-amber-600 rounded-3xl p-5 text-white">
    <p class="text-amber-100 text-sm mb-1">Current Logged Balance</p>
    <p class="text-4xl font-extrabold" data-countup="<?= $current ?>"><?= fmt_money($current) ?></p>
    <p class="text-amber-200 text-xs mt-2">Mirrors your actual bank account</p>
  </div>

  <?php render_goal_progress($goal, $current); ?>

  <!-- Goal form -->
  <div class="bg-white rounded-2xl shadow-sm p-5">
    <p class="font-semibold text-slate-700 text-sm mb-4">
      <?= $goal ? 'Update Savings Goal' : 'Set a Savings Goal' ?>
    </p>
    <form method="POST" action="" class="space-y-3">
      <?= csrf_input() ?>
      <input type="hidden" name="form_action" value="set_goal">
      <div>
        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Goal Title</label>
        <input type="text" name="goal_title" required maxlength="80"
               value="<?= $goal ? htmlspecialchars($goal['title']) : '' ?>"
               placeholder="e.g. Family Vacation 2027"
               class="w-full px-4 py-2.5 rounded-xl border border-slate-200
                      focus:outline-none focus:ring-2 focus:ring-primary text-sm">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Target Amount (<?= CURRENCY ?>)</label>
        <input type="number" name="goal_target" min="1" required
               value="<?= $goal ? (int)$goal['target_amount'] : '' ?>"
               class="w-full px-4 py-2.5 rounded-xl border border-slate-200
                      focus:outline-none focus:ring-2 focus:ring-primary text-sm font-semibold">
      </div>
      <button type="submit"
              class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-2.5 rounded-xl
                     transition active:scale-95 text-sm">
        <?= $goal ? 'Update Goal' : 'Set Goal' ?>
      </button>
    </form>
    <?php if ($goal): ?>
    <form method="POST" action="" class="mt-2">
      <?= csrf_input() ?>
      <input type="hidden" name="form_action" value="clear_goal">
      <button type="submit"
              class="w-full bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold py-2.5 rounded-xl
                     transition active:scale-95 text-sm">
        Clear Goal
      </button>
    </form>
    <?php endif; ?>
  </div>

  <!-- Update form -->
  <div class="bg-white rounded-2xl shadow-sm p-5">
    <p class="font-semibold text-slate-700 text-sm mb-4">Set New Balance</p>
    <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
      <?= csrf_input() ?>

      <div>
        <label class="block text-xs font-semibold text-slate-500 mb-1.5"><?= t('new_balance') ?> *</label>
        <div class="relative">
          <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-semibold text-sm">XAF</span>
          <input type="number" name="amount" min="0" required
                 class="w-full pl-14 pr-4 py-3.5 rounded-xl border border-slate-200
                        focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent
                        text-slate-800 bg-white transition text-lg font-bold"
                 placeholder="<?= $current ?>">
        </div>
        <p class="text-xs text-slate-400 mt-1">
          Enter the exact balance shown on your bank statement.
        </p>
      </div>

      <div>
        <label class="block text-xs font-semibold text-slate-500 mb-1.5"><?= t('note') ?></label>
        <textarea name="note" rows="2"
                  class="w-full px-4 py-3 rounded-xl border border-slate-200
                         focus:outline-none focus:ring-2 focus:ring-amber-400
                         text-slate-800 bg-white resize-none transition"
                  placeholder="e.g. After interest credit, after withdrawal of 50 000 XAF..."></textarea>
      </div>

      <div>
        <label class="block text-xs font-semibold text-slate-500 mb-1.5"><?= t('proof') ?></label>
        <label class="flex items-center gap-3 p-3 border border-dashed border-slate-300
                      rounded-xl cursor-pointer hover:bg-slate-50 transition"
               x-data="{name:''}" @change.window="$nextTick(()=>{})">
          <input type="file" name="proof" accept="image/*,.pdf" class="sr-only"
                 @change="name=$event.target.files[0]?.name||''">
          <svg class="w-8 h-8 text-slate-300 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round"
              d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
          </svg>
          <span class="text-sm text-slate-500" x-text="name || 'Bank screenshot or PDF'"></span>
        </label>
        <p class="text-xs text-slate-400 mt-1"><?= t('proof_hint') ?></p>
      </div>

      <button type="submit"
              class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-4 rounded-2xl
                     transition-all active:scale-95 shadow-md shadow-amber-500/30 text-base">
        <?= t('update_balance') ?>
      </button>
    </form>
  </div>

  <!-- Balance trend chart -->
  <?php if (count($logs) >= 2): ?>
  <div class="bg-white rounded-2xl shadow-sm p-4">
    <p class="font-semibold text-slate-700 text-sm mb-3">Balance Trend</p>
    <div style="height: 220px;">
      <canvas id="balanceChart"></canvas>
    </div>
  </div>
  <?php elseif (count($logs) === 1): ?>
  <div class="bg-white rounded-2xl shadow-sm p-4 text-center text-slate-400 text-sm">
    Log one more balance update to unlock the trend chart here.
  </div>
  <?php endif; ?>

  <!-- Balance history -->
  <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100">
      <p class="font-semibold text-slate-700 text-sm"><?= t('balance_history') ?></p>
    </div>
    <?php if (empty($logs)): ?>
    <div class="px-4 py-6 text-center text-slate-400 text-sm">No balance logs yet.</div>
    <?php else: ?>
    <ul class="divide-y divide-slate-100">
      <?php foreach ($logs as $log): ?>
      <li class="px-4 py-3">
        <div class="flex items-center justify-between">
          <p class="text-lg font-bold text-slate-800"><?= fmt_money((int)$log['amount']) ?></p>
          <p class="text-xs text-slate-400"><?= fmt_date($log['created_at']) ?></p>
        </div>
        <?php if ($log['note']): ?>
        <p class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($log['note']) ?></p>
        <?php endif; ?>
        <?php if ($log['proof_file']): ?>
        <a href="<?= APP_URL ?>/serve_file.php?f=<?= urlencode($log['proof_file']) ?>"
           target="_blank"
           class="inline-flex items-center gap-1 mt-1 text-xs text-primary underline">
          <?= t('view_proof') ?>
        </a>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
</div>

<?php if (count($logs) >= 2): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('balanceChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode(array_map(fn($l) => date('d M', strtotime($l['created_at'])), array_reverse($logs))) ?>,
    datasets: [{
      label: 'Balance (<?= CURRENCY ?>)',
      data: <?= json_encode(array_map(fn($l) => (int)$l['amount'], array_reverse($logs))) ?>,
      borderColor: '#0f766e',
      backgroundColor: 'rgba(15,118,110,0.1)',
      tension: 0.3,
      fill: true,
      pointRadius: 3,
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true } }
  }
});
</script>
<?php endif; ?>

<?php page_end('balance'); ?>
