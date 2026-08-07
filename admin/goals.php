<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();

/** Ownership guard: only act on a goal if it's a family-wide (admin) goal. */
function _family_goal_or_die(int $goal_id): array {
    $goal = get_goal($goal_id);
    if (!$goal || $goal['user_id'] !== null) {
        flash('error', 'Goal not found.');
        redirect('/admin/goals.php');
    }
    return $goal;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action  = $_POST['action'] ?? '';
    $goal_id = (int) ($_POST['goal_id'] ?? 0);

    if ($action === 'create_goal') {
        $title  = trim($_POST['goal_title'] ?? '');
        $target = (int) ($_POST['goal_target'] ?? 0);
        if ($title === '' || $target <= 0) {
            flash('error', 'Goal needs a title and a target amount above zero.');
        } else {
            create_goal($title, $target, $admin['id'], null);
            flash('success', 'New family goal created!');
        }
        redirect('/admin/goals.php');
    }

    if ($action === 'complete_goal') {
        _family_goal_or_die($goal_id);
        complete_goal($goal_id);
        flash('success', 'Goal marked complete!');
        redirect('/admin/goals.php');
    }

    if ($action === 'reopen_goal') {
        _family_goal_or_die($goal_id);
        reopen_goal($goal_id);
        flash('success', 'Goal reopened.');
        redirect('/admin/goals.php');
    }

    if ($action === 'delete_goal') {
        _family_goal_or_die($goal_id);
        delete_goal($goal_id);
        flash('success', 'Goal deleted.');
        redirect('/admin/goals.php');
    }

    if ($action === 'set_pins') {
        _family_goal_or_die($goal_id);
        $member_ids = array_map('intval', $_POST['members'] ?? []);
        set_goal_pins($goal_id, $member_ids);
        flash('success', 'Visibility updated — pinned members can now see this goal.');
        redirect('/admin/goals.php');
    }
}

$current         = main_balance();
$active_goals    = list_active_goals(null);
$completed_goals = list_completed_goals(null);
$members         = db_rows("SELECT id, full_name FROM users WHERE role='member' AND is_active=1 ORDER BY full_name");
$csrf            = csrf_token();

page_start('Family Goals', 'goals');
?>

<div class="py-5 space-y-5 fade-up">
  <h2 class="text-2xl font-bold text-slate-800">Family Goals</h2>

  <!-- Create a new family goal -->
  <div class="bg-white rounded-2xl shadow-sm overflow-hidden" x-data="{open: <?= empty($active_goals) ? 'true' : 'false' ?>}">
    <button @click="open=!open" class="w-full flex items-center justify-between px-4 py-3.5 text-left">
      <p class="font-semibold text-slate-700 text-sm flex items-center gap-2">
        <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        New Family Goal
      </p>
      <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-slate-400 transition-transform"
           fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
      </svg>
    </button>
    <div x-show="open" x-cloak>
      <form method="POST" action="" class="px-4 pb-4 pt-2 border-t border-slate-100 space-y-3">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="create_goal">
        <div>
          <label class="block text-xs font-semibold text-slate-500 mb-1.5">Goal Title</label>
          <input type="text" name="goal_title" required maxlength="80" placeholder="Family Vacation 2027"
                 class="w-full px-4 py-2.5 rounded-xl border border-slate-200
                        focus:outline-none focus:ring-2 focus:ring-primary text-sm">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 mb-1.5">Target Amount (<?= CURRENCY ?>)</label>
          <input type="number" name="goal_target" min="1" required
                 class="w-full px-4 py-2.5 rounded-xl border border-slate-200
                        focus:outline-none focus:ring-2 focus:ring-primary text-sm font-semibold">
        </div>
        <button type="submit"
                class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-2.5 rounded-xl
                       transition active:scale-95 text-sm">
          Create Goal
        </button>
      </form>
    </div>
  </div>

  <!-- Active family goals -->
  <div>
    <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-2 px-1">Active Goals</p>
    <?php if (empty($active_goals)): ?>
    <div class="bg-white rounded-2xl p-8 text-center text-slate-400 text-sm shadow-sm">
      No family goals yet. Create one above!
    </div>
    <?php else: ?>
    <div class="space-y-3">
      <?php foreach ($active_goals as $g): ?>
      <?php $pinned_ids = goal_pin_member_ids((int)$g['id']); ?>
      <div>
        <?php render_goal_card($g, $current, '', true, $csrf); ?>
        <?php if (!empty($members)): ?>
        <div class="bg-white rounded-2xl shadow-sm p-4 -mt-2 border-t border-slate-100">
          <p class="text-xs font-semibold text-slate-500 mb-2">Visible on dashboard for:</p>
          <form method="POST" action="" class="space-y-2">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="set_pins">
            <input type="hidden" name="goal_id" value="<?= $g['id'] ?>">
            <div class="flex flex-wrap gap-2">
              <?php foreach ($members as $m): ?>
              <label class="flex items-center gap-1.5 text-xs bg-slate-50 hover:bg-slate-100
                             border border-slate-200 rounded-lg px-2.5 py-1.5 cursor-pointer transition">
                <input type="checkbox" name="members[]" value="<?= $m['id'] ?>"
                       <?= in_array((int)$m['id'], $pinned_ids, true) ? 'checked' : '' ?>
                       class="rounded border-slate-300 text-primary focus:ring-primary">
                <?= htmlspecialchars($m['full_name']) ?>
              </label>
              <?php endforeach; ?>
            </div>
            <button type="submit"
                    class="text-xs bg-primary/10 hover:bg-primary/20 text-primary font-semibold
                           px-3 py-1.5 rounded-lg transition active:scale-95">
              Save Visibility
            </button>
          </form>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Completed family goals history -->
  <?php if (!empty($completed_goals)): ?>
  <div class="bg-white rounded-2xl shadow-sm overflow-hidden" x-data="{open:false}">
    <button @click="open=!open" class="w-full flex items-center justify-between px-4 py-3.5 text-left">
      <p class="font-semibold text-slate-700 text-sm">
        Completed Goals <span class="text-slate-400 font-normal">(<?= count($completed_goals) ?>)</span>
      </p>
      <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-slate-400 transition-transform"
           fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
      </svg>
    </button>
    <div x-show="open" x-cloak class="px-4 pb-4 pt-1 border-t border-slate-100 space-y-3">
      <?php foreach ($completed_goals as $g): ?>
      <?php render_goal_card($g, $current, '', true, $csrf); ?>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php page_end('goals'); ?>
