<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    global $pdo;
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $alt = $_POST['alternate_phone'] ?? '';
    $city = $_POST['city'] ?? '';
    $address = $_POST['address'] ?? '';
    $id_type = $_POST['id_proof_type'] ?? '';
    $id_num = $_POST['id_proof_number'] ?? '';
    
    // Determine shop_id
    $shop_id = null;
    if (isShopAdmin()) {
        $shop_id = getShopId();
    } else {
        $shop_id = !empty($_POST['shop_id']) ? (int)$_POST['shop_id'] : null;
    }

    $stmt = $pdo->prepare("INSERT INTO customers (shop_id, name, phone, alternate_phone, city, address, id_proof_type, id_proof_number) VALUES (?,?,?,?,?,?,?,?)");
    if($stmt->execute([$shop_id, $name, $phone, $alt, $city, $address, $id_type, $id_num])){
        $newId = $pdo->lastInsertId();
        setFlash('success', 'Customer added successfully.');
        header('Location: view.php?id=' . $newId);
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
global $pdo;
$shops = [];
if (isSuperAdmin()) {
    $shops = $pdo->query("SELECT id, name FROM shops WHERE is_active = 1 ORDER BY name")->fetchAll();
}

?>

<div class="page-header">
    <div>
        <h1 class="page-title">Add New Customer</h1>
        <p class="page-subtitle">Register a new customer profile.</p>
    </div>
    <a href="index.php" class="btn btn-outline">Back to Customers</a>
</div>

<div class="card" style="max-width: 700px;">
    <div class="card-body">
        <form method="POST">
            <div class="form-grid">
                <?php if (isSuperAdmin()): ?>
                <div class="form-group full">
                    <label>Assign to Shop</label>
                    <select name="shop_id" required>
                        <option value="">-- Select Shop --</option>
                        <?php foreach($shops as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="form-group full">
                    <label>Full Name *</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Phone Number *</label>
                    <input type="tel" name="phone" required pattern="[0-9]{10,12}" placeholder="10 digit number">
                </div>
                <div class="form-group">
                    <label>Alternate Phone</label>
                    <input type="tel" name="alternate_phone">
                </div>
                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="city">
                </div>
                <div class="form-group">
                    <label>ID Proof Type</label>
                    <select name="id_proof_type">
                        <option value="">Select...</option>
                        <option value="Aadhaar">Aadhaar Card</option>
                        <option value="PAN">PAN Card</option>
                        <option value="Voter ID">Voter ID</option>
                        <option value="Driving License">Driving License</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>ID Proof Number</label>
                    <input type="text" name="id_proof_number">
                </div>
                <div class="form-group full">
                    <label>Full Address</label>
                    <textarea name="address"></textarea>
                </div>
            </div>
            <div class="mt-4 flex gap-3">
                <button type="submit" class="btn btn-primary">Save Customer</button>
                <a href="index.php" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
