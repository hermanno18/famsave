<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Already logged in?
if (current_user()) redirect('/dashboard.php');

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username !== '' && is_login_locked($username)) {
        $error = t('too_many_attempts');
    } elseif (login_user($username, $password)) {
        $u = current_user();
        redirect($u['role'] === 'admin' ? '/admin/index.php' : '/dashboard.php');
    } else {
        // Check if user exists but is inactive
        $exists = db_row("SELECT is_active FROM users WHERE username=?", [$username]);
        $error  = ($exists && !$exists['is_active'])
                    ? t('account_inactive')
                    : t('invalid_creds');
    }
}
?><!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'en' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0f766e">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="FamSave">
<link rel="manifest" href="<?= APP_URL ?>/manifest.json">
<title>FamSave — <?= t('login') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: { extend: {
    colors: {
      primary: { DEFAULT:'#0f766e', light:'#14b8a6', dark:'#115e59' },
      accent:  { DEFAULT:'#d97706', light:'#fbbf24' },
    }
  }}
}
</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  body { font-family:'Inter',sans-serif; }
  @keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
  .fade-up { animation: fadeUp .4s ease both; }
</style>
</head>
<body class="bg-gradient-to-br from-primary-dark via-primary to-primary-light
             min-h-screen flex flex-col items-center justify-center px-4"
      style="padding-bottom:env(safe-area-inset-bottom)">

<!-- Lang toggle -->
<div class="absolute top-4 right-4">
  <a href="<?= APP_URL ?>/api/action.php?action=lang&to=<?= ($_SESSION['lang']??'en')==='en'?'fr':'en' ?>"
     class="text-xs font-bold bg-white/20 hover:bg-white/30 text-white px-3 py-1.5 rounded-full transition">
    <?= t('lang_toggle') ?>
  </a>
</div>

<div class="w-full max-w-sm fade-up">
  <!-- Logo -->
  <div class="text-center mb-8">
    <div class="inline-flex items-center justify-center w-20 h-20 bg-white/15 rounded-3xl mb-4 shadow-xl">
      <svg class="w-10 h-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M17 9V7a5 5 0 00-10 0v2M5 9h14l1 12H4L5 9z"/>
      </svg>
    </div>
    <h1 class="text-4xl font-extrabold text-white tracking-tight">FamSave</h1>
    <p class="text-teal-100 text-sm mt-1">Family Savings, Simplified</p>
  </div>

  <!-- Card -->
  <div class="bg-white rounded-3xl shadow-2xl px-6 py-8"
       x-data="{showPw: false}">

    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 mb-5 text-sm flex gap-2">
      <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
      </svg>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="" class="space-y-5">
      <div>
        <label class="block text-sm font-semibold text-slate-700 mb-1.5"><?= t('username') ?></label>
        <input type="text" name="username" required autocomplete="username"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
               class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50
                      focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent
                      text-slate-800 placeholder-slate-400 transition"
               placeholder="your_username">
      </div>

      <div>
        <label class="block text-sm font-semibold text-slate-700 mb-1.5"><?= t('password') ?></label>
        <div class="relative">
          <input :type="showPw ? 'text' : 'password'" name="password" required autocomplete="current-password"
                 class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50
                        focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent
                        text-slate-800 placeholder-slate-400 pr-12 transition"
                 placeholder="••••••••">
          <button type="button" @click="showPw=!showPw"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
            <svg x-show="!showPw" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
            <svg x-show="showPw" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
            </svg>
          </button>
        </div>
      </div>

      <button type="submit"
              class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-3.5 rounded-xl
                     transition-all active:scale-95 shadow-md shadow-primary/30 text-base">
        <?= t('login') ?>
      </button>
    </form>
  </div>

  <p class="text-center text-white/50 text-xs mt-6">FamSave &copy; <?= date('Y') ?></p>
</div>

<script>
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('<?= APP_URL ?>/sw.js').catch(()=>{});
}
</script>
</body></html>
