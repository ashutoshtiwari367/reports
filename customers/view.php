<?php
require_once __DIR__ . '/../includes/header.php';
global $pdo;

$id = (int)($_GET['id'] ?? 0);
if(!$id) die('Invalid ID');

$cust = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$cust->execute([$id]);
$c = $cust->fetch();
if(!$c) die('Customer not found');

$loans = $pdo->prepare("
    SELECT l.*, s.name as shop_name 
    FROM loans l 
    JOIN shops s ON l.shop_id = s.id 
    WHERE l.customer_id = ? 
    ORDER BY l.created_at DESC
");
$loans->execute([$id]);
$history = $loans->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= htmlspecialchars($c['name']) ?></h1>
        <p class="page-subtitle">Customer Profile & Loan History</p>
    </div>
    <div class="flex gap-3">
        <a href="/emi/loans/add.php?customer_id=<?= $c['id'] ?>" class="btn btn-success">New Loan</a>
        <a href="index.php" class="btn btn-outline">Back to List</a>
    </div>
</div>

<div class="form-grid mb-4">
    <div class="card">
        <div class="card-body">
            <h3 class="mb-4 text-muted" style="font-size:12px;text-transform:uppercase;">Contact Info</h3>
            <p><strong>Phone:</strong> <?= htmlspecialchars($c['phone']) ?></p>
            <?php if($c['alternate_phone']): ?>
            <p><strong>Alt Phone:</strong> <?= htmlspecialchars($c['alternate_phone']) ?></p>
            <?php endif; ?>
            <p class="mt-4"><strong>City:</strong> <?= htmlspecialchars($c['city'] ?: 'N/A') ?></p>
            <p><strong>Address:</strong> <?= nl2br(htmlspecialchars($c['address'] ?: 'N/A')) ?></p>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <h3 class="mb-4 text-muted" style="font-size:12px;text-transform:uppercase;">Identity</h3>
            <p><strong>Proof Type:</strong> <?= htmlspecialchars($c['id_proof_type'] ?: 'Not Provided') ?></p>
            <p><strong>Proof No:</strong> <?= htmlspecialchars($c['id_proof_number'] ?: 'Not Provided') ?></p>
            <p class="mt-4"><strong>Registered:</strong> <?= date('d M, Y', strtotime($c['created_at'])) ?></p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Loan History (<?= count($history) ?>)</h3>
    </div>
    <div class="table-wrapper">
        <?php if(empty($history)): ?>
            <div class="empty-state">
                <p>No loans found for this customer.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Loan No.</th>
                        <th>Shop</th>
                        <th>Item</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($history as $l): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($l['loan_number']) ?></strong></td>
                        <td><?= htmlspecialchars($l['shop_name']) ?></td>
                        <td><?= htmlspecialchars($l['item_name']) ?></td>
                        <td><?= formatINR($l['total_price']) ?></td>
                        <td><?= statusBadge($l['status']) ?></td>
                        <td class="text-right">
                            <a href="/emi/loans/view.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline">View Details</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
