<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) die('Invalid ID');

global $pdo;
$stmt = $pdo->prepare("SELECT * FROM shops WHERE id = ?");
$stmt->execute([$id]);
$shop = $stmt->fetch();
if (!$shop) die('Shop not found');

// Stats for this specific shop
$stats = [
    'active_loans' => $pdo->query("SELECT COUNT(*) FROM loans WHERE shop_id=$id AND status='active'")->fetchColumn(),
    'total_sales' => $pdo->query("SELECT COALESCE(SUM(total_price),0) FROM loans WHERE shop_id=$id")->fetchColumn(),
    'total_collection' => $pdo->query("
        SELECT COALESCE(SUM(p.amount),0) 
        FROM emi_payments p 
        JOIN emi_schedule e ON p.emi_id = e.id 
        JOIN loans l ON e.loan_id = l.id 
        WHERE l.shop_id=$id
    ")->fetchColumn(),
];

$today = date('Y-m-d');
$dueToday = $pdo->query("
    SELECT e.*, l.loan_number, c.name as customer_name, c.phone 
    FROM emi_schedule e
    JOIN loans l ON e.loan_id = l.id
    JOIN customers c ON l.customer_id = c.id
    WHERE l.shop_id = $id AND e.due_date = '$today' AND e.status IN ('due', 'partial')
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= htmlspecialchars($shop['name']) ?> Dashboard</h1>
        <p class="page-subtitle"><?= htmlspecialchars($shop['city']) ?> • <?= htmlspecialchars($shop['owner_name']) ?></p>
    </div>
    <div class="flex gap-3">
        <a href="/emi/loans/add.php?shop_id=<?= $shop['id'] ?>" class="btn btn-primary">New Sale</a>
        <a href="index.php" class="btn btn-outline">Back to Shops</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon amber">
            <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div>
            <div class="stat-value"><?= number_format($stats['active_loans']) ?></div>
            <div class="stat-label">Active Loans</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon indigo">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </div>
        <div>
            <div class="stat-value"><?= formatINR($stats['total_sales']) ?></div>
            <div class="stat-label">Total Sales</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div>
            <div class="stat-value"><?= formatINR($stats['total_collection']) ?></div>
            <div class="stat-label">Total Collection</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Shop Dues Today (<?= count($dueToday) ?>)</h3>
    </div>
    <div class="table-wrapper">
        <?php if(empty($dueToday)): ?>
            <div class="empty-state">
                <p>No EMIs due today for this shop.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Loan #</th>
                        <th>Customer</th>
                        <th>Phone</th>
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
                        <td class="text-bold"><?= formatINR($emi['emi_amount']) ?></td>
                        <td><?= statusBadge($emi['status']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-success" onclick="openPaymentModal(<?= $emi['id'] ?>, <?= $emi['emi_amount'] - $emi['paid_amount'] ?>)">Mark Paid</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../emis/payment_modal.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
