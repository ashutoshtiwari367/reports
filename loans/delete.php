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
        $stmt = $pdo->prepare("DELETE FROM loans WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'Loan deleted successfully.');
    } catch (Exception $e) {
        setFlash('error', 'Could not delete loan: ' . $e->getMessage());
    }
} else {
    setFlash('error', 'Invalid Loan ID.');
}

header('Location: index.php');
exit;
