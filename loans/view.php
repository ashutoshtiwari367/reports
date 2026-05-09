<?php
require_once __DIR__ . '/../includes/header.php';
global $pdo;

$id = (int)($_GET['id'] ?? 0);
if(!$id) die('Invalid ID');

$loan = $pdo->prepare("
    SELECT l.*, l.id as loan_id, s.name as shop_name, c.*, c.name as customer_name, c.phone 
    FROM loans l 
    JOIN shops s ON l.shop_id = s.id 
    JOIN customers c ON l.customer_id = c.id 
    WHERE l.id = ?
");
$loan->execute([$id]);
$l = $loan->fetch();
if(!$l) die('Loan not found');

$emis = $pdo->prepare("SELECT * FROM emi_schedule WHERE loan_id = ? ORDER BY installment_number ASC");
$emis->execute([$id]);
$schedule = $emis->fetchAll();

$totalPaid = 0;
foreach($schedule as $e) $totalPaid += $e['paid_amount'];
$progress = ($l['remaining_amount'] > 0) ? ($totalPaid / $l['remaining_amount']) * 100 : 0;
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Loan #<?= htmlspecialchars($l['loan_number']) ?></h1>
        <p class="page-subtitle"><?= htmlspecialchars($l['customer_name']) ?> • Sale Date: <?= formatDate($l['sale_date']) ?></p>
    </div>
    <div class="flex gap-3">
        <?= statusBadge($l['status']) ?>
        <a href="print.php?id=<?= $l['loan_id'] ?>" target="_blank" class="btn btn-primary">
            <svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print Invoice
        </a>
        <a href="index.php" class="btn btn-outline">Back to Loans</a>
        <?php if(isSuperAdmin()): ?>
            <a href="edit.php?id=<?= $l['loan_id'] ?>" class="btn btn-primary" style="background:var(--accent);">Edit</a>
            <a href="delete.php?id=<?= $l['loan_id'] ?>" onclick="return confirm('Are you sure you want to completely delete this loan and all its EMIs? This cannot be undone.')" class="btn btn-outline" style="color:var(--danger); border-color:var(--danger);">Delete</a>
        <?php endif; ?>
    </div>
</div>

<div class="form-grid mb-4" style="grid-template-columns: 1.5fr 1fr;">
    <div class="card">
        <div class="card-body">
            <div class="dashboard-section-label">Product & Financial Details</div>
            <div class="flex gap-4 mb-4 flex-wrap">
                <div style="min-width:140px;">
                    <label>Item / Category</label>
                    <div class="text-bold"><?= htmlspecialchars($l['item_name']) ?></div>
                </div>
                <div style="min-width:140px;">
                    <label>Model Detail</label>
                    <div class="text-bold"><?= htmlspecialchars($l['model_detail']) ?></div>
                </div>
                <div style="min-width:140px;">
                    <label>Reference By</label>
                    <div class="text-bold"><?= htmlspecialchars($l['reference_by'] ?: 'N/A') ?></div>
                </div>
            </div>

            <div class="flex gap-4 mb-4 flex-wrap border-top pt-4">
                <div style="min-width:120px;">
                    <label>Item Price</label>
                    <div class="text-bold" style="font-size:18px;"><?= formatINR($l['total_price']) ?></div>
                </div>
                <?php if(isSuperAdmin()): ?>
                <div style="min-width:120px;">
                    <label>Purchased Price</label>
                    <div class="text-bold" style="color:var(--text-muted);"><?= formatINR($l['purchased_price'] ?? 0) ?></div>
                </div>
                <div style="min-width:120px;">
                    <label>Total Profit</label>
                    <div class="text-bold" style="color:var(--success);"><?= formatINR(($l['total_price'] - ($l['purchased_price'] ?? 0)) + $l['interest_amount']) ?></div>
                </div>
                <?php endif; ?>
                <div style="min-width:120px;">
                    <label>Interest Amount</label>
                    <div class="text-bold" style="color:var(--danger);"><?= formatINR($l['interest_amount']) ?></div>
                </div>
                <div style="min-width:120px;">
                    <label>Down Payment</label>
                    <div class="text-bold" style="color:var(--success);"><?= formatINR($l['down_payment']) ?></div>
                </div>
                <div style="min-width:120px;">
                    <label>EMI Amount</label>
                    <div class="text-bold" style="color:var(--accent);"><?= formatINR($l['emi_amount']) ?> / month</div>
                </div>
            </div>
            
            <label>Collection Progress (<?= number_format($progress, 1) ?>%)</label>
            <div class="flex justify-between" style="font-size:12px; margin-top:2px;">
                <span><?= formatINR($totalPaid) ?> Collected</span>
                <span><?= formatINR($l['remaining_amount'] - $totalPaid) ?> Left</span>
            </div>
            <div class="progress-bar" style="margin-bottom: 0;">
                <div class="progress-fill <?= $progress == 100 ? 'green' : '' ?>" style="width: <?= $progress ?>%;"></div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <div class="dashboard-section-label">Customer Documents</div>
            <div class="flex gap-4 mb-4">
                <div style="flex:1; text-align:center;">
                    <label style="display:block; margin-bottom:8px;">Customer Photo</label>
                    <?php if($l['customer_photo']): ?>
                        <a href="/<?= $l['customer_photo'] ?>" target="_blank">
                            <img src="/<?= $l['customer_photo'] ?>" style="width:100%; max-height:100px; object-fit:cover; border-radius:8px; border:1px solid var(--border);">
                        </a>
                    <?php else: ?>
                        <div style="height:100px; background:var(--surface2); border-radius:8px; display:flex; align-items:center; justify-content:center; color:var(--text-light); font-size:11px;">No Photo</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex gap-4">
                <div style="flex:1; text-align:center;">
                    <label style="display:block; margin-bottom:4px; font-size:11px;">Aadhaar (Front)</label>
                    <?php if($l['aadhaar_photo']): ?>
                        <a href="/<?= $l['aadhaar_photo'] ?>" target="_blank">
                            <img src="/<?= $l['aadhaar_photo'] ?>" style="width:100%; max-height:80px; object-fit:cover; border-radius:6px; border:1px solid var(--border);">
                        </a>
                    <?php endif; ?>
                </div>
                <div style="flex:1; text-align:center;">
                    <label style="display:block; margin-bottom:4px; font-size:11px;">Aadhaar (Back)</label>
                    <?php if($l['aadhaar_back_photo']): ?>
                        <a href="/<?= $l['aadhaar_back_photo'] ?>" target="_blank">
                            <img src="/<?= $l['aadhaar_back_photo'] ?>" style="width:100%; max-height:80px; object-fit:cover; border-radius:6px; border:1px solid var(--border);">
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="form-grid mb-4">
    <div class="card">
        <div class="card-body">
            <div class="dashboard-section-label">Customer Contact & Address</div>
            <div class="flex gap-4 mb-4">
                <div>
                    <label>Phone</label>
                    <div class="text-bold"><a href="tel:<?= $l['phone'] ?>"><?= $l['phone'] ?></a></div>
                </div>
                <div>
                    <label>City</label>
                    <div class="text-bold"><?= htmlspecialchars($l['city'] ?: 'N/A') ?></div>
                </div>
            </div>
            <div class="mb-2">
                <label>Landmark</label>
                <div class="text-bold"><?= htmlspecialchars($l['landmark'] ?: 'N/A') ?></div>
            </div>
            <div>
                <label>Full Address</label>
                <div class="text-muted"><?= nl2br(htmlspecialchars($l['address'])) ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="dashboard-section-label">References / Guarantors</div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <?php for($i=1; $i<=4; $i++): 
                    if(!$l["ref_{$i}_name"]) continue;
                ?>
                <div style="font-size:13px; padding:8px; background:var(--surface2); border-radius:6px;">
                    <div style="color:var(--accent); font-weight:700;"><?= htmlspecialchars($l["ref_{$i}_name"]) ?></div>
                    <div style="font-size:11px;"><?= htmlspecialchars($l["ref_{$i}_relation"]) ?> • <a href="tel:<?= $l["ref_{$i}_phone"] ?>"><?= $l["ref_{$i}_phone"] ?></a></div>
                </div>
                <?php endfor; ?>
                <?php if(!$l['ref_1_name']): ?>
                    <p class="text-muted">No references added.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">EMI Schedule (<?= count($schedule) ?> Months)</h3>
    </div>
    <div class="emi-timeline">
        <?php foreach($schedule as $emi): 
            $dueAmt = $emi['emi_amount'] - $emi['paid_amount'];
            $isPaid = $emi['status'] === 'received';
        ?>
        <div class="emi-row">
            <div class="emi-num <?= $emi['status'] ?>"><?= $emi['installment_number'] ?></div>
            
            <div>
                <div style="font-size:12px; color:var(--text-muted); font-weight:600;">Due Date</div>
                <div class="text-bold" style="<?= $emi['status']==='overdue' ? 'color:var(--danger)' : '' ?>">
                    <?= formatDate($emi['due_date']) ?>
                </div>
            </div>
            
            <div>
                <div style="font-size:12px; color:var(--text-muted); font-weight:600;">EMI Amount</div>
                <div class="text-bold"><?= formatINR($emi['emi_amount']) ?></div>
            </div>
            
            <div>
                <div style="font-size:12px; color:var(--text-muted); font-weight:600;">Paid</div>
                <div style="color:var(--success); font-weight:bold;"><?= formatINR($emi['paid_amount']) ?></div>
            </div>
            
            <div>
                <?= statusBadge($emi['status']) ?>
            </div>
            
            <div class="text-right flex gap-2" style="justify-content: flex-end;">
                <?php if(!$isPaid): ?>
                    <button class="btn btn-sm btn-success" onclick="openPaymentModal(<?= $emi['id'] ?>, <?= $dueAmt ?>)">Mark Paid</button>
                <?php else: ?>
                    <button class="btn btn-sm btn-ghost" disabled>Completed</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/../emis/payment_modal.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>


