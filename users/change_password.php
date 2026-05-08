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
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$u = $stmt->fetch();

if (!$u) {
    setFlash('error', 'User not found.');
    header('Location: /users/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($new_password && $confirm_password) {
        if ($new_password === $confirm_password) {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $upd  = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->execute([$hash, $id]);
            setFlash('success', 'Password updated successfully for ' . htmlspecialchars($u['name']) . '.');
            header('Location: /users/index.php');
            exit;
        } else {
            $error = 'Passwords do not match!';
        }
    } else {
        $error = 'All fields are required.';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-6 flex items-center gap-4">
    <a href="/users/index.php" class="btn btn-outline">← Back</a>
    <div>
        <h1 class="page-title">Change Password: <?= htmlspecialchars($u['name']) ?></h1>
        <p class="page-subtitle">Set a new secure password for this user</p>
    </div>
</div>

<div class="card" style="max-width:500px;">
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-error mb-4"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">

            <div class="form-group mb-4">
                <label>New Password *</label>
                <input type="password" name="new_password" required placeholder="Enter new password">
            </div>

            <div class="form-group mb-4">
                <label>Confirm Password *</label>
                <input type="password" name="confirm_password" required placeholder="Confirm new password">
            </div>

            <div class="mt-4 flex gap-3">
                <button type="submit" class="btn btn-primary">Change Password</button>
                <a href="/users/index.php" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

