<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
global $pdo;

if (!isSuperAdmin()) {
    setFlash('error', 'Access Denied.');
    header('Location: /dashboard.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$user = currentUser();

// Prevent self-deletion
if ($id == $user['id']) {
    setFlash('error', 'You cannot delete your own account.');
    header('Location: /users/index.php');
    exit;
}

if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    setFlash('success', 'User deleted successfully.');
}

header('Location: /users/index.php');
exit;

