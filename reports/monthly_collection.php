<?php
require_once __DIR__ . '/../includes/header.php';
global $pdo;

$month = date('Y-m');
if(!empty($_GET['month'])) $month = $_GET['month'];

$collections = $pdo->prepare("
    SELECT p.payment_mode, SUM(p.amount) as total, COUNT(p.id) as count
    FROM emi_payments p
    WHERE DATE_FORMAT(p.payment_date, '%Y-%m') = ?
    GROUP BY p.payment_mode
");
$collections->execute([$month]);
$data = $collections->fetchAll();

$totalMonth = 0;
foreach($data as $d) $totalMonth += $d['total'];
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Monthly Collection</h1>
        <p class="page-subtitle">Summary for <?= date('F Y', strtotime($month . '-01')) ?></p>
    </div>
    <div class="flex gap-3">
        <form method="GET" class="flex gap-2">
            <input type="month" name="month" value="<?= htmlspecialchars($month) ?>" class="search-input" onchange="this.form.submit()">
        </form>
        <a href="index.php" class="btn btn-outline">Back to Reports</a>
    </div>
</div>

<div class="card" style="max-width: 600px;">
    <div class="card-header">
        <h3 class="card-title">Total Collected: <span class="text-success"><?= formatINR($totalMonth) ?></span></h3>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Payment Mode</th>
                    <th>Transactions</th>
                    <th class="text-right">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($data)): ?>
                <tr><td colspan="3" class="text-center text-muted" style="padding:40px;">No collections recorded in this month.</td></tr>
                <?php endif; ?>
                
                <?php foreach($data as $row): ?>
                <tr>
                    <td style="text-transform: capitalize; font-weight:600;"><?= htmlspecialchars($row['payment_mode']) ?></td>
                    <td><?= $row['count'] ?></td>
                    <td class="text-right text-bold"><?= formatINR($row['total']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
