<?php
require_once __DIR__ . '/includes/header.php';
global $pdo;

// RBAC shop filter
$shopFilter = '';
$shopJoinFilter = '';
if (isShopAdmin()) {
    $sid = (int)getShopId();
    $shopFilter      = "AND shop_id = $sid";
    $shopJoinFilter  = "AND l.shop_id = $sid";
}

// Quick Stats
$stats = [
    'shops'            => isSuperAdmin()
                            ? $pdo->query("SELECT COUNT(*) FROM shops WHERE is_active=1")->fetchColumn()
                            : 1,
    'customers'        => $pdo->query("SELECT COUNT(*) FROM customers WHERE is_active=1 $shopFilter")->fetchColumn(),
    'active_loans'     => $pdo->query("SELECT COUNT(*) FROM loans WHERE status='active' $shopFilter")->fetchColumn(),
    'total_collection' => $pdo->query("SELECT COALESCE(SUM(p.amount),0) FROM emi_payments p JOIN emi_schedule e ON p.emi_id=e.id JOIN loans l ON e.loan_id=l.id WHERE 1=1 $shopJoinFilter")->fetchColumn(),
    'total_emi'        => $pdo->query("SELECT COALESCE(SUM(e.emi_amount),0) FROM emi_schedule e JOIN loans l ON e.loan_id=l.id WHERE 1=1 $shopJoinFilter")->fetchColumn(),
    'total_pending'    => $pdo->query("SELECT COALESCE(SUM(e.emi_amount - e.paid_amount),0) FROM emi_schedule e JOIN loans l ON e.loan_id=l.id WHERE e.status IN ('due','partial','overdue') $shopJoinFilter")->fetchColumn(),
];

// Recent EMIs Due Today
$today = date('Y-m-d');
$dueToday = $pdo->query("
    SELECT e.*, l.loan_number, c.name as customer_name, c.phone 
    FROM emi_schedule e
    JOIN loans l ON e.loan_id = l.id
    JOIN customers c ON l.customer_id = c.id
    WHERE e.due_date = '$today' AND e.status IN ('due', 'partial') $shopJoinFilter
    ORDER BY e.status DESC
    LIMIT 10
")->fetchAll();

?>

<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard Overview</h1>
        <p class="page-subtitle">Welcome back, <?= htmlspecialchars($user['name'] ?? 'Admin') ?>. Here's a summary of your EMI business.</p>
    </div>
    <div class="flex gap-3">
        <a href="/emi/loans/add.php" class="btn btn-primary">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Sale / Loan
        </a>
    </div>
</div>

<div class="dashboard-section-label">Business Summary</div>
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon indigo">
            <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
        </div>
        <div>
            <div class="stat-value"><?= number_format($stats['shops']) ?></div>
            <div class="stat-label">Total Shops</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div>
            <div class="stat-value"><?= number_format($stats['customers']) ?></div>
            <div class="stat-label">Total Customers</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber">
            <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
        </div>
        <div>
            <div class="stat-value"><?= number_format($stats['active_loans']) ?></div>
            <div class="stat-label">Active Loans</div>
        </div>
    </div>
</div>

<div class="dashboard-section-label">Financial Performance</div>
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon purple">
            <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
        </div>
        <div>
            <div class="stat-value"><?= formatINR($stats['total_emi']) ?></div>
            <div class="stat-label">Total Expected EMI</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <svg viewBox="0 0 24 24"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div>
            <div class="stat-value"><?= formatINR($stats['total_collection']) ?></div>
            <div class="stat-label">Total Collected</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div>
            <div class="stat-value"><?= formatINR($stats['total_pending']) ?></div>
            <div class="stat-label">Total Pending</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Due Today (<?= count($dueToday) ?>)</h3>
        <a href="/emi/reports/today_due.php" class="btn btn-sm btn-ghost">View Full Report</a>
    </div>
    <div class="table-wrapper">
        <?php if(empty($dueToday)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <h3>No EMIs due today!</h3>
                <p>All customers are up to date for today.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Loan #</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Installment</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($dueToday as $emi): ?>
                    <tr>
                        <td><strong><a href="/emi/loans/view.php?id=<?= $emi['loan_id'] ?>" style="color:var(--accent)"><?= htmlspecialchars($emi['loan_number']) ?></a></strong></td>
                        <td><?= htmlspecialchars($emi['customer_name']) ?></td>
                        <td><?= htmlspecialchars($emi['phone']) ?></td>
                        <td>#<?= $emi['installment_number'] ?></td>
                        <td class="text-bold"><?= formatINR($emi['emi_amount']) ?></td>
                        <td><?= statusBadge($emi['status']) ?></td>
                        <td>
                            <div class="flex gap-2">
                                <button class="btn btn-sm btn-success" onclick="openPaymentModal(<?= $emi['id'] ?>, <?= $emi['emi_amount'] - $emi['paid_amount'] ?>)">Pay</button>
                                <a href="<?= whatsappLink($emi['phone'], "Hello {$emi['customer_name']}, this is a reminder for your EMI of ".formatINR($emi['emi_amount'])." due today for Loan {$emi['loan_number']}.") ?>" target="_blank" class="btn btn-sm btn-whatsapp btn-icon" title="WhatsApp Reminder">
                                    <svg viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Payment Modal Component -->
<?php include __DIR__ . '/emis/payment_modal.php'; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

