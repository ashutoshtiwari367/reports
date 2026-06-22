<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) die('Invalid ID');

$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$month = $_GET['month'] ?? '';

if (!empty($month) && empty($start_date) && empty($end_date)) {
    $start_date = $month . '-01';
    $end_date = date('Y-m-t', strtotime($start_date));
}

global $pdo;
$stmt = $pdo->prepare("SELECT * FROM shops WHERE id = ?");
$stmt->execute([$id]);
$shop = $stmt->fetch();
if (!$shop) die('Shop not found');

// Stats for this specific shop
$stats = [
    'active_loans' => $pdo->query("SELECT COUNT(*) FROM loans WHERE shop_id=$id AND status='active'")->fetchColumn(),
    'total_sales' => $pdo->query("SELECT COALESCE(SUM(total_price),0) FROM loans WHERE shop_id=$id AND status != 'cancelled'")->fetchColumn(),
    'total_collection' => $pdo->query("
        SELECT COALESCE(SUM(p.amount),0) 
        FROM emi_payments p 
        JOIN emi_schedule e ON p.emi_id = e.id 
        JOIN loans l ON e.loan_id = l.id 
        WHERE l.shop_id=$id
    ")->fetchColumn(),
    'expected_profit' => getShopTotalProfit($id),
    'received_profit' => getShopReceivedProfit($id),
    'total_lagat'     => getShopTotalLagat($id),
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
        <a href="/loans/add.php?shop_id=<?= $shop['id'] ?>" class="btn btn-primary">New Sale</a>
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

    <?php if(isSuperAdmin()): ?>
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(99, 102, 241, 0.1); color: var(--accent);">
            <svg viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div>
            <div class="stat-value text-accent"><?= formatINR($stats['expected_profit']) ?></div>
            <div class="stat-label">Expected Profit</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
            <svg viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div>
            <div class="stat-value text-success"><?= formatINR($stats['received_profit']) ?></div>
            <div class="stat-label">Received Profit</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <div>
            <div class="stat-value text-warning"><?= formatINR($stats['total_lagat']) ?></div>
            <div class="stat-label">Total Cost (Lagat)</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="card" style="margin-top: 32px;">
    <div class="card-header" style="flex-wrap: wrap; gap: 15px;">
        <h3 class="card-title">All Shop Loans</h3>
    </div>
    
    <!-- Filter Form -->
    <div style="background: var(--surface2); padding: 20px; border-bottom: 1px solid var(--border);">
        <form method="GET">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label style="font-size: 11px;">Select Month</label>
                    <input type="month" name="month" id="filter_month" value="<?= htmlspecialchars($month) ?>" onchange="updateDatesFromMonth(this.value)">
                </div>
                
                <div class="form-group">
                    <label style="font-size: 11px;">Start Date</label>
                    <input type="date" name="start_date" id="filter_start_date" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                
                <div class="form-group">
                    <label style="font-size: 11px;">End Date</label>
                    <input type="date" name="end_date" id="filter_end_date" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                
                <div class="form-group full">
                    <div style="display: flex; flex-direction: column; gap: 8px; width: 100%; margin-top: 5px;">
                        <!-- Row 1: Filter & Reset side-by-side -->
                        <div style="display: flex; gap: 8px; width: 100%;">
                            <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">Filter</button>
                            <a href="dashboard.php?id=<?= $id ?>" class="btn btn-outline" style="flex: 1; justify-content: center; display: inline-flex; align-items: center;">Reset</a>
                        </div>
                        
                        <?php
                        // Query loans based on selected date filters
                        $whereClause = "WHERE l.shop_id = :shop_id";
                        $queryParams = [':shop_id' => $id];

                        if (!empty($start_date)) {
                            $whereClause .= " AND l.sale_date >= :start_date";
                            $queryParams[':start_date'] = $start_date;
                        }
                        if (!empty($end_date)) {
                            $whereClause .= " AND l.sale_date <= :end_date";
                            $queryParams[':end_date'] = $end_date;
                        }

                        $stmt = $pdo->prepare("
                            SELECT l.*, c.name as customer_name, c.phone 
                            FROM loans l 
                            JOIN customers c ON l.customer_id = c.id 
                            $whereClause
                            ORDER BY l.created_at DESC
                        ");
                        $stmt->execute($queryParams);
                        $allLoans = $stmt->fetchAll();
                        
                        if(!empty($allLoans)): ?>
                        <!-- Row 2: Download PDF button takes its own full-width row -->
                        <a href="#" onclick="downloadPDF(event)" class="btn btn-primary" style="width: 100%; justify-content: center; background-color: var(--accent); color: white; display: inline-flex; align-items: center;">
                            <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; margin-right: 5px; fill: none; stroke: currentColor; stroke-width: 2;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Download PDF
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <div class="table-wrapper">
        <?php
        
        if(empty($allLoans)): ?>
            <div class="empty-state">
                <p>No loans found for this shop.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Loan #</th>
                        <th>Customer</th>
                        <th>Item / Model</th>
                        <th>Price</th>
                        <th>Months</th>
                        <th>Status</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($allLoans as $loan): ?>
                    <tr>
                        <td><strong><a href="/loans/view.php?id=<?= $loan['id'] ?>" style="color:var(--accent)"><?= htmlspecialchars($loan['loan_number']) ?></a></strong></td>
                        <td>
                            <?= htmlspecialchars($loan['customer_name']) ?><br>
                            <small class="text-muted"><?= htmlspecialchars($loan['phone']) ?></small>
                        </td>
                        <td>
                            <?= htmlspecialchars($loan['item_name']) ?><br>
                            <small class="text-muted"><?= htmlspecialchars($loan['model_detail']) ?></small>
                        </td>
                        <td>
                            <?= formatINR($loan['total_price']) ?><br>
                            <small class="text-muted">Bal: <?= formatINR($loan['remaining_amount']) ?></small>
                        </td>
                        <td><?= $loan['emi_months'] ?> mo × <?= formatINR($loan['emi_amount']) ?></td>
                        <td><?= statusBadge($loan['status']) ?></td>
                        <td class="text-right">
                            <a href="/loans/view.php?id=<?= $loan['id'] ?>" class="btn btn-sm btn-outline">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
function updateDatesFromMonth(monthVal) {
    if (monthVal) {
        const parts = monthVal.split('-');
        const year = parseInt(parts[0]);
        const month = parseInt(parts[1]);
        
        // Start date
        const startDateStr = `${year}-${String(month).padStart(2, '0')}-01`;
        
        // End date (last day of month)
        const lastDay = new Date(year, month, 0).getDate();
        const endDateStr = `${year}-${String(month).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;
        
        document.getElementById('filter_start_date').value = startDateStr;
        const startPicker = document.getElementById('filter_start_date')._flatpickr;
        if (startPicker) {
            startPicker.setDate(startDateStr);
        }
        
        document.getElementById('filter_end_date').value = endDateStr;
        const endPicker = document.getElementById('filter_end_date')._flatpickr;
        if (endPicker) {
            endPicker.setDate(endDateStr);
        }
    } else {
        document.getElementById('filter_start_date').value = '';
        const startPicker = document.getElementById('filter_start_date')._flatpickr;
        if (startPicker) startPicker.clear();
        
        document.getElementById('filter_end_date').value = '';
        const endPicker = document.getElementById('filter_end_date')._flatpickr;
        if (endPicker) endPicker.clear();
    }
}

function downloadPDF(e) {
    e.preventDefault();
    const start = document.getElementById('filter_start_date').value;
    const end = document.getElementById('filter_end_date').value;
    const shopId = <?= $id ?>;
    const url = `/exports/export_pdf.php?shop_id=${shopId}&start_date=${encodeURIComponent(start)}&end_date=${encodeURIComponent(end)}`;
    window.open(url, '_blank');
}
</script>

<?php include __DIR__ . '/../emis/payment_modal.php'; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

