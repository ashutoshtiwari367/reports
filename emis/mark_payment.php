<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    
    $emiId = (int)($_POST['emi_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $mode = $_POST['payment_mode'] ?? 'cash';
    $date = $_POST['payment_date'] ?? date('Y-m-d');
    $ref = $_POST['reference_number'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $returnUrl = $_POST['return_url'] ?? '/emi/dashboard.php';

    if ($emiId > 0 && $amount > 0) {
        global $pdo;
        
        $stmt = $pdo->prepare("INSERT INTO emi_payments (emi_id, amount, payment_mode, payment_date, reference_number, notes, recorded_by) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$emiId, $amount, $mode, $date, $ref, $notes, $_SESSION['user_id']]);
        
        // Sync parent EMI and Loan
        syncEMIStatus($emiId);
        
        setFlash('success', 'Payment recorded successfully.');
    } else {
        setFlash('error', 'Invalid amount or EMI data.');
    }
    
    header("Location: $returnUrl");
    exit;
}
