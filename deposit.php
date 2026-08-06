<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $amount = (int) ($_POST['amount'] ?? 0);
    $note   = trim($_POST['note'] ?? '');

    if ($amount < 100) {
        flash('error', 'Minimum amount is 100 XAF.');
        redirect('/deposit.php');
    }

    try {
        $proof = handle_upload('proof');
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
        redirect('/deposit.php');
    }

    db_run(
        "INSERT INTO transactions (user_id,type,amount,note,proof_file) VALUES (?,?,?,?,?)",
        [$user['id'], 'deposit', $amount, $note, $proof]
    );

    // Notify admin(s)
    $admins = db_rows("SELECT id FROM users WHERE role='admin'");
    $msg    = "New deposit request of " . fmt_money($amount) . " from {$user['full_name']}.";
    foreach ($admins as $a) notify((int)$a['id'], $msg, '/admin/deposits.php');

    flash('success', t('deposit_sent'));
    redirect('/dashboard.php');
}

page_start(t('log_deposit'), 'deposit');
?>

<div class="py-5 fade-up">
  <h2 class="text-2xl font-bold text-slate-800 mb-1"><?= t('log_deposit') ?></h2>
  <p class="text-slate-500 text-sm mb-6">Record a deposit you made to the shared account.</p>

  <form method="POST" action="" enctype="multipart/form-data"
        class="space-y-5" x-data="depositForm()">

    <?= csrf_input() ?>

    <!-- Amount -->
    <div>
      <label class="block text-sm font-semibold text-slate-700 mb-1.5"><?= t('amount') ?> *</label>
      <div class="relative">
        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-semibold text-sm">XAF</span>
        <input type="number" name="amount" x-model="amount" min="100" required
               class="w-full pl-14 pr-4 py-3.5 rounded-xl border border-slate-200
                      focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent
                      text-slate-800 bg-white transition text-lg font-bold"
               placeholder="50 000">
      </div>
      <p class="text-xs text-slate-400 mt-1" x-show="amount > 0" x-cloak>
        = <span x-text="formatXAF(amount)"></span>
      </p>
    </div>

    <!-- Note -->
    <div>
      <label class="block text-sm font-semibold text-slate-700 mb-1.5"><?= t('note') ?></label>
      <textarea name="note" rows="2"
                class="w-full px-4 py-3 rounded-xl border border-slate-200
                       focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent
                       text-slate-800 bg-white resize-none transition"
                placeholder="e.g. September savings..."></textarea>
    </div>

    <!-- File upload -->
    <div>
      <label class="block text-sm font-semibold text-slate-700 mb-1.5"><?= t('proof') ?> *</label>
      <label class="relative flex flex-col items-center justify-center w-full h-36
                    border-2 border-dashed border-slate-300 rounded-2xl cursor-pointer
                    bg-slate-50 hover:bg-slate-100 transition"
             :class="preview ? 'border-primary' : ''"
             @dragover.prevent @drop.prevent="onDrop($event)">
        <input type="file" name="proof" accept="image/*,.pdf" class="sr-only"
               @change="onFile($event)" required>

        <div x-show="!preview" class="text-center">
          <svg class="w-10 h-10 text-slate-300 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
          </svg>
          <p class="text-sm text-slate-500 font-medium">Tap to upload</p>
          <p class="text-xs text-slate-400"><?= t('proof_hint') ?></p>
        </div>

        <div x-show="preview" x-cloak class="w-full h-full relative">
          <img :src="preview" alt="preview"
               class="w-full h-full object-contain rounded-2xl p-1"
               x-show="isImage">
          <div x-show="!isImage"
               class="flex flex-col items-center justify-center h-full text-primary">
            <svg class="w-10 h-10 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
            </svg>
            <p class="text-sm font-medium" x-text="fileName"></p>
          </div>
          <button type="button" @click.prevent="clearFile()"
                  class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6
                         flex items-center justify-center text-xs shadow">✕</button>
        </div>
      </label>
    </div>

    <button type="submit"
            class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-4 rounded-2xl
                   transition-all active:scale-95 shadow-md shadow-primary/30 text-base">
      <?= t('submit') ?>
    </button>
  </form>
</div>

<script>
function depositForm() {
  return {
    amount: '',
    preview: null,
    isImage: false,
    fileName: '',
    formatXAF(v) {
      return new Intl.NumberFormat('fr-FR').format(parseInt(v)||0) + ' XAF';
    },
    onFile(e) { this.setFile(e.target.files[0]); },
    onDrop(e) { this.setFile(e.dataTransfer.files[0]); },
    setFile(f) {
      if (!f) return;
      this.fileName = f.name;
      this.isImage  = f.type.startsWith('image/');
      if (this.isImage) {
        const r = new FileReader();
        r.onload = e => this.preview = e.target.result;
        r.readAsDataURL(f);
      } else {
        this.preview = '__pdf__';
      }
    },
    clearFile() { this.preview = null; this.fileName = ''; }
  }
}
</script>

<?php page_end('deposit'); ?>
