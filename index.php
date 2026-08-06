<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = current_user();
if ($user) {
    redirect($user['role'] === 'admin' ? '/admin/index.php' : '/dashboard.php');
} else {
    redirect('/login.php');
}
