<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

// Export All Active Loans to CSV (Excel format)
global $pdo;

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
        l.status as 'Status'
    FROM loans l
    JOIN shops s ON l.shop_id = s.id
    JOIN customers c ON l.customer_id = c.id
    ORDER BY l.created_at DESC
";

$stmt = $pdo->query($query);
$loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = "emi_active_loans_" . date('Ymd') . ".csv";

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
