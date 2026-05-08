<?php
require_once __DIR__ . '/../includes/header.php';
global $pdo;

$today = date('Y-m-d');
// Note: We use due_date < today AND status='due' rather than rely only on the 'overdue' status word,
// as the status column may not be fully synchronized by cron job yet (since we are doing a manual tracking SaaS without background triggers).
$overdue = $pdo->query("
    SELECT e.*, l.loan_number, c.name as customer_name, c.phone, s.name as shop_name,
    DATEDIFF('$today', e.due_date) as days_late
    FROM emi_schedule e
    JOIN loans l ON e.loan_id = l.id
    JOIN customers c ON l.customer_id = c.id
    JOIN shops s ON l.shop_id = s.id
    WHERE e.due_date < '$today' AND e.status IN ('due', 'partial')
    ORDER BY e.due_date ASC
")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1 class="page-title text-danger">Defaulters / Overdue</h1>
        <p class="page-subtitle">EMIs that have missed their due date.</p>
    </div>
    <div class="flex gap-3">
        <div class="search-bar">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" class="search-input" data-table="overdueTable" placeholder="Search by customer...">
        </div>
        <a href="index.php" class="btn btn-outline">Back to Reports</a>
    </div>
</div>

<div class="card border-danger">
    <div class="table-wrapper">
        <table id="overdueTable">
            <thead>
                <tr>
                    <th>Due Date</th>
                    <th>Days Late</th>
                    <th>Loan No.</th>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Amount Due</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($overdue)): ?>
                <tr><td colspan="7" class="text-center text-muted" style="padding:40px;">No overdue EMIs! Great job on collections.</td></tr>
                <?php endif; ?>
                
                <?php foreach($overdue as $emi): ?>
                <tr>
                    <td><?= date('d M, Y', strtotime($emi['due_date'])) ?></td>
                    <td><span class="badge badge-overdue"><?= $emi['days_late'] ?> Days</span></td>
                    <td><strong><a href="/emi/loans/view.php?id=<?= $emi['loan_id'] ?>" style="color:var(--accent)"><?= htmlspecialchars($emi['loan_number']) ?></a></strong></td>
                    <td><?= htmlspecialchars($emi['customer_name']) ?></td>
                    <td><?= htmlspecialchars($emi['phone']) ?></td>
                    <td class="text-bold text-danger"><?= formatINR($emi['emi_amount'] - $emi['paid_amount']) ?></td>
                    <td>
                        <div class="flex gap-2">
                            <button class="btn btn-sm btn-success" onclick="openPaymentModal(<?= $emi['id'] ?>, <?= $emi['emi_amount'] - $emi['paid_amount'] ?>)">Mark Paid</button>
                            <a href="<?= whatsappLink($emi['phone'], "URGENT: Hello {$emi['customer_name']}, your EMI of ".formatINR($emi['emi_amount'])." was due on ".date('d M Y', strtotime($emi['due_date'])).". Please pay immediately to avoid penalty.") ?>" target="_blank" class="btn btn-sm btn-whatsapp">
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
