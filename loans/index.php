<?php
require_once __DIR__ . '/../includes/header.php';
global $pdo;

$shop_id = getShopId();
$where = isShopAdmin() ? "WHERE l.shop_id = " . (int)$shop_id : "";

$loans = $pdo->query("
    SELECT l.*, s.name as shop_name, c.name as customer_name, c.phone
    FROM loans l
    JOIN shops s ON l.shop_id = s.id
    JOIN customers c ON l.customer_id = c.id
    $where
    ORDER BY l.created_at DESC
")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Sales / Loans</h1>
        <p class="page-subtitle">All active and completed loans across all shops.</p>
    </div>
    <div class="flex gap-3">
        <div class="search-bar">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" class="search-input" data-table="loansTable" placeholder="Search by loan #, customer, item...">
        </div>
        <a href="add.php" class="btn btn-primary">New Sale / Loan</a>
    </div>
</div>

<div class="card">
    <div class="table-wrapper">
        <table id="loansTable">
            <thead>
                <tr>
                    <th>Loan No.</th>
                    <th>Customer</th>
                    <th>Item</th>
                    <th>Total Price</th>
                    <th>EMI</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($loans as $l): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($l['loan_number']) ?></strong></td>
                    <td>
                        <?= htmlspecialchars($l['customer_name']) ?><br>
                        <small class="text-muted"><?= htmlspecialchars($l['phone']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($l['item_name']) ?></td>
                    <td>
                        <?= formatINR($l['total_price']) ?><br>
                        <small class="text-muted">Bal: <?= formatINR($l['remaining_amount']) ?></small>
                    </td>
                    <td><?= $l['emi_months'] ?> mo × <?= formatINR($l['emi_amount']) ?></td>
                    <td><?= statusBadge($l['status']) ?></td>
                    <td class="text-right flex gap-2" style="justify-content: flex-end;">
                        <a href="view.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline">View</a>
                        <?php if(isSuperAdmin()): ?>
                            <a href="edit.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline" style="border-color:var(--accent); color:var(--accent);">Edit</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
