<?php
// views/layout/header.php
$user       = \App\Core\Auth::user();
$companyId  = \App\Core\Auth::companyId();
$company    = $companyId ? \App\Core\Database::fetch("SELECT * FROM companies WHERE id=?", [$companyId]) : null;
$branches   = $companyId ? \App\Models\Branch::allForCompany($companyId) : [];
$activeBranch = !empty($_SESSION['branch_id'])
    ? \App\Core\Database::fetch("SELECT * FROM branches WHERE id=?", [$_SESSION['branch_id']])
    : ($branches[0] ?? null);

$currentUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$isActive   = fn(string $prefix) => str_starts_with($currentUri, $prefix) ? 'active' : '';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$flash = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);

$theme = $_COOKIE['theme'] ?? 'light';
?><!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="AccountingPro — Modern Accounting & ERP SaaS">
<title><?= htmlspecialchars($title ?? 'AccountingPro') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="stylesheet" href="<?= asset('css/tour.css') ?>">
<script src="https://code.jquery.com/jquery-3.7.1.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts" defer></script>
<script src="<?= asset('js/tour.js') ?>" defer></script>
</head>
<?php
// Determine which tour to attach to this page
$tourKey = match(true) {
    str_starts_with($currentUri, '/dashboard') || $currentUri === '/' => 'dashboard',
    str_starts_with($currentUri, '/reports') => 'reports',
    str_starts_with($currentUri, '/import')  => 'import',
    str_starts_with($currentUri, '/roles')   => 'roles',
    str_starts_with($currentUri, '/users')   => 'users',
    default => '',
};
?>
<body
    class="<?= $_COOKIE['sidebar_collapsed'] ?? '' === '1' ? 'sidebar-collapsed' : '' ?>"
    data-tour="<?= $tourKey ?>"
    data-theme="<?= htmlspecialchars($theme) ?>"
>

<!-- ── Sidebar ──────────────────────────────────────────────── -->
<div class="sidebar" id="sidebar">
    <!-- Logo -->
    <div class="sidebar-logo">
        <div class="sidebar-logo-icon">AP</div>
        <span class="sidebar-logo-text">AccountingPro</span>
    </div>

    <!-- Company + Branch Context -->
    <?php if ($company): ?>
    <div class="sidebar-context">
        <div class="context-label">Company</div>
        <div class="context-selector" onclick="toggleCompanyMenu()">
            <div class="context-selector-icon"><?= strtoupper(substr($company['name'], 0, 2)) ?></div>
            <span class="context-selector-name"><?= htmlspecialchars($company['name']) ?></span>
            <span class="context-selector-chevron">▾</span>
        </div>

        <?php if (count($branches) > 1): ?>
        <div class="context-label" style="margin-top:10px;">Branch</div>
        <div class="context-selector" id="branchSelector" onclick="toggleBranchDropdown()">
            <div class="context-selector-icon" style="background:linear-gradient(135deg,#06b6d4,#0891b2);">
                <?= strtoupper(substr($activeBranch['code'] ?? 'HQ', 0, 2)) ?>
            </div>
            <span class="context-selector-name"><?= htmlspecialchars($activeBranch['name'] ?? 'All Branches') ?></span>
            <span class="context-selector-chevron">▾</span>
        </div>
        <!-- Branch dropdown -->
        <div id="branchDropdown" style="display:none;margin-top:6px;">
            <?php foreach($branches as $b): ?>
            <div onclick="switchBranch(<?= $b['id'] ?>, '<?= htmlspecialchars($b['name']) ?>')"
                 style="padding:8px 10px;border-radius:8px;cursor:pointer;font-size:12px;color:var(--text-sidebar);
                        display:flex;align-items:center;gap:8px;transition:background 0.2s;"
                 onmouseover="this.style.background='rgba(255,255,255,0.08)'"
                 onmouseout="this.style.background=''"
            >
                <span style="font-size:10px;font-weight:700;background:var(--primary);padding:2px 5px;border-radius:4px;color:#fff;"><?= htmlspecialchars($b['code']) ?></span>
                <?= htmlspecialchars($b['name']) ?>
                <?php if(($activeBranch['id'] ?? 0) === $b['id']): ?>
                    <span style="margin-left:auto;color:var(--success);font-size:12px;">✓</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <div class="nav-group">
            <div class="nav-group-label">Main</div>

            <a href="/dashboard" class="nav-item <?= $isActive('/dashboard') ?: ($currentUri === '/' ? 'active' : '') ?>">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Dashboard</span>
            </a>
        </div>

        <div class="nav-group">
            <div class="nav-group-label">Sales</div>

            <a href="/invoices" class="nav-item <?= $isActive('/invoices') ?>">
                <span class="nav-icon">🧾</span>
                <span class="nav-text">Invoices</span>
            </a>
            <a href="/customers" class="nav-item <?= $isActive('/customers') ?>">
                <span class="nav-icon">👥</span>
                <span class="nav-text">Customers</span>
            </a>
        </div>

        <div class="nav-group">
            <div class="nav-group-label">Purchases</div>

            <a href="/vendors" class="nav-item <?= $isActive('/vendors') ?>">
                <span class="nav-icon">🏭</span>
                <span class="nav-text">Vendors</span>
            </a>
            <a href="/products" class="nav-item <?= $isActive('/products') ?>">
                <span class="nav-icon">📦</span>
                <span class="nav-text">Products</span>
            </a>
        </div>

        <div class="nav-group">
            <div class="nav-group-label">Finance</div>

            <a href="/expenses" class="nav-item <?= $isActive('/expenses') ?>">
                <span class="nav-icon">💸</span>
                <span class="nav-text">Expenses</span>
            </a>
            <a href="/accounting" class="nav-item <?= $isActive('/accounting') ?>">
                <span class="nav-icon">📒</span>
                <span class="nav-text">Accounting</span>
            </a>
            <a href="/reports" class="nav-item <?= $isActive('/reports') ?>">
                <span class="nav-icon">📊</span>
                <span class="nav-text">360° Reports</span>
                <span class="nav-badge" style="background:var(--secondary);color:#fff;font-size:9px;">NEW</span>
            </a>
        </div>

        <div class="nav-group">
            <div class="nav-group-label">Organisation</div>

            <a href="/branches" class="nav-item <?= $isActive('/branches') ?>">
                <span class="nav-icon">🏢</span>
                <span class="nav-text">Branches</span>
            </a>
            <a href="/users" class="nav-item <?= $isActive('/users') ?>">
                <span class="nav-icon">👥</span>
                <span class="nav-text">Users</span>
            </a>
            <a href="/roles" class="nav-item <?= $isActive('/roles') ?>">
                <span class="nav-icon">🔐</span>
                <span class="nav-text">Roles & Permissions</span>
            </a>
        </div>

        <div class="nav-group">
            <div class="nav-group-label">Tools</div>

            <a href="/import/new" class="nav-item <?= $isActive('/import') ?>">
                <span class="nav-icon">📥</span>
                <span class="nav-text">Data Import</span>
            </a>
            <a href="/webhooks" class="nav-item <?= $isActive('/webhooks') ?>">
                <span class="nav-icon">🔗</span>
                <span class="nav-text">Webhooks</span>
            </a>
            <a href="/settings" class="nav-item <?= $isActive('/settings') ?>">
                <span class="nav-icon">⚙️</span>
                <span class="nav-text">Settings</span>
            </a>
        </div>
    </nav>

    <!-- Sidebar footer (user) -->
    <div class="sidebar-footer">
        <div class="nav-item" style="cursor:default;">
            <span class="nav-icon" style="background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;font-size:12px;font-weight:700;">
                <?= strtoupper(substr($user['name'] ?? 'U', 0, 2)) ?>
            </span>
            <div class="nav-text" style="min-width:0;">
                <div style="font-size:12px;font-weight:700;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($user['name'] ?? '') ?></div>
                <div style="font-size:10px;color:var(--text-muted);text-transform:capitalize;"><?= $user['role'] ?? '' ?></div>
            </div>
            <a href="/logout" title="Logout" style="color:var(--text-muted);font-size:16px;padding:4px;flex-shrink:0;">⎋</a>
        </div>
    </div>
</div>

<!-- Sidebar toggle -->
<button class="sidebar-toggle-btn" id="sidebarToggleBtn" onclick="toggleSidebar()" title="Toggle sidebar">
    <span id="toggleIcon">‹</span>
</button>

<!-- ── Main ──────────────────────────────────────────────────── -->
<div class="app-wrapper">
<div class="main-content" id="mainContent">

    <!-- Topbar -->
    <header class="topbar">
        <!-- Mobile hamburger -->
        <button onclick="toggleMobileSidebar()" style="display:none;background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-secondary);" id="mobileMenuBtn">☰</button>

        <!-- Breadcrumb -->
        <div class="topbar-breadcrumb">
            <span>AccountingPro</span>
            <span class="bc-sep">/</span>
            <span class="bc-current"><?= htmlspecialchars($breadcrumb ?? ucfirst(ltrim($currentUri,'/'))) ?></span>
        </div>

        <div class="topbar-actions">
            <!-- Active branch pill -->
            <?php if($activeBranch): ?>
            <div class="branch-pill" onclick="toggleBranchDropdown()">
                <span class="branch-dot"></span>
                <?= htmlspecialchars($activeBranch['name']) ?>
                <span>▾</span>
            </div>
            <?php endif; ?>

            <!-- Search -->
            <div class="topbar-search">
                <span class="topbar-search-icon">🔍</span>
                <input type="text" placeholder="Search..." id="globalSearch">
            </div>

            <!-- Notifications -->
            <div class="dropdown">
                <button class="topbar-btn" id="notifBtn">
                    🔔
                    <?php if(!empty($notifications)): ?>
                    <span class="notif-dot"></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu notif-panel" id="notifMenu">
                    <div style="padding:12px 14px;font-weight:700;font-size:13px;border-bottom:1px solid var(--border);">Notifications</div>
                    <?php if(empty($notifications ?? [])): ?>
                    <div class="empty-state" style="padding:30px;">
                        <div class="empty-icon" style="font-size:28px;">🔔</div>
                        <div>No new notifications</div>
                    </div>
                    <?php else: ?>
                    <?php foreach(($notifications ?? []) as $n): ?>
                    <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>">
                        <div class="notif-dot-indicator"></div>
                        <div>
                            <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($n['title']) ?></div>
                            <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($n['body'] ?? '') ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Help / Tour dropdown -->
            <div class="dropdown" id="helpDropdown">
                <button class="tour-launcher" id="helpBtn" onclick="document.getElementById('helpMenu').classList.toggle('open');event.stopPropagation();">
                    <span>💡</span> Help
                </button>
                <div class="dropdown-menu help-dropdown" id="helpMenu">
                    <div class="dropdown-header">Help & Tours</div>
                    <?php $tourPages = ['dashboard','reports','import','roles','users']; ?>
                    <?php foreach($tourPages as $tp): ?>
                    <div class="dropdown-item" onclick="document.getElementById('helpMenu').classList.remove('open');AppTour.start('<?= $tp ?>')">
                        <?= match($tp) {
                            'dashboard' => '🏠 Dashboard Tour',
                            'reports'   => '📊 Reports Tour',
                            'import'    => '📥 Import Tour',
                            'roles'     => '🔐 Roles Tour',
                            'users'     => '👥 Users Tour',
                            default     => ucfirst($tp) . ' Tour',
                        } ?>
                    </div>
                    <?php endforeach; ?>
                    <div class="dropdown-divider"></div>
                    <div class="dropdown-item" onclick="localStorage.removeItem('ap_tours_seen');location.reload();">
                        🔄 Reset All Tours
                    </div>
                    <a class="dropdown-item" href="/settings">⚙️ Settings</a>
                </div>
            </div>

            <!-- Theme toggle -->
            <button class="theme-toggle <?= $theme === 'dark' ? 'dark' : '' ?>" id="themeToggle" title="Toggle dark mode">
                <div class="theme-toggle-knob"><?= $theme === 'dark' ? '🌙' : '☀️' ?></div>
            </button>

            <!-- Profile -->
            <div class="dropdown">
                <div class="topbar-avatar" id="profileBtn">
                    <?= strtoupper(substr($user['name'] ?? 'U', 0, 2)) ?>
                </div>
                <div class="dropdown-menu" id="profileMenu">
                    <div class="dropdown-header"><?= htmlspecialchars($user['name'] ?? '') ?></div>
                    <div class="dropdown-item" onclick="location.href='/settings'">⚙️ Settings</div>
                    <div class="dropdown-divider"></div>
                    <div class="dropdown-item danger" onclick="location.href='/logout'">⎋ Log out</div>
                </div>
            </div>
        </div>
    </header>

    <!-- Flash messages -->
    <?php if(!empty($flash['success'])): ?>
    <script>document.addEventListener('DOMContentLoaded',()=>showToast('<?= addslashes($flash['success']) ?>','success'));</script>
    <?php endif; ?>
    <?php if(!empty($flash['error'])): ?>
    <script>document.addEventListener('DOMContentLoaded',()=>showToast('<?= addslashes($flash['error']) ?>','error'));</script>
    <?php endif; ?>

    <!-- Toast container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Page content starts -->
    <main class="page-content">
