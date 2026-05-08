<?php
// Process form BEFORE any HTML output
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
global $pdo;

if (!isSuperAdmin()) {
    setFlash('error', 'Access Denied.');
    header('Location: /emi/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $shop_id = !empty($_POST['shop_id']) ? (int)$_POST['shop_id'] : null;

    if ($name && $email && $password) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email already exists!';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = $shop_id ? 'shop_admin' : 'admin';
            $ins  = $pdo->prepare("INSERT INTO users (name, email, password, role, shop_id) VALUES (?, ?, ?, ?, ?)");
            $ins->execute([$name, $email, $hash, $role, $shop_id]);
            setFlash('success', 'User account created successfully.');
            header('Location: /emi/users/index.php');
            exit;
        }
    } else {
        $error = 'All fields are required.';
    }
}

// Fetch shops for dropdown
$shops = $pdo->query("SELECT id, name FROM shops WHERE is_active = 1 ORDER BY name")->fetchAll();

// Now include header (outputs HTML)
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-6 flex items-center gap-4">
    <a href="/emi/users/index.php" class="btn btn-outline">← Back</a>
    <div>
        <h1 class="page-title">Create New User</h1>
        <p class="page-subtitle">Create a Super Admin or assign an account to a Shop</p>
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
                    <input type="text" name="name" required placeholder="John Doe">
                </div>

                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" required placeholder="shop@example.com">
                </div>

                <div class="form-group">
                    <label>Password *</label>
                    <input type="text" name="password" required placeholder="SecretPass123">
                </div>

                <div class="form-group">
                    <label>Assign to Shop <small>(Optional)</small></label>
                    <select name="shop_id">
                        <option value="">-- None (Super Admin) --</option>
                        <?php foreach ($shops as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-muted" style="font-size:12px; margin-top:4px;">If you select a shop, this user will only see that shop's data.</p>
                </div>
            </div>

            <div class="mt-4 flex gap-3">
                <button type="submit" class="btn btn-primary">Create User Account</button>
                <a href="/emi/users/index.php" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
