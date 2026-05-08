<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
global $pdo;

if (!isSuperAdmin()) {
    setFlash('error', 'Only Super Admin can edit loans.');
    header('Location: index.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) die('Invalid ID');

$stmt = $pdo->prepare("SELECT * FROM loans WHERE id = ?");
$stmt->execute([$id]);
$loan = $stmt->fetch();

if (!$loan) die('Loan not found');

// Get total paid amount for this loan
$paidStmt = $pdo->prepare("
    SELECT COALESCE(SUM(paid_amount), 0) 
    FROM emi_schedule 
    WHERE loan_id = ? AND status IN ('received', 'partial')
");
$paidStmt->execute([$id]);
$totalPaid = (float)$paidStmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $saleDate = $_POST['sale_date'] ?? $loan['sale_date'];
    $item = $_POST['item_name'];
    $modelDetail = trim($_POST['model_detail'] ?? '');
    $price = (float)$_POST['total_price'];
    $down = (float)$_POST['down_payment'];
    $interestAmount = (float)$_POST['interest_amount'];
    $months = (int)$_POST['emi_months'];
    
    // Recalculate New Liability (interest is per month)
    $totalInterest = $interestAmount * $months;
    $newTotalLiability = ($price - $down) + $totalInterest;
    
    // Remaining balance to be paid
    $newRemainingBalance = $newTotalLiability - $totalPaid;
    if ($newRemainingBalance < 0) $newRemainingBalance = 0;
    
    try {
        $pdo->beginTransaction();
        
        // 1. Update Loan Table
        $upd = $pdo->prepare("UPDATE loans SET 
            sale_date = ?, item_name = ?, model_detail = ?, 
            total_price = ?, down_payment = ?, interest_amount = ?, 
            remaining_amount = ?, emi_months = ? 
            WHERE id = ?");
        $upd->execute([
            $saleDate, $item, $modelDetail, 
            $price, $down, $interestAmount, 
            $newTotalLiability, $months, 
            $id
        ]);
        
        // 2. Delete all unpaid EMIs
        // We only delete EMIs that have 0 paid_amount
        $pdo->prepare("DELETE FROM emi_schedule WHERE loan_id = ? AND paid_amount <= 0")->execute([$id]);
        
        // 3. Count how many EMIs are kept (i.e. partially or fully paid)
        $keptStmt = $pdo->prepare("SELECT COUNT(*) FROM emi_schedule WHERE loan_id = ?");
        $keptStmt->execute([$id]);
        $keptCount = (int)$keptStmt->fetchColumn();
        
        // 4. Generate new EMIs for the remaining balance if > 0
        if ($newRemainingBalance > 0) {
            $remainingMonths = $months - $keptCount;
            if ($remainingMonths <= 0) $remainingMonths = 1; // Fallback to 1 month if they reduced months too much
            
            $newEmiAmount = round($newRemainingBalance / $remainingMonths, 2);
            
            // Find the date of the last kept EMI to start the new ones
            $lastEmiStmt = $pdo->prepare("SELECT due_date FROM emi_schedule WHERE loan_id = ? ORDER BY installment_number DESC LIMIT 1");
            $lastEmiStmt->execute([$id]);
            $lastEmiDate = $lastEmiStmt->fetchColumn();
            
            if ($lastEmiDate) {
                $nextDate = new DateTime($lastEmiDate);
                $nextDate->modify('+1 month');
                $startDate = $nextDate->format('Y-m-d');
            } else {
                $startDate = date('Y-m-d', strtotime('+1 month')); // fallback
            }
            
            // Generate the rest of the schedule
            $date = new DateTime($startDate);
            for ($i = 1; $i <= $remainingMonths; $i++) {
                $instNum = $keptCount + $i;
                $amount = ($i === $remainingMonths) ? round($newRemainingBalance - ($newEmiAmount * ($remainingMonths - 1)), 2) : $newEmiAmount;
                
                $ins = $pdo->prepare("INSERT INTO emi_schedule (loan_id, installment_number, due_date, emi_amount, status) VALUES (?,?,?,?,'due')");
                $ins->execute([$id, $instNum, $date->format('Y-m-d'), $amount]);
                $date->modify('+1 month');
            }
        }
        
        // 5. Sync loan status
        syncLoanStatus($id);
        
        $pdo->commit();
        setFlash('success', 'Loan details updated and schedule recalculated successfully.');
        header("Location: view.php?id=$id");
        exit;
    } catch(Exception $e) {
        $pdo->rollBack();
        setFlash('error', 'Error updating loan: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Edit Loan #<?= htmlspecialchars($loan['loan_number']) ?></h1>
        <p class="page-subtitle">Update financial details and recalculate EMI schedule.</p>
    </div>
    <a href="view.php?id=<?= $id ?>" class="btn btn-outline">Cancel</a>
</div>

<div class="card">
    <div class="card-body">
        <div class="alert alert-warning mb-4">
            <strong>Total Paid So Far: <?= formatINR($totalPaid) ?></strong><br>
            If you change the financial details, all unpaid EMIs will be deleted and a new schedule will be generated for the remaining balance.
        </div>
        
        <form method="POST" id="loanForm">
            <div class="form-grid mb-4">
                <div class="form-group">
                    <label>Sale Date</label>
                    <input type="date" name="sale_date" required value="<?= $loan['sale_date'] ?>">
                </div>
                <div class="form-group">
                    <label>Item Name</label>
                    <input type="text" name="item_name" required value="<?= htmlspecialchars($loan['item_name']) ?>">
                </div>
                <div class="form-group">
                    <label>Model Detail</label>
                    <input type="text" name="model_detail" required value="<?= htmlspecialchars($loan['model_detail'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Total Item Price (₹)</label>
                    <input type="number" step="0.01" name="total_price" id="total_price" required value="<?= $loan['total_price'] ?>" oninput="calc()">
                </div>
                <div class="form-group">
                    <label>Down Payment (₹)</label>
                    <input type="number" step="0.01" name="down_payment" id="down_payment" required value="<?= $loan['down_payment'] ?>" oninput="calc()">
                </div>
                <div class="form-group">
                    <label>Interest Amount (Per Month) (₹)</label>
                    <input type="number" step="0.01" name="interest_amount" id="interest_amount" required value="<?= $loan['interest_amount'] ?>" oninput="calc()">
                </div>
                <div class="form-group">
                    <label>New Total Liability (₹)</label>
                    <input type="number" id="remaining" readonly style="background: var(--surface2); cursor: not-allowed;" value="<?= $loan['remaining_amount'] ?>">
                </div>
                <div class="form-group">
                    <label>Total EMI Months</label>
                    <input type="number" name="emi_months" id="months" min="1" max="60" required value="<?= $loan['emi_months'] ?>">
                </div>
            </div>

            <div class="mt-4 pt-4 border-top">
                <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure? This will recalculate all unpaid EMIs.')" style="font-size: 16px; padding: 15px 30px; width: 100%;">Save Changes & Recalculate</button>
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
    
    let rem = (price - down) + (interest * months);
    if(rem < 0) rem = 0;
    document.getElementById('remaining').value = rem.toFixed(2);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
