<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    global $pdo;
    $name = $_POST['name'];
    $owner = $_POST['owner_name'];
    $phone = $_POST['phone'];
    $city = $_POST['city'];
    $address = $_POST['address'];

    $stmt = $pdo->prepare("INSERT INTO shops (name, owner_name, phone, city, address) VALUES (?,?,?,?,?)");
    if($stmt->execute([$name, $owner, $phone, $city, $address])){
        setFlash('success', 'Shop added successfully.');
        header('Location: index.php');
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Add New Shop</h1>
        <p class="page-subtitle">Register a new retail branch.</p>
    </div>
    <a href="index.php" class="btn btn-outline">Back to Shops</a>
</div>

<div class="card" style="max-width: 600px;">
    <div class="card-body">
        <form method="POST">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Shop Name *</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Owner Name</label>
                    <input type="text" name="owner_name">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone">
                </div>
                <div class="form-group full">
                    <label>City</label>
                    <input type="text" name="city">
                </div>
                <div class="form-group full">
                    <label>Full Address</label>
                    <textarea name="address"></textarea>
                </div>
            </div>
            <div class="mt-4 flex gap-3">
                <button type="submit" class="btn btn-primary">Save Shop</button>
                <a href="index.php" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
