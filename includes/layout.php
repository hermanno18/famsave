<?php
/**
 * Layout — page_start() / page_end() wrap every page.
 * Uses Tailwind CDN + Alpine.js. Mobile-first PWA shell.
 */

function page_start(string $title, string $active = ''): void {
    $user     = current_user();
    $notifs   = $user ? db_rows(
        "SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 20",
        [$user['id']]
    ) : [];
    $unread   = $user ? unread_count((int)$user['id']) : 0;
    $flash    = get_flash();
    $is_admin = $user && $user['role'] === 'admin';
    $lang     = $_SESSION['lang'] ?? 'en';
    ?><!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0f766e">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="FamSave">
<link rel="manifest" href="<?= APP_URL ?>/manifest.json">
<link rel="apple-touch-icon" href="<?= APP_URL ?>/icons/icon-192.png">
<title><?= htmlspecialchars($title) ?> — FamSave</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: { extend: {
    colors: {
      primary:  { DEFAULT:'#0f766e', light:'#14b8a6', dark:'#115e59' },
      accent:   { DEFAULT:'#d97706', light:'#fbbf24' },
    },
    fontFamily: { sans: ['Inter','ui-sans-serif','system-ui','sans-serif'] },
  }}
}
</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  [x-cloak] { display: none !important; }
  body { font-family:'Inter',sans-serif; -webkit-tap-highlight-color:transparent; }
  .safe-bottom { padding-bottom: max(4.5rem, env(safe-area-inset-bottom)); }
  @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
  .fade-up { animation: fadeUp .35s ease both; }
  @keyframes countUp { from{opacity:0} to{opacity:1} }
  .count-card { animation: fadeUp .5s .1s ease both; }
</style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen" x-data="appShell()" x-init="init()">

<!-- ── Top header ───────────────────────────────────────────────────── -->
<header class="fixed top-0 inset-x-0 z-30 bg-primary text-white shadow-lg"
        style="padding-top:env(safe-area-inset-top)">
  <div class="flex items-center justify-between px-4 h-14 max-w-2xl mx-auto">
    <div class="flex items-center gap-2">
      <svg class="w-7 h-7 text-accent-light" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M17 9V7a5 5 0 00-10 0v2M5 9h14l1 12H4L5 9z"/>
      </svg>
      <span class="font-extrabold text-lg tracking-tight">FamSave</span>
    </div>
    <div class="flex items-center gap-3">
      <!-- Lang toggle -->
      <a href="<?= APP_URL ?>/api/action.php?action=lang&to=<?= $lang==='en'?'fr':'en' ?>"
         class="text-xs font-bold bg-white/20 hover:bg-white/30 px-2 py-1 rounded-full transition">
        <?= t('lang_toggle') ?>
      </a>

      <?php if ($user): ?>
      <!-- Notifications bell -->
      <button @click="notifOpen=!notifOpen" class="relative p-1">
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round"
            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        <?php if ($unread > 0): ?>
        <span data-unread-badge
              class="absolute -top-0.5 -right-0.5 bg-accent text-white text-[10px] font-bold
                      w-4 h-4 flex items-center justify-center rounded-full">
          <?= min($unread, 9) ?><?= $unread > 9 ? '+' : '' ?>
        </span>
        <?php endif; ?>
      </button>
      <?php endif; ?>
    </div>
  </div>
</header>

<!-- ── Notification drawer ──────────────────────────────────────────── -->
<?php if ($user): ?>
<div x-show="notifOpen" x-cloak @click.away="notifOpen=false"
     class="fixed inset-x-0 z-40 bg-white shadow-xl rounded-b-2xl
            max-w-2xl mx-auto overflow-hidden"
     style="top:calc(3.5rem + env(safe-area-inset-top))"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 -translate-y-2"
     x-transition:enter-end="opacity-100 translate-y-0">
  <div class="flex items-center justify-between px-4 py-3 border-b">
    <p class="font-semibold text-sm"><?= t('notifications') ?></p>
    <button @click="markRead()" class="text-xs text-primary underline">Mark all read</button>
  </div>
  <ul class="max-h-72 overflow-y-auto divide-y divide-slate-100">
    <?php if (empty($notifs)): ?>
    <li class="px-4 py-6 text-center text-sm text-slate-400"><?= t('no_notifications') ?></li>
    <?php else: foreach ($notifs as $n): ?>
    <?php $tag = $n['link'] ? 'a' : 'div'; ?>
    <li class="<?= !$n['is_read'] ? 'bg-teal-50' : '' ?>">
      <<?= $tag ?> <?= $n['link'] ? 'href="' . APP_URL . htmlspecialchars($n['link']) . '"' : '' ?>
         class="px-4 py-3 flex gap-3 items-start <?= $n['link'] ? 'hover:bg-slate-50 cursor-pointer' : '' ?>">
        <span class="mt-0.5 w-2 h-2 rounded-full flex-shrink-0 <?= !$n['is_read'] ? 'bg-primary' : 'bg-slate-200' ?>"></span>
        <div>
          <p class="text-sm"><?= htmlspecialchars($n['message']) ?></p>
          <p class="text-xs text-slate-400 mt-0.5"><?= fmt_date($n['created_at']) ?></p>
        </div>
      </<?= $tag ?>>
    </li>
    <?php endforeach; endif; ?>
  </ul>
</div>
<?php endif; ?>

<!-- ── Flash message ────────────────────────────────────────────────── -->
<?php if ($flash): ?>
<div x-data="{show:true}" x-show="show" x-init="setTimeout(()=>show=false,4000)"
     x-transition:leave="transition duration-300" x-transition:leave-end="opacity-0 translate-y-2"
     class="fixed top-16 inset-x-4 z-50 max-w-2xl mx-auto fade-up
            <?= $flash['type']==='error' ? 'bg-red-500' : 'bg-emerald-500' ?>
            text-white rounded-xl px-4 py-3 shadow-lg flex items-center gap-3">
  <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
    <?php if ($flash['type']==='error'): ?>
    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
    <?php else: ?>
    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
    <?php endif; ?>
  </svg>
  <p class="text-sm font-medium"><?= htmlspecialchars($flash['msg']) ?></p>
  <button @click="show=false" class="ml-auto text-white/70 hover:text-white">✕</button>
</div>
<?php endif; ?>

<!-- ── Main content ─────────────────────────────────────────────────── -->
<main class="pt-14 safe-bottom max-w-2xl mx-auto px-4">
<?php // page content goes here — page_end() closes it
}


function page_end(string $active = ''): void {
    $user     = current_user();
    $is_admin = $user && $user['role'] === 'admin';
    $base     = APP_URL;

    // Nav items: [label_key, icon_path_d, href, page_key]
    $member_nav = [
        ['nav_home',     'M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z M9 22V12h6v10',
            '/dashboard.php', 'home'],
        ['nav_deposit',  'M12 4v16m8-8H4',
            '/deposit.php', 'deposit'],
        ['nav_withdraw', 'M19 14l-7 7m0 0l-7-7m7 7V3',
            '/withdraw.php', 'withdraw'],
        ['nav_profile',  'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
            '/profile.php', 'profile'],
    ];
    $admin_nav = [
        ['nav_admin',       'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
            '/admin/index.php', 'admin'],
        ['nav_deposits',    'M12 4v16m8-8H4',
            '/admin/deposits.php', 'deposits'],
        ['nav_withdrawals', 'M19 14l-7 7m0 0l-7-7m7 7V3',
            '/admin/withdrawals.php', 'withdrawals'],
        ['nav_balance',     'M3 6l3 1m0 0l-3 9a5 5 0 006.9 4.9L9 7m0 0l6 2m-6-2L9 21m6-14l3-1m-3 1l-3 9a5 5 0 006.9 4.9L15 7',
            '/admin/balance.php', 'balance'],
        ['nav_users',       'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5 5 0 019.288 0',
            '/admin/users.php', 'users'],
    ];

    $nav = $is_admin ? $admin_nav : $member_nav;
    ?>
</main>

<?php if ($user): ?>
<!-- ── Bottom navigation ─────────────────────────────────────────────── -->
<nav class="fixed bottom-0 inset-x-0 z-30 bg-white border-t border-slate-200 shadow-t"
     style="padding-bottom:env(safe-area-inset-bottom)">
  <div class="flex items-center justify-around max-w-2xl mx-auto h-16">
    <?php foreach ($nav as [$key, $icon_d, $href, $page]): ?>
    <?php $is_active = ($active === $page); ?>
    <a href="<?= $base . $href ?>"
       class="flex flex-col items-center gap-0.5 px-2 min-w-0 flex-1
              <?= $is_active ? 'text-primary' : 'text-slate-400 hover:text-primary' ?> transition-colors">
      <svg class="w-6 h-6 <?= $is_active ? 'stroke-2' : 'stroke-[1.5]' ?>"
           fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="<?= $icon_d ?>"/>
      </svg>
      <span class="text-[10px] font-<?= $is_active ? '700' : '500' ?> truncate leading-tight">
        <?= t($key) ?>
      </span>
      <?php if ($is_active): ?>
      <span class="w-1 h-1 rounded-full bg-primary"></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
</nav>
<?php endif; ?>

<script>
function appShell() {
  return {
    notifOpen: false,
    init() {
      // Register PWA service worker
      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('<?= APP_URL ?>/sw.js').catch(() => {});
      }
    },
    async markRead() {
      try {
        const r = await fetch('<?= APP_URL ?>/api/action.php', {method:'POST',
          headers:{'X-Requested-With':'XMLHttpRequest'},
          body: new URLSearchParams({csrf:'<?= csrf_token() ?>', action:'mark_read'})
        });
        const d = await r.json();
        if (!d.ok) { alert(d.error || 'Could not mark notifications read.'); return; }
        document.querySelectorAll('.bg-teal-50').forEach(el => el.classList.remove('bg-teal-50'));
        document.querySelectorAll('.bg-primary').forEach(el => {
          el.classList.remove('bg-primary'); el.classList.add('bg-slate-200');
        });
        document.querySelectorAll('[data-unread-badge]').forEach(el => el.remove());
      } catch (e) {
        alert('Network error — could not reach the server.');
      }
    }
  }
}

// Count-up animation for balance cards
document.querySelectorAll('[data-countup]').forEach(el => {
  const target = parseInt(el.dataset.countup, 10);
  const duration = 1200;
  const start = performance.now();
  const step = ts => {
    const p = Math.min((ts - start) / duration, 1);
    const ease = 1 - Math.pow(1 - p, 3);
    el.textContent = new Intl.NumberFormat('fr-FR').format(Math.round(target * ease)) + ' XAF';
    if (p < 1) requestAnimationFrame(step);
  };
  requestAnimationFrame(step);
});
</script>
</body></html>
<?php
}
