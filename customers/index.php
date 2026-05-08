<?php
require_once __DIR__ . '/../includes/header.php';
global $pdo;

$shop_id = getShopId();
$where = isShopAdmin() ? "WHERE c.shop_id = " . (int)$shop_id : "";

$customers = $pdo->query("
    SELECT c.*, 
    (SELECT COUNT(*) FROM loans WHERE customer_id = c.id) as total_loans,
    (SELECT COUNT(*) FROM loans WHERE customer_id = c.id AND status='active') as active_loans
    FROM customers c 
    $where
    ORDER BY created_at DESC
")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Customers</h1>
        <p class="page-subtitle">Directory of all customers across all shops.</p>
    </div>
    <div class="flex gap-3">
        <div class="search-bar">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" class="search-input" data-table="customersTable" placeholder="Search by name or phone...">
        </div>
        <a href="add.php" class="btn btn-primary">Add Customer</a>
    </div>
</div>

<div class="card">
    <div class="table-wrapper">
        <table id="customersTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>City</th>
                    <th>Active Loans</th>
                    <th>Total Loans</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($customers as $c): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                    <td><?= htmlspecialchars($c['phone']) ?></td>
                    <td><?= htmlspecialchars($c['city']) ?></td>
                    <td>
                        <?php if($c['active_loans'] > 0): ?>
                            <span class="badge badge-warning"><?= $c['active_loans'] ?> Active</span>
                        <?php else: ?>
                            <span class="text-muted">None</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $c['total_loans'] ?></td>
                    <td class="text-right">
                        <a href="view.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline">Profile</a>
                        <a href="/emi/loans/add.php?customer_id=<?= $c['id'] ?>" class="btn btn-sm btn-success">New Loan</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
