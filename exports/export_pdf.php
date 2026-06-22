<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

global $pdo;

$shop_id = isset($_GET['shop_id']) ? (int)$_GET['shop_id'] : 0;
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Build dynamic WHERE section
$whereClauses = [];
$params = [];

if ($shop_id > 0) {
    $whereClauses[] = "l.shop_id = :shop_id";
    $params[':shop_id'] = $shop_id;
}

if (!empty($start_date)) {
    $whereClauses[] = "l.sale_date >= :start_date";
    $params[':start_date'] = $start_date;
}

if (!empty($end_date)) {
    $whereClauses[] = "l.sale_date <= :end_date";
    $params[':end_date'] = $end_date;
}

$whereSection = "";
if (count($whereClauses) > 0) {
    $whereSection = "WHERE " . implode(" AND ", $whereClauses);
}

$query = "
    SELECT 
        l.loan_number,
        l.sale_date,
        c.name as customer_name,
        l.emi_amount,
        l.purchased_price,
        l.total_price,
        l.interest_amount
    FROM loans l
    JOIN customers c ON l.customer_id = c.id
    $whereSection
    ORDER BY l.sale_date DESC, l.created_at DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If filtered by shop, get shop details
$shopName = "All Shops";
if ($shop_id > 0) {
    $shopStmt = $pdo->prepare("SELECT name FROM shops WHERE id = ?");
    $shopStmt->execute([$shop_id]);
    $shopName = $shopStmt->fetchColumn() ?: "Shop #$shop_id";
}

// Generate filename
$filename = "Report_" . preg_replace('/[^a-zA-Z0-9]/', '_', $shopName) . "_";
if (!empty($start_date) && !empty($end_date)) {
    $filename .= $start_date . "_to_" . $end_date;
} elseif (!empty($start_date)) {
    $filename .= "from_" . $start_date;
} elseif (!empty($end_date)) {
    $filename .= "up_to_" . $end_date;
} else {
    $filename .= date('Ymd');
}
$filename .= ".pdf";

// Calculate Totals
$totalCost = 0;
$totalProfit = 0;
$totalEMI = 0;
foreach ($loans as $loan) {
    $totalCost += (float)$loan['purchased_price'];
    $totalProfit += (((float)$loan['total_price'] - (float)$loan['purchased_price']) + (float)$loan['interest_amount']);
    $totalEMI += (float)$loan['emi_amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report - <?= htmlspecialchars($shopName) ?></title>
    <style>
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #0f172a;
            background: #f1f5f9;
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }
        .report-container {
            width: 210mm;
            padding: 15mm;
            box-sizing: border-box;
            background: #ffffff;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header-left h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 800;
            color: #6366f1;
            letter-spacing: -0.5px;
        }
        .header-left p {
            margin: 4px 0 0 0;
            font-size: 13px;
            color: #64748b;
        }
        .header-right {
            text-align: right;
        }
        .header-right p {
            margin: 2px 0;
            font-size: 11px;
            color: #64748b;
        }
        .header-right .report-type {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }
        
        /* Stats Summary Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            background: #f8fafc;
            text-align: center;
        }
        .stat-val {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 2px;
        }
        .stat-label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
        }
        
        /* Table styles */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #6366f1;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 8px;
            border: 1px solid #6366f1;
            text-align: left;
        }
        td {
            padding: 10px 8px;
            font-size: 11.5px;
            color: #334155;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        tr.totals-row td {
            font-weight: 700;
            background-color: #f1f5f9;
            border-top: 2px solid #94a3b8;
            border-bottom: 2px solid #94a3b8;
            color: #0f172a;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        
        /* Loader screen overlay */
        .loading-screen {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            font-family: 'Inter', sans-serif;
            color: #0f172a;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #6366f1;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>

<div id="loader" class="loading-screen">
    <div class="spinner"></div>
    <p style="font-size: 16px; font-weight: 600; margin: 0;">Generating PDF Report...</p>
    <p style="font-size: 12px; color: #64748b; margin: 5px 0 0 0;">Please wait, it will download automatically.</p>
</div>

<div id="report-content" class="report-container">
    <div class="header">
        <div class="header-left">
            <h1>SS GROUP</h1>
            <p>Shop: <strong><?= htmlspecialchars($shopName) ?></strong></p>
        </div>
        <div class="header-right">
            <p class="report-type">Sales & Loan Report (Portrait)</p>
            <p>Duration: 
                <strong>
                    <?php if (!empty($start_date) && !empty($end_date)): ?>
                        <?= formatDate($start_date) ?> to <?= formatDate($end_date) ?>
                    <?php elseif (!empty($start_date)): ?>
                        From <?= formatDate($start_date) ?>
                    <?php elseif (!empty($end_date)): ?>
                        Up to <?= formatDate($end_date) ?>
                    <?php else: ?>
                        All Time
                    <?php endif; ?>
                </strong>
            </p>
            <p>Generated: <?= date('d-m-Y H:i') ?></p>
        </div>
    </div>

    <!-- Stats Summary Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Loans</div>
            <div class="stat-val"><?= count($loans) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Cost (Lagat)</div>
            <div class="stat-val"><?= formatINR($totalCost) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Expected Profit</div>
            <div class="stat-val" style="color: #10b981;"><?= formatINR($totalProfit) ?></div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 18%;">Loan No</th>
                <th style="width: 14%; text-align: center;">Date</th>
                <th style="width: 28%;">Customer Name</th>
                <th style="width: 13%; text-align: right;">EMI/Month</th>
                <th style="width: 14%; text-align: right;">Cost (Lagat)</th>
                <th style="width: 13%; text-align: right;">Profit</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($loans) > 0): ?>
                <?php foreach ($loans as $loan): ?>
                    <?php 
                        $loanProfit = (((float)$loan['total_price'] - (float)$loan['purchased_price']) + (float)$loan['interest_amount']);
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($loan['loan_number']) ?></strong></td>
                        <td class="text-center"><?= formatDate($loan['sale_date']) ?></td>
                        <td><?= htmlspecialchars($loan['customer_name']) ?></td>
                        <td class="text-right"><?= formatINR((float)$loan['emi_amount']) ?></td>
                        <td class="text-right"><?= formatINR((float)$loan['purchased_price']) ?></td>
                        <td class="text-right" style="color: #059669; font-weight: 600;"><?= formatINR($loanProfit) ?></td>
                    </tr>
                <?php endforeach; ?>
                <!-- Totals Row -->
                <tr class="totals-row">
                    <td colspan="3" class="text-right" style="text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">Total</td>
                    <td class="text-right"><?= formatINR($totalEMI) ?></td>
                    <td class="text-right"><?= formatINR($totalCost) ?></td>
                    <td class="text-right" style="color: #059669;"><?= formatINR($totalProfit) ?></td>
                </tr>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center" style="padding: 30px; color: #64748b;">No sales records found for this period.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    window.addEventListener('DOMContentLoaded', () => {
        const element = document.getElementById('report-content');
        const loader = document.getElementById('loader');
        
        const opt = {
            margin:       [10, 10, 10, 10], // 10mm margins on all sides
            filename:     '<?= $filename ?>',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { 
                scale: 2, 
                useCORS: true,
                logging: false,
                letterRendering: true
            },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        
        // Short delay to ensure browser finishes rendering styles
        setTimeout(() => {
            html2pdf().set(opt).from(element).save().then(() => {
                loader.innerHTML = `
                    <div style="font-size: 40px; color: #10b981; margin-bottom: 15px;">✓</div>
                    <p style="font-size: 16px; font-weight: 600; margin: 0; color: #10b981;">Report Downloaded!</p>
                    <p style="font-size: 12px; color: #64748b; margin: 5px 0 0 0;">You can close this tab now.</p>
                `;
                // Close the tab automatically after 1.5 seconds
                setTimeout(() => {
                    window.close();
                }, 1500);
            }).catch(err => {
                console.error('PDF Generation Error:', err);
                loader.innerHTML = `
                    <div style="font-size: 40px; color: #ef4444; margin-bottom: 15px;">✗</div>
                    <p style="font-size: 16px; font-weight: 600; margin: 0; color: #ef4444;">Failed to generate PDF</p>
                    <p style="font-size: 12px; color: #64748b; margin: 5px 0 0 0;">Please refresh the page to retry or print manually using browser (Ctrl+P).</p>
                `;
            });
        }, 500);
    });
</script>

</body>
</html>
