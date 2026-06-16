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
        l.total_price as 'Total Price',
        l.remaining_amount as 'Remaining Balance',
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
$filename .= ".csv";

// Headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Add BOM to fix UTF-8 in Excel
fputs($output, $bom =(chr(0xEF) . chr(0xBB) . chr(0xBF)));

if (count($loans) > 0) {
    // Add Headers
    fputcsv($output, array_keys($loans[0]));
    
    // Add Rows
    foreach ($loans as $row) {
        fputcsv($output, $row);
    }
} else {
    fputcsv($output, ['No data found']);
}

fclose($output);
exit;
