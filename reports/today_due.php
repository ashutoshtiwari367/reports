<?php
require_once __DIR__ . '/../includes/header.php';
global $pdo;

$today = date('Y-m-d');
$due = $pdo->query("
    SELECT e.*, l.loan_number, c.name as customer_name, c.phone, s.name as shop_name 
    FROM emi_schedule e
    JOIN loans l ON e.loan_id = l.id
    JOIN customers c ON l.customer_id = c.id
    JOIN shops s ON l.shop_id = s.id
    WHERE e.due_date = '$today' AND e.status IN ('due', 'partial')
    ORDER BY s.name ASC, c.name ASC
")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Today's Due EMIs</h1>
        <p class="page-subtitle">Schedule for <?= date('d M, Y') ?></p>
    </div>
    <div class="flex gap-3">
        <div class="search-bar">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" class="search-input" data-table="dueTable" placeholder="Search...">
        </div>
        <a href="index.php" class="btn btn-outline">Back to Reports</a>
    </div>
</div>

<div class="card">
    <div class="table-wrapper">
        <table id="dueTable">
            <thead>
                <tr>
                    <th>Shop</th>
                    <th>Loan No.</th>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Amount Due</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($due)): ?>
                <tr><td colspan="7" class="text-center text-muted" style="padding:40px;">No EMIs due today!</td></tr>
                <?php endif; ?>
                
                <?php foreach($due as $emi): ?>
                <tr>
                    <td><?= htmlspecialchars($emi['shop_name']) ?></td>
                    <td><strong><a href="/emi/loans/view.php?id=<?= $emi['loan_id'] ?>" style="color:var(--accent)"><?= htmlspecialchars($emi['loan_number']) ?></a></strong></td>
                    <td><?= htmlspecialchars($emi['customer_name']) ?></td>
                    <td><?= htmlspecialchars($emi['phone']) ?></td>
                    <td class="text-bold text-danger"><?= formatINR($emi['emi_amount'] - $emi['paid_amount']) ?></td>
                    <td><?= statusBadge($emi['status']) ?></td>
                    <td>
                        <div class="flex gap-2">
                            <button class="btn btn-sm btn-success" onclick="openPaymentModal(<?= $emi['id'] ?>, <?= $emi['emi_amount'] - $emi['paid_amount'] ?>)">Mark Paid</button>
                            <a href="<?= whatsappLink($emi['phone'], "Hello {$emi['customer_name']}, this is a reminder for your EMI of ".formatINR($emi['emi_amount'])." due today.") ?>" target="_blank" class="btn btn-sm btn-whatsapp">
                                Reminder
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../emis/payment_modal.php'; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
