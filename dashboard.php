<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();

$balance    = user_balance((int)$user['id']);
$main_bal   = main_balance();
$goal       = active_goal();
$balance_logs = db_rows("SELECT * FROM balance_logs ORDER BY id DESC LIMIT 20");
$recent_txs = db_rows(
    "SELECT * FROM transactions WHERE user_id=? ORDER BY id DESC LIMIT 5",
    [$user['id']]
);

page_start(t('nav_home'), 'home');
?>

<div class="py-5 space-y-5 fade-up">

  <!-- Greeting -->
  <div>
    <p class="text-slate-500 text-sm">👋 <?= date('H') < 12 ? 'Good morning' : (date('H') < 18 ? 'Good afternoon' : 'Good evening') ?></p>
    <h2 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($user['full_name']) ?></h2>
  </div>

  <!-- Your balance card -->
  <div class="count-card rounded-3xl overflow-hidden shadow-lg">
    <div class="bg-gradient-to-br from-primary to-primary-dark p-6 pb-5">
      <p class="text-teal-100 text-sm font-medium mb-1"><?= t('your_balance') ?></p>
      <p class="text-white text-4xl font-extrabold tracking-tight"
         data-countup="<?= $balance ?>">
        <?= fmt_money($balance) ?>
      </p>
      <div class="mt-4 flex gap-2">
        <a href="<?= APP_URL ?>/deposit.php"
           class="flex-1 bg-white/15 hover:bg-white/25 text-white text-sm font-semibold
                  py-2.5 rounded-xl text-center transition active:scale-95 flex items-center justify-center gap-1.5">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
          </svg>
          <?= t('nav_deposit') ?>
        </a>
        <a href="<?= APP_URL ?>/withdraw.php"
           class="flex-1 bg-white/15 hover:bg-white/25 text-white text-sm font-semibold
                  py-2.5 rounded-xl text-center transition active:scale-95 flex items-center justify-center gap-1.5">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
          </svg>
          <?= t('nav_withdraw') ?>
        </a>
      </div>
    </div>
  </div>

  <?php render_goal_progress($goal, $main_bal); ?>

  <!-- Family balance trend -->
  <?php if (count($balance_logs) >= 2): ?>
  <div class="bg-white rounded-2xl shadow-sm p-4">
    <p class="font-semibold text-slate-700 text-sm mb-3">Family Balance Trend</p>
    <div style="height: 180px;">
      <canvas id="familyBalanceChart"></canvas>
    </div>
  </div>
  <?php endif; ?>

  <!-- Main account balance (read-only for members) -->
  <div class="bg-white rounded-2xl shadow-sm p-4 flex items-center gap-4">
    <div class="w-12 h-12 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
      <svg class="w-6 h-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M3 6l3 1m0 0l-3 9a5 5 0 006.9 4.9L9 7m0 0l6 2m-6-2L9 21m6-14l3-1m-3 1l-3 9a5 5 0 006.9 4.9L15 7"/>
      </svg>
    </div>
    <div>
      <p class="text-xs text-slate-500"><?= t('main_balance') ?></p>
      <p class="text-lg font-bold text-slate-800"><?= fmt_money($main_bal) ?></p>
    </div>
  </div>

  <!-- Recent activity -->
  <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
      <p class="font-semibold text-slate-700 text-sm"><?= t('recent_activity') ?></p>
      <a href="<?= APP_URL ?>/profile.php" class="text-xs text-primary font-medium"><?= t('view_all') ?></a>
    </div>

    <?php if (empty($recent_txs)): ?>
    <div class="px-4 py-8 text-center text-slate-400 text-sm"><?= t('no_transactions') ?></div>
    <?php else: ?>
    <ul class="divide-y divide-slate-100">
      <?php foreach ($recent_txs as $tx): ?>
      <li class="px-4 py-3 flex items-center gap-3">
        <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0
                    <?= $tx['type']==='deposit' ? 'bg-emerald-100' : 'bg-red-100' ?>">
          <svg class="w-5 h-5 <?= $tx['type']==='deposit' ? 'text-emerald-600' : 'text-red-500' ?>"
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
          <p class="text-xs text-slate-400 truncate"><?= fmt_date($tx['created_at']) ?></p>
        </div>
        <div class="text-right">
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

</div>

<?php if (count($balance_logs) >= 2): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('familyBalanceChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode(array_map(fn($l) => date('d M', strtotime($l['created_at'])), array_reverse($balance_logs))) ?>,
    datasets: [{
      label: 'Balance (<?= CURRENCY ?>)',
      data: <?= json_encode(array_map(fn($l) => (int)$l['amount'], array_reverse($balance_logs))) ?>,
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

<?php page_end('home'); ?>
