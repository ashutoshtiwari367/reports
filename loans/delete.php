<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

// Only Super Admin can delete loans
if (!isSuperAdmin()) {
    setFlash('error', 'You do not have permission to delete loans.');
    header('Location: index.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    global $pdo;
    try {
        $pdo->beginTransaction();

        // 1. Get customer_id before deleting loan
        $stmt = $pdo->prepare("SELECT customer_id FROM loans WHERE id = ?");
        $stmt->execute([$id]);
        $loanData = $stmt->fetch();
        
        if ($loanData) {
            $custId = $loanData['customer_id'];
            
            // 2. Delete the loan
            $stmt = $pdo->prepare("DELETE FROM loans WHERE id = ?");
            $stmt->execute([$id]);
            
            // 3. Check if customer has any OTHER loans
            $check = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE customer_id = ?");
            $check->execute([$custId]);
            $remainingLoans = (int)$check->fetchColumn();
            
            if ($remainingLoans === 0) {
                // 4. Delete the customer if no loans are left
                $pdo->prepare("DELETE FROM customers WHERE id = ?")->execute([$custId]);
            }
        }

        $pdo->commit();
        setFlash('success', 'Loan and associated customer (if no other loans) deleted successfully.');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        setFlash('error', 'Could not delete: ' . $e->getMessage());
    }
} else {
    setFlash('error', 'Invalid Loan ID.');
}

header('Location: index.php');
exit;
