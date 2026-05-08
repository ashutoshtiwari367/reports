<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$overdueCount  = getOverdueCount();
$todayDueCount = getTodayDueCount();
$currentPage   = basename($_SERVER['PHP_SELF'], '.php');
$user          = currentUser();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMI Tracker – <?= ucfirst($currentPage) ?></title>
    <meta name="description" content="Manual EMI tracking system for multi-shop management">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/emi/assets/css/style.css">
</head>
<body>
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">₹</div>
            <div class="brand-text">
                <span class="brand-name">EMI Tracker</span>
                <span class="brand-sub">Management System</span>
            </div>
            <button class="sidebar-close" onclick="toggleSidebar()">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-label">Main</div>
            <a href="/emi/dashboard.php" class="nav-item <?= $currentPage==='dashboard'?'active':'' ?>">
                <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Dashboard
            </a>
            <?php if (isSuperAdmin()): ?>
            <a href="/emi/shops/index.php" class="nav-item <?= $currentPage==='index'&&strpos($_SERVER['REQUEST_URI'],'shops')!==false?'active':'' ?>">
                <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                Shops
            </a>
            <a href="/emi/users/index.php" class="nav-item <?= strpos($_SERVER['REQUEST_URI'],'users')!==false?'active':'' ?>">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Users
            </a>
            <?php endif; ?>

            <div class="nav-section-label">Operations</div>
            <a href="/emi/loans/index.php" class="nav-item <?= strpos($_SERVER['REQUEST_URI'],'loans')!==false?'active':'' ?>">
                <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Loans / Sales
            </a>
            <a href="/emi/reports/today_due.php" class="nav-item <?= $currentPage==='today_due'?'active':'' ?>">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Today's Due
                <?php if ($todayDueCount > 0): ?>
                    <span class="badge-count"><?= $todayDueCount ?></span>
                <?php endif; ?>
            </a>

            <?php if (isSuperAdmin()): ?>
            <div class="nav-section-label">Reports</div>
            <a href="/emi/reports/index.php" class="nav-item <?= strpos($_SERVER['REQUEST_URI'],'reports')!==false?'active':'' ?>">
                <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                Reports
                <?php if ($overdueCount > 0): ?>
                    <span class="badge-count badge-red"><?= $overdueCount ?></span>
                <?php endif; ?>
            </a>
            <a href="/emi/exports/export_excel.php" class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Export Excel
            </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($user['name'] ?? 'A', 0, 1)) ?></div>
                <div class="user-details">
                    <span class="user-name"><?= htmlspecialchars($user['name'] ?? 'Admin') ?></span>
                    <span class="user-role"><?= ucfirst($user['role'] ?? 'admin') ?></span>
                </div>
            </div>
            <a href="/emi/auth/logout.php" class="logout-btn" title="Logout">
                <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </a>
        </div>
    </aside>

    <!-- Main content -->
    <div class="main-content">
        <header class="topbar">
            <button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()">
                <svg viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <div class="topbar-right">
                <span class="topbar-date"><?= date('D, d M Y') ?></span>
            </div>
        </header>

        <script>
            function toggleSidebar() {
                document.getElementById('sidebar').classList.toggle('open');
                document.getElementById('sidebarOverlay').classList.toggle('active');
            }
        </script>

        <div class="page-content">
            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
            <?php endif; ?>
