<?php
require_once __DIR__ . '/../includes/header.php';
global $pdo;

$shops = $pdo->query("
    SELECT s.name, 
        COUNT(l.id) as total_loans,
        SUM(CASE WHEN l.status='active' THEN 1 ELSE 0 END) as active_loans,
        COALESCE(SUM(l.total_price), 0) as total_sales,
        COALESCE(SUM(l.down_payment), 0) as total_down_payment,
        COALESCE(SUM(l.total_price - l.down_payment), 0) as total_financed
    FROM shops s
    LEFT JOIN loans l ON s.id = l.shop_id
    GROUP BY s.id
    ORDER BY total_sales DESC
")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Shop-wise Summary</h1>
        <p class="page-subtitle">Overall performance comparison across branches.</p>
    </div>
    <a href="index.php" class="btn btn-outline">Back to Reports</a>
</div>

<div class="card">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Shop Name</th>
                    <th>Total Loans</th>
                    <th>Active Loans</th>
                    <th>Total Sales</th>
                    <th>Total Down Payment</th>
                    <th>Total Financed</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($shops)): ?>
                <tr><td colspan="6" class="text-center text-muted" style="padding:40px;">No shop data available.</td></tr>
                <?php endif; ?>
                
                <?php foreach($shops as $row): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                    <td><?= $row['total_loans'] ?></td>
                    <td><span class="badge badge-warning"><?= $row['active_loans'] ?></span></td>
                    <td class="text-bold"><?= formatINR($row['total_sales']) ?></td>
                    <td><?= formatINR($row['total_down_payment']) ?></td>
                    <td class="text-info"><?= formatINR($row['total_financed']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
