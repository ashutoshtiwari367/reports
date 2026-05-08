<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
if (!empty($_SESSION['user_id'])) {
    header('Location: /emi/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $pass  = $_POST['password'] ?? '';

    if ($email && $pass) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($pass, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = $user;
            header('Location: /emi/dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EMI Tracker</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/emi/assets/css/style.css">
</head>
<body class="auth-page">

    <div class="auth-card">
        <div class="auth-logo">
            <div class="auth-logo-icon">₹</div>
            <h1>Welcome Back  SS Group</h1>
            <p>Login to manage your shops </p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group mb-4">
                <label>Email Address</label>
                <input type="email" name="email" value="admin@emi.com" required autofocus>
            </div>
            
            <div class="form-group mb-4">
                <label>Password</label>
                <input type="password" name="password" value="admin123" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:12px; font-size:14px; margin-top:8px;">
                Sign In
            </button>
        </form>
    </div>

</body>
</html>
