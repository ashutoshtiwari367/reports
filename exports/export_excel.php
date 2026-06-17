<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

// Export Loans to CSV (Excel format) with dynamic filters
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
        l.loan_number as 'Loan No',
        s.name as 'Shop Name',
        c.name as 'Customer Name',
        c.phone as 'Phone',
        l.item_name as 'Item',
        l.total_price as 'Sell Price',
        l.interest_amount as 'Interest Amount',
        (l.total_price + l.interest_amount) as 'Total Price (with Interest)',
        l.purchased_price as 'Cost Price (Lagat)',
        ((l.total_price - l.purchased_price) + l.interest_amount) as 'Total Expected Profit (On Full Completion)',
        l.down_payment as 'Down Payment',
        (SELECT COALESCE(SUM(emi_amount), 0) FROM emi_schedule WHERE loan_id = l.id) as 'Total EMI Amount',
        (l.down_payment + (SELECT COALESCE(SUM(paid_amount), 0) FROM emi_schedule WHERE loan_id = l.id)) as 'Total Received (Paid)',
        l.remaining_amount as 'Remaining Balance (Unpaid)',
        ROUND(
            ((l.total_price - l.purchased_price) + l.interest_amount) / 
            NULLIF(l.total_price + l.interest_amount, 0) * 
            (l.down_payment + (SELECT COALESCE(SUM(paid_amount), 0) FROM emi_schedule WHERE loan_id = l.id)), 
            2
        ) as 'Proportional Received Profit (Till Date)',
        l.emi_months as 'Total Months',
        l.status as 'Status',
        l.sale_date as 'Sale Date'
    FROM loans l
    JOIN shops s ON l.shop_id = s.id
    JOIN customers c ON l.customer_id = c.id
    $whereSection
    ORDER BY l.created_at DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If filtered by shop, prefix filename with shop name
$shopPrefix = "emi_";
if ($shop_id > 0) {
    $shopStmt = $pdo->prepare("SELECT name FROM shops WHERE id = ?");
    $shopStmt->execute([$shop_id]);
    $shopName = $shopStmt->fetchColumn();
    if ($shopName) {
        $shopPrefix = preg_replace('/[^a-zA-Z0-9]/', '_', strtolower($shopName)) . "_";
    }
}

// Generate filtered filename
$filename = $shopPrefix . "loans_";
if (!empty($start_date) && !empty($end_date)) {
    $filename .= $start_date . "_to_" . $end_date;
} elseif (!empty($start_date)) {
    $filename .= "from_" . $start_date;
} elseif (!empty($end_date)) {
    $filename .= "up_to_" . $end_date;
} else {
    $filename .= date('Ymd');
}
$filename .= ".xls";

// Headers for download
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
header("Pragma: no-cache");
header("Expires: 0");

// Output XML-compliant HTML which Excel opens directly as a formatted worksheet with gridlines
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<!--[if gte mso 9]><xml>
<x:ExcelWorkbook>
<x:ExcelWorksheets>
<x:ExcelWorksheet>
<x:Name>Loans Report</x:Name>
<x:WorksheetOptions>
<x:DisplayGridlines/>
</x:WorksheetOptions>
</x:ExcelWorksheet>
</x:ExcelWorksheets>
</x:ExcelWorkbook>
</xml><![endif]-->
<style>
  table { border-collapse: collapse; }
  th { background-color: #f2f2f2; font-weight: bold; border: 0.5pt solid #cccccc; padding: 6px; }
  td { border: 0.5pt solid #cccccc; padding: 6px; }
</style>
</head>
<body>
<table>
    <thead>
        <tr>
            <?php if (count($loans) > 0): ?>
                <?php foreach (array_keys($loans[0]) as $header): ?>
                    <th><?= htmlspecialchars($header) ?></th>
                <?php endforeach; ?>
            <?php else: ?>
                <th>Result</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php if (count($loans) > 0): ?>
            <?php foreach ($loans as $row): ?>
                <tr>
                    <?php foreach ($row as $val): ?>
                        <td><?= htmlspecialchars((string)$val) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td>No data found</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
</body>
</html>
<?php
exit;
