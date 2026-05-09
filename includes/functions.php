<?php
require_once __DIR__ . '/../config/db.php';

// ─── Session ────────────────────────────────────────────────
function requireLogin(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: /auth/login.php');
        exit;
    }
}

function currentUser(): array {
    return $_SESSION['user'] ?? [];
}

function isSuperAdmin(): bool {
    $u = currentUser();
    return ($u['role'] ?? '') === 'admin';
}

function isShopAdmin(): bool {
    $u = currentUser();
    return ($u['role'] ?? '') === 'shop_admin';
}

function getShopId(): ?int {
    $u = currentUser();
    return isset($u['shop_id']) ? (int)$u['shop_id'] : null;
}

// ─── Currency ───────────────────────────────────────────────
function formatINR(float $amount): string {
    return '₹' . number_format($amount, 2);
}

function formatDate(string $date): string {
    return date('d-m-y', strtotime($date));
}

// ─── EMI Status ─────────────────────────────────────────────
function computeEMIStatus(array $emi): string {
    $today = date('Y-m-d');
    $paid  = (float)$emi['paid_amount'];
    $due   = (float)$emi['emi_amount'];

    if ($paid >= $due) return 'received';
    if ($paid > 0 && $paid < $due) return 'partial';
    if ($emi['due_date'] < $today) return 'overdue';
    return 'due';
}

function statusBadge(string $status): string {
    $map = [
        'due'      => ['label' => 'Due',      'class' => 'badge-due'],
        'received' => ['label' => 'Received', 'class' => 'badge-received'],
        'partial'  => ['label' => 'Partial',  'class' => 'badge-partial'],
        'overdue'  => ['label' => 'Overdue',  'class' => 'badge-overdue'],
    ];
    $b = $map[$status] ?? ['label' => ucfirst($status), 'class' => 'badge-due'];
    return "<span class=\"badge {$b['class']}\">{$b['label']}</span>";
}

// ─── Loan Number Generator ───────────────────────────────────
function generateLoanNumber(): string {
    global $pdo;
    $month = date('m');
    $year  = date('y');
    $prefix = "SS-$month-$year-";

    // Find the latest loan number for the current month
    $stmt = $pdo->prepare("SELECT loan_number FROM loans WHERE loan_number LIKE ? ORDER BY loan_number DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $lastLoan = $stmt->fetchColumn();

    if ($lastLoan) {
        // Extract the sequential part (last 3 digits)
        $parts = explode('-', $lastLoan);
        $lastSeq = (int)end($parts);
        $nextSeq = $lastSeq + 1;
    } else {
        $nextSeq = 1;
    }

    // Pad the sequence with zeros (e.g., 001)
    return $prefix . str_pad((string)$nextSeq, 3, '0', STR_PAD_LEFT);
}

// ─── EMI Schedule Generator ─────────────────────────────────
function generateEMISchedule(int $loanId, float $remaining, int $months, string $firstDate, float $emiAmount): void {
    global $pdo;
    $date = new DateTime($firstDate);
    for ($i = 1; $i <= $months; $i++) {
        // Last installment gets remainder to handle rounding
        $amount = ($i === $months) ? round($remaining - ($emiAmount * ($months - 1)), 2) : $emiAmount;
        $stmt = $pdo->prepare("INSERT INTO emi_schedule (loan_id, installment_number, due_date, emi_amount, status) VALUES (?,?,?,?,'due')");
        $stmt->execute([$loanId, $i, $date->format('Y-m-d'), $amount]);
        $date->modify('+1 month');
    }
}

// ─── Sync EMI paid_amount & status ──────────────────────────
function syncEMIStatus(int $emiId): void {
    global $pdo;
    $paid = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM emi_payments WHERE emi_id=?");
    $paid->execute([$emiId]);
    $totalPaid = (float)$paid->fetchColumn();

    $emi = $pdo->prepare("SELECT emi_amount, due_date FROM emi_schedule WHERE id=?");
    $emi->execute([$emiId]);
    $row = $emi->fetch();

    $status = computeEMIStatus([
        'paid_amount' => $totalPaid,
        'emi_amount'  => $row['emi_amount'],
        'due_date'    => $row['due_date'],
    ]);

    $upd = $pdo->prepare("UPDATE emi_schedule SET paid_amount=?, status=? WHERE id=?");
    $upd->execute([$totalPaid, $status, $emiId]);

    // Sync loan status
    syncLoanStatus((int)$pdo->query("SELECT loan_id FROM emi_schedule WHERE id=$emiId")->fetchColumn());
}

function syncLoanStatus(int $loanId): void {
    global $pdo;
    $counts = $pdo->prepare("SELECT
        COUNT(*) AS total,
        SUM(status='received') AS received
        FROM emi_schedule WHERE loan_id=?");
    $counts->execute([$loanId]);
    $r = $counts->fetch();
    if ($r['total'] > 0 && $r['received'] == $r['total']) {
        $pdo->prepare("UPDATE loans SET status='completed' WHERE id=?")->execute([$loanId]);
    }
}

// ─── WhatsApp Link ──────────────────────────────────────────
function whatsappLink(string $phone, string $message): string {
    $clean = preg_replace('/\D/', '', $phone);
    if (strlen($clean) === 10) $clean = '91' . $clean;
    return "https://wa.me/{$clean}?text=" . rawurlencode($message);
}

// ─── Overdue count for sidebar badge ────────────────────────
function getOverdueCount(): int {
    global $pdo;
    $today = date('Y-m-d');
    return (int)$pdo->query("SELECT COUNT(*) FROM emi_schedule WHERE status='overdue' OR (due_date < '$today' AND status='due')")->fetchColumn();
}

function getTodayDueCount(): int {
    global $pdo;
    $today = date('Y-m-d');
    return (int)$pdo->query("SELECT COUNT(*) FROM emi_schedule WHERE due_date='$today' AND status IN('due','partial')")->fetchColumn();
}

// ─── Flash Messages ──────────────────────────────────────────
function setFlash(string $type, string $msg): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function showFlash(): void {
    $f = getFlash();
    if ($f) {
        echo "<div class=\"alert alert-{$f['type']}\">{$f['msg']}</div>";
    }
}

// ─── CSRF ────────────────────────────────────────────────────
function csrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function verifyCsrf(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        die('Invalid CSRF token.');
    }
}

