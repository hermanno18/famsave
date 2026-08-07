<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();

/** Ownership guard: only act on a goal if it belongs to this member. */
function _my_goal_or_die(int $goal_id, int $user_id): array {
    $goal = get_goal($goal_id);
    if (!$goal || (int)$goal['user_id'] !== $user_id) {
        flash('error', 'Goal not found.');
        redirect('/goals.php');
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
            create_goal($title, $target, $user['id'], $user['id']);
            flash('success', 'New savings goal created!');
        }
        redirect('/goals.php');
    }

    if ($action === 'toggle_pin') {
        _my_goal_or_die($goal_id, (int)$user['id']);
        toggle_goal_pin($goal_id);
        redirect('/goals.php');
    }

    if ($action === 'complete_goal') {
        _my_goal_or_die($goal_id, (int)$user['id']);
        complete_goal($goal_id);
        flash('success', 'Goal marked complete! ');
        redirect('/goals.php');
    }

    if ($action === 'reopen_goal') {
        _my_goal_or_die($goal_id, (int)$user['id']);
        reopen_goal($goal_id);
        flash('success', 'Goal reopened.');
        redirect('/goals.php');
    }

    if ($action === 'delete_goal') {
        _my_goal_or_die($goal_id, (int)$user['id']);
        delete_goal($goal_id);
        flash('success', 'Goal deleted.');
        redirect('/goals.php');
    }
}

$balance         = user_balance((int)$user['id']);
$active_goals    = list_active_goals((int)$user['id']);
$completed_goals = list_completed_goals((int)$user['id']);
$family_goals    = pinned_family_goals_for_user((int)$user['id']);
$main_bal        = main_balance();
$csrf            = csrf_token();

page_start('My Goals', 'goals');
?>

<div class="py-5 space-y-5 fade-up">
  <h2 class="text-2xl font-bold text-slate-800">My Goals</h2>

  <!-- Create a new goal -->
  <div class="bg-white rounded-2xl shadow-sm overflow-hidden" x-data="{open: <?= empty($active_goals) ? 'true' : 'false' ?>}">
    <button @click="open=!open" class="w-full flex items-center justify-between px-4 py-3.5 text-left">
      <p class="font-semibold text-slate-700 text-sm flex items-center gap-2">
        <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        New Savings Goal
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
          <input type="text" name="goal_title" required maxlength="80" placeholder="e.g. New Bike"
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

  <!-- Family goals pinned to me -->
  <?php if (!empty($family_goals)): ?>
  <div>
    <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-2 px-1">Family Goals</p>
    <div class="space-y-3">
      <?php foreach ($family_goals as $g): ?>
      <?php render_goal_card($g, $main_bal, 'Family Goal'); ?>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- My active goals -->
  <div>
    <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-2 px-1">Active Goals</p>
    <?php if (empty($active_goals)): ?>
    <div class="bg-white rounded-2xl p-8 text-center text-slate-400 text-sm shadow-sm">
      No active goals yet. Create one above!
    </div>
    <?php else: ?>
    <div class="space-y-3">
      <?php foreach ($active_goals as $g): ?>
      <?php render_goal_card($g, $balance, '', true, $csrf); ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Completed goals history -->
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
      <?php render_goal_card($g, $balance, '', true, $csrf); ?>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php page_end('goals'); ?>
