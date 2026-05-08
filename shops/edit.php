<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) die('Invalid ID');

global $pdo;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $owner = $_POST['owner_name'];
    $phone = $_POST['phone'];
    $city = $_POST['city'];
    $address = $_POST['address'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE shops SET name=?, owner_name=?, phone=?, city=?, address=?, is_active=? WHERE id=?");
    if($stmt->execute([$name, $owner, $phone, $city, $address, $is_active, $id])){
        setFlash('success', 'Shop updated successfully.');
        header('Location: index.php');
        exit;
    }
}

$stmt = $pdo->prepare("SELECT * FROM shops WHERE id = ?");
$stmt->execute([$id]);
$shop = $stmt->fetch();
if (!$shop) die('Shop not found');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Edit Shop</h1>
        <p class="page-subtitle">Update details for <?= htmlspecialchars($shop['name']) ?></p>
    </div>
    <a href="index.php" class="btn btn-outline">Back to Shops</a>
</div>

<div class="card" style="max-width: 600px;">
    <div class="card-body">
        <form method="POST">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Shop Name *</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($shop['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Owner Name</label>
                    <input type="text" name="owner_name" value="<?= htmlspecialchars($shop['owner_name']) ?>">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($shop['phone']) ?>">
                </div>
                <div class="form-group full">
                    <label>City</label>
                    <input type="text" name="city" value="<?= htmlspecialchars($shop['city']) ?>">
                </div>
                <div class="form-group full">
                    <label>Full Address</label>
                    <textarea name="address"><?= htmlspecialchars($shop['address']) ?></textarea>
                </div>
                <div class="form-group full flex gap-2" style="flex-direction: row; align-items: center;">
                    <input type="checkbox" name="is_active" id="is_active" value="1" <?= $shop['is_active'] ? 'checked' : '' ?> style="width: auto;">
                    <label for="is_active" style="margin: 0; cursor: pointer;">Shop is Active</label>
                </div>
            </div>
            <div class="mt-4 flex gap-3">
                <button type="submit" class="btn btn-primary">Update Shop</button>
                <a href="index.php" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
