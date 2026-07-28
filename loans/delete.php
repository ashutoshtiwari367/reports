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

        // 1. Get customer_id and customer photos before deleting loan
        $stmt = $pdo->prepare("
            SELECT l.customer_id, c.customer_photo, c.aadhaar_photo, c.aadhaar_back_photo 
            FROM loans l 
            JOIN customers c ON l.customer_id = c.id 
            WHERE l.id = ?
        ");
        $stmt->execute([$id]);
        $loanData = $stmt->fetch();
        
        if ($loanData) {
            $custId = $loanData['customer_id'];
            
            // 2. Find all EMI IDs for this loan to manually delete payments
            $emiStmt = $pdo->prepare("SELECT id FROM emi_schedule WHERE loan_id = ?");
            $emiStmt->execute([$id]);
            $emiIds = $emiStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($emiIds)) {
                $inQuery = implode(',', array_fill(0, count($emiIds), '?'));
                // Delete associated payments
                $stmt = $pdo->prepare("DELETE FROM emi_payments WHERE emi_id IN ($inQuery)");
                $stmt->execute($emiIds);
            }
            
            // Delete associated EMI schedule
            $stmt = $pdo->prepare("DELETE FROM emi_schedule WHERE loan_id = ?");
            $stmt->execute([$id]);

            // Delete the loan itself
            $stmt = $pdo->prepare("DELETE FROM loans WHERE id = ?");
            $stmt->execute([$id]);
            
            // 3. Check if customer has any OTHER loans
            $check = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE customer_id = ?");
            $check->execute([$custId]);
            $remainingLoans = (int)$check->fetchColumn();
            
            if ($remainingLoans === 0) {
                // 4. Delete customer photo files from disk if they exist
                foreach (['customer_photo', 'aadhaar_photo', 'aadhaar_back_photo'] as $key) {
                    if (!empty($loanData[$key])) {
                        $filePath = __DIR__ . '/../' . $loanData[$key];
                        if (file_exists($filePath)) {
                            @unlink($filePath);
                        }
                    }
                }
                
                // Delete the customer record
                $pdo->prepare("DELETE FROM customers WHERE id = ?")->execute([$custId]);
            }
        }

        $pdo->commit();
        setFlash('success', 'Loan, associated payments/schedule, and customer (if no other loans left) deleted successfully.');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        setFlash('error', 'Could not delete: ' . $e->getMessage());
    }
} else {
    setFlash('error', 'Invalid Loan ID.');
}

header('Location: index.php');
exit;
