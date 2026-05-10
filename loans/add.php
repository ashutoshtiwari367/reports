<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
global $pdo;

$preselect_customer = $_GET['customer_id'] ?? '';

// RBAC: Shop Admin gets own shop only
if (isShopAdmin()) {
    $myShopId = getShopId();
    $shops = $pdo->prepare("SELECT id, name FROM shops WHERE id = ? AND is_active=1");
    $shops->execute([$myShopId]);
    $shops = $shops->fetchAll();
    $customers = $pdo->prepare("SELECT id, name, phone FROM customers WHERE shop_id = ? AND is_active=1 ORDER BY name");
    $customers->execute([$myShopId]);
    $customers = $customers->fetchAll();
} else {
    $shops = $pdo->query("SELECT id, name FROM shops WHERE is_active=1 ORDER BY name")->fetchAll();
    $customers = $pdo->query("SELECT id, name, phone FROM customers WHERE is_active=1 ORDER BY name")->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shopId = isShopAdmin() ? getShopId() : (int)($_POST['shop_id'] ?? 0);

    if (!$shopId || $shopId <= 0) {
        setFlash('error', 'Please select a valid shop.');
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Customer Details
    $custName = trim($_POST['customer_name']);
    $custPhone = trim($_POST['customer_phone']);
    $custCity = trim($_POST['customer_city'] ?? '');
    $custAddr = trim($_POST['customer_address'] ?? '');
    $landmark = trim($_POST['landmark'] ?? '');
    
    // References
    $refs = [];
    for($i=1; $i<=4; $i++) {
        $refs["ref_{$i}_name"] = trim($_POST["ref_{$i}_name"] ?? '');
        $refs["ref_{$i}_phone"] = trim($_POST["ref_{$i}_phone"] ?? '');
        $refs["ref_{$i}_relation"] = trim($_POST["ref_{$i}_relation"] ?? '');
    }
    
    // Sale / Loan Details
    $refBy = trim($_POST['reference_by'] ?? '');
    $saleDate = $_POST['sale_date'] ?? date('Y-m-d');
    $item = $_POST['item_name'];
    $modelDetail = trim($_POST['model_detail'] ?? '');
    $price = (float)$_POST['total_price'];
    $purchasedPrice = (float)($_POST['purchased_price'] ?? 0);
    $down = (float)$_POST['down_payment'];
    $interestAmount = (float)$_POST['interest_amount'];
    $months = (int)$_POST['emi_months'];
    $firstEmi = $_POST['first_emi_date'];
    
    $remaining = ($price - $down) + $interestAmount;
    $emiAmount = round($remaining / $months, 2);
    $dueDay = (int)date('j', strtotime($firstEmi));
    if ($dueDay > 28) $dueDay = 28; 
    
    $manualLoanNum = trim($_POST['manual_loan_number'] ?? '');
    $loanNum = !empty($manualLoanNum) ? $manualLoanNum : generateLoanNumber();

    // Check if loan number already exists
    $checkStmt = $pdo->prepare("SELECT id FROM loans WHERE loan_number = ? LIMIT 1");
    $checkStmt->execute([$loanNum]);
    if ($checkStmt->fetch()) {
        setFlash('error', "Loan Number '$loanNum' already exists. Please use a unique number.");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // Handle File Uploads
    $custPhotoPath = '';
    $aadhaarPhotoPath = '';
    $aadhaarBackPath = '';
    
    if(!empty($_FILES['customer_photo']['name'])) {
        $ext = pathinfo($_FILES['customer_photo']['name'], PATHINFO_EXTENSION);
        $custPhotoPath = 'uploads/customers/' . time() . '_cust.' . $ext;
        move_uploaded_file($_FILES['customer_photo']['tmp_name'], __DIR__ . '/../' . $custPhotoPath);
    }
    
    if(!empty($_FILES['aadhaar_photo']['name'])) {
        $ext = pathinfo($_FILES['aadhaar_photo']['name'], PATHINFO_EXTENSION);
        $aadhaarPhotoPath = 'uploads/aadhaar/' . time() . '_aadhaar_front.' . $ext;
        move_uploaded_file($_FILES['aadhaar_photo']['tmp_name'], __DIR__ . '/../' . $aadhaarPhotoPath);
    }

    if(!empty($_FILES['aadhaar_back_photo']['name'])) {
        $ext = pathinfo($_FILES['aadhaar_back_photo']['name'], PATHINFO_EXTENSION);
        $aadhaarBackPath = 'uploads/aadhaar/' . time() . '_aadhaar_back.' . $ext;
        move_uploaded_file($_FILES['aadhaar_back_photo']['tmp_name'], __DIR__ . '/../' . $aadhaarBackPath);
    }
    
    try {
        $pdo->beginTransaction();
        
        // 1. Handle Customer (Find or Create)
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ? AND shop_id = ? LIMIT 1");
        $stmt->execute([$custPhone, $shopId]);
        $existingCust = $stmt->fetch();
        
        $custData = [
            $shopId, $custName, $custPhone, $custCity, $custAddr, $landmark,
            $refs['ref_1_name'], $refs['ref_1_phone'], $refs['ref_1_relation'],
            $refs['ref_2_name'], $refs['ref_2_phone'], $refs['ref_2_relation'],
            $refs['ref_3_name'], $refs['ref_3_phone'], $refs['ref_3_relation'],
            $refs['ref_4_name'], $refs['ref_4_phone'], $refs['ref_4_relation'],
            $custPhotoPath, $aadhaarPhotoPath, $aadhaarBackPath
        ];

        if ($existingCust) {
            $custId = $existingCust['id'];
            
            $fields = [
                "name=?", "city=?", "address=?", "landmark=?", 
                "ref_1_name=?", "ref_1_phone=?", "ref_1_relation=?",
                "ref_2_name=?", "ref_2_phone=?", "ref_2_relation=?",
                "ref_3_name=?", "ref_3_phone=?", "ref_3_relation=?",
                "ref_4_name=?", "ref_4_phone=?", "ref_4_relation=?"
            ];
            $updParams = [
                $custName, $custCity, $custAddr, $landmark,
                $refs['ref_1_name'], $refs['ref_1_phone'], $refs['ref_1_relation'],
                $refs['ref_2_name'], $refs['ref_2_phone'], $refs['ref_2_relation'],
                $refs['ref_3_name'], $refs['ref_3_phone'], $refs['ref_3_relation'],
                $refs['ref_4_name'], $refs['ref_4_phone'], $refs['ref_4_relation']
            ];

            if ($custPhotoPath) {
                $fields[] = "customer_photo=?";
                $updParams[] = $custPhotoPath;
            }
            if ($aadhaarPhotoPath) {
                $fields[] = "aadhaar_photo=?";
                $updParams[] = $aadhaarPhotoPath;
            }
            if ($aadhaarBackPath) {
                $fields[] = "aadhaar_back_photo=?";
                $updParams[] = $aadhaarBackPath;
            }

            $updParams[] = $custId;
            $sql = "UPDATE customers SET " . implode(", ", $fields) . " WHERE id=?";
            $upd = $pdo->prepare($sql);
            $upd->execute($updParams);
        } else {
            $ins = $pdo->prepare("INSERT INTO customers (shop_id, name, phone, city, address, landmark, 
                ref_1_name, ref_1_phone, ref_1_relation,
                ref_2_name, ref_2_phone, ref_2_relation,
                ref_3_name, ref_3_phone, ref_3_relation,
                ref_4_name, ref_4_phone, ref_4_relation,
                customer_photo, aadhaar_photo, aadhaar_back_photo) 
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $ins->execute($custData);
            $custId = $pdo->lastInsertId();
        }
        
        // 2. Create Loan
        $stmt = $pdo->prepare("INSERT INTO loans (shop_id, customer_id, reference_by, sale_date, loan_number, item_name, model_detail, purchased_price, total_price, down_payment, interest_amount, remaining_amount, emi_months, emi_amount, emi_due_day, first_emi_date, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$shopId, $custId, $refBy, $saleDate, $loanNum, $item, $modelDetail, $purchasedPrice, $price, $down, $interestAmount, $remaining, $months, $emiAmount, $dueDay, $firstEmi, $_SESSION['user_id']]);
        $loanId = $pdo->lastInsertId();
        
        generateEMISchedule($loanId, $remaining, $months, $firstEmi, $emiAmount);
        
        $pdo->commit();
        setFlash('success', 'Sale recorded successfully with full details!');
        header("Location: view.php?id=$loanId");
        exit;
    } catch(Exception $e) {
        $pdo->rollBack();
        setFlash('error', 'Error: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">New Sale / Loan (Detailed)</h1>
        <p class="page-subtitle">Complete all details including references and photos.</p>
    </div>
    <a href="index.php" class="btn btn-outline">Cancel</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" id="loanForm">
            <div class="dashboard-section-label">1. Basic Info & Customer Details</div>
            <div class="form-grid mb-4">
                <?php if (!isShopAdmin()): ?>
                <div class="form-group full">
                    <label>Select Shop *</label>
                    <select name="shop_id" required>
                        <option value="">-- Choose Shop --</option>
                        <?php foreach($shops as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" name="shop_id" value="<?= getShopId() ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label>Sale Date *</label>
                    <input type="date" name="sale_date" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label>Reference By</label>
                    <input type="text" name="reference_by" placeholder="Person who referred">
                </div>
                <div class="form-group">
                    <label>Manual Loan Number (Optional)</label>
                    <input type="text" name="manual_loan_number" placeholder="Enter manual # or leave blank">
                    <small class="text-muted" style="font-size: 11px;">Khali chhodne par auto-generate hoga.</small>
                </div>

                <div class="form-group">
                    <label>Customer Name *</label>
                    <input type="text" name="customer_name" required placeholder="Full Name">
                </div>
                <div class="form-group">
                    <label>Phone Number *</label>
                    <input type="tel" name="customer_phone" required pattern="[0-9]{10,12}" placeholder="10 digit number">
                </div>
                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="customer_city" placeholder="City">
                </div>
                <div class="form-group">
                    <label>Near Landmark</label>
                    <input type="text" name="landmark" placeholder="e.g. Near Shiv Mandir">
                </div>
                <div class="form-group full">
                    <label>Full Address</label>
                    <textarea name="customer_address" placeholder="Complete address..."></textarea>
                </div>
            </div>

            <div class="dashboard-section-label">2. References (Guarantors)</div>
            <div class="form-grid responsive-grid mb-4">
                <?php for($i=1; $i<=4; $i++): 
                    $required = ($i <= 2) ? 'required' : '';
                ?>
                <div style="padding: 15px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface2);">
                    <h4 style="margin-bottom: 10px; font-size: 13px; color: var(--accent);">Reference #<?= $i ?> <?= $i<=2 ? '*' : '' ?></h4>
                    <div class="form-group mb-2">
                        <label>Name</label>
                        <input type="text" name="ref_<?= $i ?>_name" placeholder="Name" <?= $required ?>>
                    </div>
                    <div class="form-group mb-2">
                        <label>Phone</label>
                        <input type="tel" name="ref_<?= $i ?>_phone" placeholder="Phone" <?= $required ?>>
                    </div>
                    <div class="form-group">
                        <label>Relation</label>
                        <input type="text" name="ref_<?= $i ?>_relation" placeholder="Relation" <?= $required ?>>
                    </div>
                </div>
                <?php endfor; ?>
            </div>

            <div class="dashboard-section-label">3. Product & Payment Details</div>
            <div class="form-grid mb-4">
                <div class="form-group">
                    <label>Item Name *</label>
                    <input type="text" name="item_name" required placeholder="e.g. Mobile, Fridge">
                </div>
                <div class="form-group">
                    <label>Model Detail *</label>
                    <input type="text" name="model_detail" required placeholder="e.g. iPhone 15 Pro Max 256GB">
                </div>
                <div class="form-group">
                    <label>Sell Price (₹) *</label>
                    <input type="number" step="0.01" name="total_price" id="total_price" required oninput="calc()">
                </div>
                <div class="form-group">
                    <label>Cost Price (₹) *</label>
                    <input type="number" step="0.01" name="purchased_price" id="purchased_price" required oninput="calc()">
                </div>
                <div class="form-group">
                    <label>Down Payment (₹)</label>
                    <input type="number" step="0.01" name="down_payment" id="down_payment" value="0" required oninput="calc()">
                </div>
                <div class="form-group">
                    <label>Total Interest Amount (₹)</label>
                    <input type="number" step="0.01" name="interest_amount" id="interest_amount" value="0" required oninput="calc()">
                </div>
                <div class="form-group">
                    <label>Remaining Amount (₹)</label>
                    <input type="number" id="remaining" readonly style="background: var(--surface2); cursor: not-allowed;">
                </div>
                <div class="form-group">
                    <label>EMI Duration (Months) *</label>
                    <input type="number" name="emi_months" id="months" min="1" max="60" required oninput="calc()">
                </div>
                <div class="form-group">
                    <label>Calculated EMI / Month (₹)</label>
                    <input type="text" id="emi_calc" readonly style="background: var(--surface2); color: var(--success); font-weight: bold;">
                </div>
                <div class="form-group">
                    <label>First EMI Due Date *</label>
                    <input type="date" name="first_emi_date" required>
                </div>
            </div>

            <div class="dashboard-section-label">4. Documents & Photos</div>
            <div class="form-grid mb-4">
                <div class="form-group">
                    <label>Customer Photo</label>
                    <input type="file" name="customer_photo" id="customer_photo" accept="image/*" style="display:none">
                    <input type="file" id="customer_photo_cam" accept="image/*" capture="user" style="display:none" onchange="photoFromCam('customer_photo',this)">
                    <div class="flex gap-2 mb-2">
                        <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('customer_photo_cam').click()">&#128247; Camera</button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('customer_photo').click()">&#128444; Gallery</button>
                    </div>
                    <img id="preview_customer_photo" style="display:none; max-height:80px; border-radius:6px; border:1px solid var(--border);">
                    <span id="label_customer_photo" class="text-muted" style="font-size:12px;">No file chosen</span>
                </div>
                <div class="form-group">
                    <label>Aadhaar Card (Front)</label>
                    <input type="file" name="aadhaar_photo" id="aadhaar_photo" accept="image/*" style="display:none">
                    <input type="file" id="aadhaar_photo_cam" accept="image/*" capture="environment" style="display:none" onchange="photoFromCam('aadhaar_photo',this)">
                    <div class="flex gap-2 mb-2">
                        <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('aadhaar_photo_cam').click()">&#128247; Camera</button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('aadhaar_photo').click()">&#128444; Gallery</button>
                    </div>
                    <img id="preview_aadhaar_photo" style="display:none; max-height:80px; border-radius:6px; border:1px solid var(--border);">
                    <span id="label_aadhaar_photo" class="text-muted" style="font-size:12px;">No file chosen</span>
                </div>
                <div class="form-group">
                    <label>Aadhaar Card (Back)</label>
                    <input type="file" name="aadhaar_back_photo" id="aadhaar_back_photo" accept="image/*" style="display:none">
                    <input type="file" id="aadhaar_back_photo_cam" accept="image/*" capture="environment" style="display:none" onchange="photoFromCam('aadhaar_back_photo',this)">
                    <div class="flex gap-2 mb-2">
                        <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('aadhaar_back_photo_cam').click()">&#128247; Camera</button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('aadhaar_back_photo').click()">&#128444; Gallery</button>
                    </div>
                    <img id="preview_aadhaar_back_photo" style="display:none; max-height:80px; border-radius:6px; border:1px solid var(--border);">
                    <span id="label_aadhaar_back_photo" class="text-muted" style="font-size:12px;">No file chosen</span>
                </div>
            </div>

            <div class="mt-4 pt-4 border-top">
                <button type="submit" class="btn btn-primary" style="font-size: 16px; padding: 15px 30px; width: 100%;">Create Loan & Generate EMI Schedule</button>
            </div>
        </form>
    </div>
</div>

<script>
function calc() {
    let price = parseFloat(document.getElementById('total_price').value) || 0;
    let down = parseFloat(document.getElementById('down_payment').value) || 0;
    let interest = parseFloat(document.getElementById('interest_amount').value) || 0;
    let months = parseInt(document.getElementById('months').value) || 1;
    
    let rem = (price - down) + interest;
    if(rem < 0) rem = 0;
    document.getElementById('remaining').value = rem.toFixed(2);
    
    let emi = (months > 0) ? rem / months : 0;
    document.getElementById('emi_calc').value = '₹ ' + emi.toFixed(2);
}

// Photo handling and preview
function photoFromCam(targetId, camInput) {
    if (camInput.files && camInput.files[0]) {
        const targetInput = document.getElementById(targetId);
        // Create a new FileList containing the camera photo
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(camInput.files[0]);
        targetInput.files = dataTransfer.files;
        
        // Trigger change to show preview
        showPreview(targetInput);
    }
}

function showPreview(input) {
    const id = input.id.replace('_cam', '');
    const preview = document.getElementById('preview_' + id);
    const label = document.getElementById('label_' + id);
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
            label.textContent = input.files[0].name;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

document.querySelectorAll('input[type="file"]').forEach(input => {
    input.addEventListener('change', function() {
        if (!this.id.endsWith('_cam')) {
            showPreview(this);
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
