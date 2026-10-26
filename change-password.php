<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_middleware.php';
$user = Auth::getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit;
}
$rawRedirect = trim((string)($_GET['redirect'] ?? $_POST['redirect'] ?? ''));
$allowedRedirects = ['index.php', 'qa-dashboard.php', 'qa-dashboard.html', 'dropdown-qa.html', 'dropdown-qa.php'];
$cleanTarget = basename(parse_url($rawRedirect, PHP_URL_PATH) ?? '');
$redirectTarget = in_array($cleanTarget, $allowedRedirects, true) ? $cleanTarget : 'index.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $error = 'Your session token expired. Refresh this page and try again.';
    } else {
        $result = Auth::changePassword($user['id'], (string)($_POST['current_password'] ?? ''), (string)($_POST['new_password'] ?? ''), (string)($_POST['confirm_password'] ?? ''));
        if ($result['success']) {
            header('Location: ' . $redirectTarget);
            exit;
        }
        $error = $result['error'];
    }
}
header('Cache-Control: no-store');
$token = Auth::generateCsrfToken();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Change Your Password — Bantay Aso</title>
    <link rel="icon" href="assets/img/bantay-aso-logo.jpg" type="image/jpeg">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>main{min-height:100vh;display:grid;place-items:center;padding:1.5rem}.password-card{width:100%;max-width:460px;padding:2rem;background:var(--bg-surface);border:1px solid var(--border-medium);border-radius:var(--radius-xl)}h1{font-size:1.6rem;margin-bottom:.75rem}.intro{color:var(--text-secondary);line-height:1.6;margin-bottom:1.5rem}.error{color:var(--accent-rose);margin-bottom:1rem}</style>
</head>
<body><main><section class="password-card" aria-labelledby="title">
    <h1 id="title">Choose a new password</h1>
    <p class="intro">You must replace your temporary password before accessing Bantay Aso. Use at least 8 characters and choose a different password.</p>
    <?php if ($error !== ''): ?><p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <form method="post" action="change-password.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectTarget, ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-group"><label for="current">Temporary or current password</label><input class="form-control" type="password" id="current" name="current_password" autocomplete="current-password" required autofocus></div>
        <div class="form-group"><label for="new">New password</label><input class="form-control" type="password" id="new" name="new_password" autocomplete="new-password" minlength="8" required></div>
        <div class="form-group"><label for="confirm">Confirm new password</label><input class="form-control" type="password" id="confirm" name="confirm_password" autocomplete="new-password" minlength="8" required></div>
        <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center">Save new password</button>
    </form>
</section></main></body></html>
