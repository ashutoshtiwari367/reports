<?php
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Reports & Analytics</h1>
        <p class="page-subtitle">Track collections, dues, and business performance.</p>
    </div>
</div>

<div class="report-grid">
    <a href="today_due.php" class="report-card">
        <div class="report-icon" style="background: rgba(245,158,11,0.1); color: var(--warning);">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div class="report-title">Today's Due EMIs</div>
        <div class="report-desc">List of all EMIs scheduled to be paid today. Ideal for calling customers.</div>
    </a>
    
    <a href="monthly_collection.php" class="report-card">
        <div class="report-icon" style="background: rgba(16,185,129,0.1); color: var(--success);">
            <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="report-title">Monthly Collection</div>
        <div class="report-desc">Total amount collected in the current month categorized by payment mode.</div>
    </a>
    
    <a href="shopwise.php" class="report-card">
        <div class="report-icon" style="background: rgba(99,102,241,0.1); color: var(--accent);">
            <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
        </div>
        <div class="report-title">Shop-wise Summary</div>
        <div class="report-desc">Compare performance, active loans, and collections across all your branches.</div>
    </a>
    
    <a href="overdue.php" class="report-card">
        <div class="report-icon" style="background: rgba(239,68,68,0.1); color: var(--danger);">
            <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div class="report-title">Defaulters / Overdue</div>
        <div class="report-desc">Identify customers who have missed their EMI dates. Send WhatsApp reminders.</div>
    </a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
