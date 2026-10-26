<?php
/**
 * Bantay Aso — Dedicated Sign-In Page
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_middleware.php';

$error = '';
$currentUser = Auth::getCurrentUser();

$rawRedirect = trim((string)($_GET['redirect'] ?? $_POST['redirect'] ?? ''));
$allowedRedirects = ['index.php', 'qa-dashboard.php', 'qa-dashboard.html', 'dropdown-qa.html', 'dropdown-qa.php'];
$cleanTarget = basename(parse_url($rawRedirect, PHP_URL_PATH) ?? '');
$redirectTarget = in_array($cleanTarget, $allowedRedirects, true) ? $cleanTarget : 'index.php';

if ($currentUser) {
    if (!empty($currentUser['must_change_password'])) {
        header('Location: change-password.php' . ($redirectTarget !== 'index.php' ? '?redirect=' . urlencode($redirectTarget) : ''));
    } else {
        header('Location: ' . $redirectTarget);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $clientIp = Auth::getClientIp();

    if (!Auth::checkRateLimit($clientIp, 5, 15)) {
        http_response_code(429);
        $error = 'Too many failed login attempts. Please wait 15 minutes before trying again.';
    } else {
        $result = Auth::login($username, $password);
        if ($result['success']) {
            Auth::clearAttempts($clientIp);
            if (!empty($result['user']['must_change_password'])) {
                header('Location: change-password.php' . ($redirectTarget !== 'index.php' ? '?redirect=' . urlencode($redirectTarget) : ''));
            } else {
                header('Location: ' . $redirectTarget);
            }
            exit;
        } else {
            Auth::recordFailedAttempt($clientIp, $username);
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="icon" href="assets/img/bantay-aso-logo.jpg" type="image/jpeg">
    <link rel="stylesheet" href="assets/css/helix.css?v=<?= filemtime(__DIR__ . '/assets/css/helix.css') ?>">
    <link rel="stylesheet" href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/assets/css/app.css') ?>">
    <style>
        .login-card-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .login-card {
            background: rgba(10, 18, 32, 0.94);
            border: 1px solid var(--hx-line);
            width: 100%;
            max-width: 440px;
            padding: 2.5rem 2.25rem 2.25rem;
            position: relative;
        }
        /* H3LIX HUD corner accents */
        .login-card::before, .login-card::after {
            content: "";
            position: absolute;
            width: 18px;
            height: 18px;
            pointer-events: none;
        }
        .login-card::before {
            top: -1px;
            left: -1px;
            border-top: 2px solid var(--hx-cyan);
            border-left: 2px solid var(--hx-cyan);
        }
        .login-card::after {
            bottom: -1px;
            right: -1px;
            border-bottom: 2px solid var(--hx-cyan);
            border-right: 2px solid var(--hx-cyan);
        }
    </style>
</head>
<body class="hx-auth">
    <div class="login-card-container">
        <div class="login-card hx-auth__panel">
            <div class="hx-auth__brand" style="text-align: center; margin-bottom: 2rem;">
                <div class="hx-lockup hx-lockup--lg" style="justify-content: center; margin-bottom: 0.75rem;">
                    <img class="hx-lockup__mark" src="assets/img/h3lix-emblem.svg" alt="S3curity H3LIX" width="48" height="48" onerror="this.outerHTML='<span style=\'font-size:2rem;\'>🛡️</span>';">
                    <div class="hx-lockup__text" style="text-align: left;">
                        <span class="hx-lockup__parent">S3curity</span>
                        <span class="hx-lockup__product" style="font-size: 1.45rem;">BANTAY <b>ASO</b></span>
                    </div>
                </div>
                <p class="hx-auth__tagline" style="margin: 0; color: var(--hx-muted); font-size: 0.72rem; letter-spacing: 0.16em;">// Continuous QA & Semantic Changelog Sentinel</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="hx-alert hx-alert--danger" style="margin-bottom: 1.5rem;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="hx-form">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirectTarget) ?>">
                <div class="form-group hx-field">
                    <label class="hx-label" for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control hx-input" placeholder="Enter username" required autofocus>
                </div>
                <div class="form-group hx-field">
                    <label class="hx-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control hx-input" placeholder="••••••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary hx-btn hx-btn--primary hx-btn--block" style="width: 100%; justify-content: center; margin-top: 1.25rem;">Sign In</button>
            </form>
        </div>
    </div>
</body>
</html>
