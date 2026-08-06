<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$admin  = require_admin();
$filter = $_GET['status'] ?? 'pending';
$allowed = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($filter, $allowed)) $filter = 'pending';

$where = $filter === 'all' ? '' : "AND t.status = '$filter'";
$txs = db_rows("
    SELECT t.*, u.full_name, u.username
      FROM transactions t
      JOIN users u ON u.id = t.user_id
     WHERE t.type = 'deposit' $where
     ORDER BY t.id DESC
");

page_start(t('nav_deposits'), 'deposits');
?>

<div class="py-5 space-y-4 fade-up">
  <h2 class="text-2xl font-bold text-slate-800"><?= t('nav_deposits') ?></h2>

  <!-- Filter tabs -->
  <div class="flex gap-2 overflow-x-auto pb-1 no-scrollbar">
    <?php foreach (['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','all'=>'All'] as $s=>$label): ?>
    <a href="?status=<?= $s ?>"
       class="flex-shrink-0 px-4 py-1.5 rounded-full text-xs font-semibold transition
              <?= $filter===$s ? 'bg-primary text-white' : 'bg-white text-slate-500 border border-slate-200 hover:border-primary' ?>">
      <?= $label ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Transaction cards -->
  <?php if (empty($txs)): ?>
  <div class="bg-white rounded-2xl p-8 text-center text-slate-400 text-sm shadow-sm">
    No deposits found.
  </div>
  <?php else: ?>
  <div class="space-y-3" id="deposit-list">
    <?php foreach ($txs as $tx): ?>
    <div class="bg-white rounded-2xl shadow-sm overflow-hidden" id="tx-<?= $tx['id'] ?>">
      <div class="p-4">
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="font-semibold text-slate-800"><?= htmlspecialchars($tx['full_name']) ?></p>
            <p class="text-xs text-slate-400">@<?= htmlspecialchars($tx['username']) ?> · <?= fmt_date($tx['created_at']) ?></p>
          </div>
          <div class="text-right flex-shrink-0">
            <p class="text-lg font-extrabold text-emerald-600">+<?= fmt_money((int)$tx['amount']) ?></p>
            <?= fmt_status_badge($tx['status']) ?>
          </div>
        </div>

        <?php if ($tx['note']): ?>
        <p class="mt-2 text-sm text-slate-600 bg-slate-50 rounded-lg px-3 py-2">
          <?= htmlspecialchars($tx['note']) ?>
        </p>
        <?php endif; ?>

        <?php if ($tx['admin_note']): ?>
        <p class="mt-1 text-xs text-slate-500 italic">Admin: "<?= htmlspecialchars($tx['admin_note']) ?>"</p>
        <?php endif; ?>

        <!-- Proof + actions -->
        <div class="mt-3 flex items-center gap-2 flex-wrap">
          <?php if ($tx['proof_file']): ?>
          <a href="<?= APP_URL ?>/serve_file.php?f=<?= urlencode($tx['proof_file']) ?>"
             target="_blank"
             class="inline-flex items-center gap-1.5 text-xs bg-slate-100 hover:bg-slate-200
                    text-slate-600 px-3 py-1.5 rounded-lg font-medium transition">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
            <?= t('view_proof') ?>
          </a>
          <?php else: ?>
          <span class="text-xs text-slate-400"><?= t('no_proof') ?></span>
          <?php endif; ?>

          <?php if ($tx['status'] === 'pending'): ?>
          <div class="ml-auto flex gap-2" x-data="txAction(<?= $tx['id'] ?>, '<?= csrf_token() ?>')">
            <button @click="act('approve')"
                    class="bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold
                           px-4 py-1.5 rounded-lg transition active:scale-95">
              <?= t('approve') ?>
            </button>
            <button @click="showReject=!showReject"
                    class="bg-red-100 hover:bg-red-200 text-red-600 text-xs font-bold
                           px-4 py-1.5 rounded-lg transition active:scale-95">
              <?= t('reject') ?>
            </button>
          </div>
          <?php endif; ?>
        </div>

        <!-- Reject note input -->
        <?php if ($tx['status'] === 'pending'): ?>
        <div x-data="txAction(<?= $tx['id'] ?>, '<?= csrf_token() ?>')" x-show="showReject" x-cloak
             class="mt-3 flex gap-2">
          <input x-model="note" type="text"
                 class="flex-1 px-3 py-2 text-sm rounded-lg border border-red-200
                        focus:outline-none focus:ring-1 focus:ring-red-400"
                 placeholder="<?= t('admin_note') ?>">
          <button @click="act('reject')"
                  class="bg-red-500 text-white text-xs font-bold px-4 py-2 rounded-lg
                         transition active:scale-95 flex-shrink-0">
            Confirm
          </button>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<script>
function txAction(id, csrf) {
  return {
    showReject: false,
    note: '',
    async act(action) {
      const body = new URLSearchParams({ csrf, action, id, note: this.note, type: 'deposit' });
      const r = await fetch('<?= APP_URL ?>/api/action.php', { method:'POST', body });
      const d = await r.json();
      if (d.ok) {
        document.getElementById('tx-' + id)?.remove();
      } else { alert(d.error); }
    }
  }
}
</script>

<?php page_end('deposits'); ?>
