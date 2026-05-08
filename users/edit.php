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
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $shop_id = !empty($_POST['shop_id']) ? (int)$_POST['shop_id'] : null;

    if ($name && $email) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) {
            $error = 'Email already exists!';
        } else {
            $role = $shop_id ? 'shop_admin' : 'admin';
            $upd  = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, shop_id = ? WHERE id = ?");
            $upd->execute([$name, $email, $role, $shop_id, $id]);
            setFlash('success', 'User updated successfully.');
            header('Location: /users/index.php');
            exit;
        }
    } else {
        $error = 'Name and Email are required.';
    }
}

$shops = $pdo->query("SELECT id, name FROM shops WHERE is_active = 1 ORDER BY name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-6 flex items-center gap-4">
    <a href="/users/index.php" class="btn btn-outline">← Back</a>
    <div>
        <h1 class="page-title">Edit User: <?= htmlspecialchars($u['name']) ?></h1>
        <p class="page-subtitle">Update account details or re-assign to a shop</p>
    </div>
</div>

<div class="card" style="max-width:700px;">
    <div class="card-body">
        <?php if ($error): ?>
            <div class="alert alert-error mb-4"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" required value="<?= htmlspecialchars($u['name']) ?>">
                </div>

                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($u['email']) ?>">
                </div>

                <div class="form-group">
                    <label>Assign to Shop <small>(Optional)</small></label>
                    <select name="shop_id">
                        <option value="">-- None (Super Admin) --</option>
                        <?php foreach ($shops as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $u['shop_id'] == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mt-4 flex gap-3">
                <button type="submit" class="btn btn-primary">Update User</button>
                <a href="/users/index.php" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

