<!DOCTYPE html>
<html lang="en" data-theme="<?= $_COOKIE['theme'] ?? 'light' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title ?? 'Sign In') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/app.css">
<style>
.auth-page {
    min-height: 100vh;
    display: flex;
    background: var(--bg-body);
}
.auth-left {
    flex: 1;
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px;
    position: relative;
    overflow: hidden;
}
.auth-left::before {
    content: '';
    position: absolute;
    width: 500px; height: 500px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(99,102,241,0.15) 0%, transparent 70%);
    top: -100px; right: -100px;
}
.auth-left::after {
    content: '';
    position: absolute;
    width: 400px; height: 400px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(6,182,212,0.1) 0%, transparent 70%);
    bottom: -80px; left: -80px;
}
.auth-left-content { position: relative; z-index: 1; max-width: 420px; }
.auth-brand {
    display: flex; align-items: center; gap: 12px;
    margin-bottom: 48px;
}
.auth-brand-icon {
    width: 44px; height: 44px;
    background: linear-gradient(135deg, #6366f1, #818cf8);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; font-weight: 800; color: #fff;
    box-shadow: 0 4px 16px rgba(99,102,241,0.4);
}
.auth-brand-name { font-size: 22px; font-weight: 800; color: #fff; }

.auth-tagline { font-size: 32px; font-weight: 800; color: #fff; line-height: 1.2; margin-bottom: 16px; letter-spacing: -0.5px; }
.auth-description { font-size: 15px; color: rgba(255,255,255,0.55); line-height: 1.7; }
.auth-features { margin-top: 36px; display: flex; flex-direction: column; gap: 14px; }
.auth-feature {
    display: flex; align-items: center; gap: 12px;
    font-size: 14px; color: rgba(255,255,255,0.75);
}
.auth-feature-icon {
    width: 32px; height: 32px;
    background: rgba(255,255,255,0.08);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; flex-shrink: 0;
}

.auth-right {
    width: 480px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px;
    background: var(--bg-card);
}
.auth-form-container { width: 100%; max-width: 380px; }
.auth-form-title { font-size: 26px; font-weight: 800; color: var(--text-primary); margin-bottom: 6px; letter-spacing: -0.4px; }
.auth-form-subtitle { color: var(--text-secondary); font-size: 14px; margin-bottom: 32px; }

.auth-divider {
    text-align: center;
    color: var(--text-muted);
    font-size: 12px;
    margin: 20px 0;
    position: relative;
}
.auth-divider::before, .auth-divider::after {
    content: '';
    position: absolute;
    top: 50%; width: calc(50% - 30px);
    height: 1px; background: var(--border);
}
.auth-divider::before { left: 0; }
.auth-divider::after  { right: 0; }

.error-banner {
    background: rgba(239,68,68,0.08);
    border: 1px solid rgba(239,68,68,0.2);
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 13px;
    color: var(--danger);
    margin-bottom: 20px;
    display: flex;
    gap: 8px;
    align-items: flex-start;
}

@media (max-width: 900px) {
    .auth-left { display: none; }
    .auth-right { width: 100%; }
}
</style>
</head>
<body>
<?php if(session_status() !== PHP_SESSION_ACTIVE) session_start(); ?>

<div class="auth-page">
    <!-- Left panel -->
    <div class="auth-left">
        <div class="auth-left-content">
            <div class="auth-brand">
                <div class="auth-brand-icon">AP</div>
                <span class="auth-brand-name">AccountingPro</span>
            </div>
            <h1 class="auth-tagline">Modern Accounting<br>for Modern Business</h1>
            <p class="auth-description">The complete ERP solution with multi-branch support, real-time analytics, and seamless integrations.</p>
            <div class="auth-features">
                <div class="auth-feature"><span class="auth-feature-icon">🏢</span> Multi-company & Multi-branch</div>
                <div class="auth-feature"><span class="auth-feature-icon">📊</span> Real-time financial dashboards</div>
                <div class="auth-feature"><span class="auth-feature-icon">🔗</span> REST API & Webhook support</div>
                <div class="auth-feature"><span class="auth-feature-icon">📥</span> Google Sheets data import</div>
                <div class="auth-feature"><span class="auth-feature-icon">🔒</span> Bank-grade security & JWT auth</div>
            </div>
        </div>
    </div>

    <!-- Right panel (form) -->
    <div class="auth-right">
        <div class="auth-form-container">
            <h2 class="auth-form-title">Welcome back</h2>
            <p class="auth-form-subtitle">Sign in to your AccountingPro account</p>

            <?php if(!empty($_SESSION['flash']['error'])): ?>
            <div class="error-banner">⚠️ <span><?= htmlspecialchars($_SESSION['flash']['error']) ?></span></div>
            <?php unset($_SESSION['flash']['error']); endif; ?>

            <form method="POST" action="/login" id="loginForm">
                <input type="hidden" name="_token" value="<?= \App\Core\Auth::csrfToken() ?>">

                <div class="form-group">
                    <label class="form-label required" for="email">Email Address</label>
                    <input type="email" name="email" id="email" class="form-control"
                           placeholder="you@company.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required autocomplete="email">
                </div>

                <div class="form-group">
                    <label class="form-label required" for="password">
                        Password
                        <a href="/forgot-password" style="float:right;font-size:11px;font-weight:500;">Forgot password?</a>
                    </label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" class="form-control"
                               placeholder="••••••••" required autocomplete="current-password">
                        <span class="input-addon" onclick="togglePassword()" style="cursor:pointer;" id="eyeIcon">👁</span>
                    </div>
                </div>

                <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin-bottom:24px;cursor:pointer;">
                    <input type="checkbox" name="remember" style="accent-color:var(--primary);">
                    Remember me for 30 days
                </label>

                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;" id="loginBtn">
                    <span id="loginBtnText">Sign In</span>
                    <span class="spinner-inline" id="loginSpinner" style="display:none;"></span>
                </button>
            </form>

            <div class="auth-divider">or</div>

            <div style="text-align:center; font-size:13px; color:var(--text-secondary);">
                Don't have an account?
                <a href="/register" style="font-weight:700;">Create one free →</a>
            </div>

            <div style="text-align:center; margin-top:24px; font-size:11px; color:var(--text-muted);">
                Demo: <strong>admin@accountingpro.com</strong> / <strong>Admin@123</strong>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', function() {
    document.getElementById('loginBtnText').textContent = 'Signing in...';
    document.getElementById('loginSpinner').style.display = 'inline-block';
    document.getElementById('loginBtn').disabled = true;
});

function togglePassword() {
    const p = document.getElementById('password');
    const e = document.getElementById('eyeIcon');
    p.type   = p.type === 'password' ? 'text' : 'password';
    e.textContent = p.type === 'password' ? '👁' : '🙈';
}
</script>
</body>
</html>
