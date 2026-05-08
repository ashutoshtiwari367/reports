<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
global $pdo;

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$id = (int)($_GET['id'] ?? 0);
if(!$id) die('Invalid ID');

$stmt = $pdo->prepare("
    SELECT l.*, l.id as loan_id, s.name as shop_name, s.address as shop_address, s.phone as shop_phone,
           c.*, c.name as customer_name, c.phone as customer_phone
    FROM loans l 
    JOIN shops s ON l.shop_id = s.id 
    JOIN customers c ON l.customer_id = c.id 
    WHERE l.id = ?
");
$stmt->execute([$id]);
$l = $stmt->fetch();
if(!$l) die('Loan not found');

$emis = $pdo->prepare("SELECT * FROM emi_schedule WHERE loan_id = ? ORDER BY installment_number ASC");
$emis->execute([$id]);
$schedule = $emis->fetchAll();

// Hide interest amount by adding it to display price
$displayItemPrice = $l['total_price'] + $l['interest_amount'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice - <?= htmlspecialchars($l['loan_number']) ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; margin: 0; padding: 0; line-height: 1.2; background: #f0f0f0; }
        .invoice-container { width: 210mm; margin: auto; }
        .invoice-page { 
            width: 210mm; 
            height: 296mm; /* Slightly less than 297mm to avoid spill */
            padding: 15mm; 
            margin: 0 auto; 
            background: #fff; 
            position: relative;
            box-sizing: border-box;
            overflow: hidden;
        }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; }
        .ss-group-title { color: #1e293b; font-size: 24px; font-weight: 800; margin: 0; letter-spacing: -1px; }
        .shop-info h2 { margin: 2px 0 0; color: #475569; font-size: 16px; }
        .invoice-details { text-align: right; }
        .section-title { background: #f8fafc; padding: 6px 10px; font-weight: bold; margin: 15px 0 8px; border-left: 4px solid #1e293b; text-transform: uppercase; font-size: 12px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        table th, table td { border: 1px solid #e2e8f0; padding: 6px 8px; text-align: left; font-size: 11px; }
        table th { background-color: #f1f5f9; color: #475569; }
        .total-row { font-weight: bold; background: #f8fafc; }
        .footer-note { position: absolute; bottom: 10mm; left: 15mm; right: 15mm; border-top: 1px solid #eee; padding-top: 8px; display: flex; justify-content: space-between; font-size: 10px; color: #64748b; }
        .doc-images { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px; }
        .doc-item { border: 1px solid #e2e8f0; padding: 8px; border-radius: 6px; text-align: center; }
        .doc-item img { max-width: 100%; max-height: 140px; border-radius: 4px; object-fit: contain; }
        .doc-label { font-size: 10px; color: #64748b; margin-bottom: 4px; font-weight: 600; display: block; }
        
        @media print {
            body { background: none; }
            .no-print { display: none; }
            .invoice-page { margin: 0; box-shadow: none; page-break-after: always; }
        }
        .btn-print { background: #1e293b; color: #fff; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>

<div class="no-print" style="text-align:center; padding: 20px;">
    <button id="download-pdf" class="btn-print">Download 2-Page Detailed PDF</button>
</div>

<div id="invoice-content" class="invoice-container">
    <!-- PAGE 1: SALE SUMMARY -->
    <div class="invoice-page">
        <div class="header">
            <div class="shop-info">
                <h1 class="ss-group-title">SS GROUP</h1>
                <h2><?= htmlspecialchars($l['shop_name']) ?></h2>
                <p style="font-size: 12px; margin-top: 5px;"><?= nl2br(htmlspecialchars($l['shop_address'])) ?><br>
                Contact: <?= htmlspecialchars($l['shop_phone']) ?></p>
            </div>
            <div class="invoice-details">
                <h1 style="margin:0; color:#1e293b; font-size: 32px;">INVOICE</h1>
                <p><strong>Loan ID:</strong> <?= htmlspecialchars($l['loan_number']) ?><br>
                <strong>Sale Date:</strong> <?= date('d-m-Y', strtotime($l['sale_date'])) ?></p>
            </div>
        </div>

        <div class="grid">
            <div>
                <div class="section-title">Customer Information</div>
                <p><strong>Name:</strong> <?= htmlspecialchars($l['customer_name']) ?><br>
                <strong>Phone:</strong> <?= htmlspecialchars($l['customer_phone']) ?><br>
                <strong>Address:</strong> <?= htmlspecialchars($l['address']) ?><br>
                <strong>Landmark:</strong> <?= htmlspecialchars($l['landmark']) ?></p>
            </div>
            <div>
                <div class="section-title">Product Description</div>
                <p><strong>Item:</strong> <?= htmlspecialchars($l['item_name']) ?><br>
                <strong>Model:</strong> <?= htmlspecialchars($l['model_detail']) ?><br>
                <strong>Period:</strong> <?= $l['emi_months'] ?> Months EMI<br>
                <strong>Ref By:</strong> <?= htmlspecialchars($l['reference_by'] ?: 'Direct') ?></p>
            </div>
        </div>

        <div class="section-title">Financial Summary</div>
        <table>
            <tr>
                <th>Description of Charges</th>
                <th style="text-align: right;">Amount (INR)</th>
            </tr>
            <tr>
                <td>Product Sale Value (Inclusive of all charges)</td>
                <td style="text-align: right;"><?= formatINR($displayItemPrice) ?></td>
            </tr>
            <tr>
                <td>Advance / Down Payment Received</td>
                <td style="text-align: right; color: #10b981;">- <?= formatINR($l['down_payment']) ?></td>
            </tr>
            <tr class="total-row">
                <td>Financed Amount (Principal + Finance)</td>
                <td style="text-align: right; font-size: 15px;"><?= formatINR($l['remaining_amount']) ?></td>
            </tr>
            <tr>
                <td style="font-size: 15px;"><strong>Fixed Monthly EMI</strong></td>
                <td style="text-align: right; font-size: 18px; color: #1e293b;"><strong><?= formatINR($l['emi_amount']) ?></strong></td>
            </tr>
        </table>

        <div class="section-title">References / Guarantors</div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
            <?php for($i=1; $i<=4; $i++): ?>
            <div style="padding: 6px; border: 1px solid #e2e8f0; border-radius: 6px; background: #f8fafc;">
                <div style="color: #1e293b; font-weight: bold; font-size: 10px; margin-bottom: 2px;">Reference #<?= $i ?></div>
                <div style="font-size: 11px; line-height: 1.1;">
                    <strong>Name:</strong> <?= htmlspecialchars($l["ref_{$i}_name"] ?: '___________') ?><br>
                    <strong>Phone:</strong> <?= htmlspecialchars($l["ref_{$i}_phone"] ?: '___________') ?><br>
                    <strong>Relation:</strong> <?= htmlspecialchars($l["ref_{$i}_relation"] ?: '___________') ?>
                </div>
            </div>
            <?php endfor; ?>
        </div>

        <?php 
        $page1_limit = 12;
        $page1_emis = array_slice($schedule, 0, $page1_limit);
        $page2_emis = array_slice($schedule, $page1_limit);
        ?>

        <?php if(!empty($page1_emis)): ?>
        <div class="section-title">Repayment Schedule (Part 1)</div>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($page1_emis as $emi): ?>
                <tr>
                    <td><?= $emi['installment_number'] ?></td>
                    <td><?= date('d-m-Y', strtotime($emi['due_date'])) ?></td>
                    <td><?= formatINR($emi['emi_amount']) ?></td>
                    <td><?= ucfirst($emi['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <div class="footer-note">
            <span>Powered by EMI Tracker</span>
            <span>Shop: <strong><?= htmlspecialchars($l['shop_name']) ?></strong></span>
            <span>Page 1 of 2</span>
        </div>
    </div>

    <!-- PAGE 2: SCHEDULE (CONTINUED) & DOCUMENTS -->
    <div class="invoice-page">
        <?php if(!empty($page2_emis)): ?>
        <div class="section-title" style="margin-top: 0;">Repayment Schedule (Continued)</div>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($page2_emis as $emi): ?>
                <tr>
                    <td><?= $emi['installment_number'] ?></td>
                    <td><?= date('d-m-Y', strtotime($emi['due_date'])) ?></td>
                    <td><?= formatINR($emi['emi_amount']) ?></td>
                    <td><?= ucfirst($emi['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <div class="section-title" style="<?= empty($page2_emis)?'margin-top:0;':'' ?>">Verification Documents</div>
        <div class="doc-images">
            <div class="doc-item">
                <span class="doc-label">Customer Profile</span>
                <?php if($l['customer_photo']): ?>
                    <img src="../<?= $l['customer_photo'] ?>">
                <?php else: ?>
                    <div style="padding: 40px; color: #cbd5e1;">Photo Not Available</div>
                <?php endif; ?>
            </div>
            <div class="doc-item">
                <span class="doc-label">Aadhaar (Front Side)</span>
                <?php if($l['aadhaar_photo']): ?>
                    <img src="../<?= $l['aadhaar_photo'] ?>">
                <?php else: ?>
                    <div style="padding: 40px; color: #cbd5e1;">Document Not Available</div>
                <?php endif; ?>
            </div>
            <div class="doc-item" style="grid-column: span 2;">
                <span class="doc-label">Aadhaar (Back Side)</span>
                <?php if($l['aadhaar_back_photo']): ?>
                    <img src="../<?= $l['aadhaar_back_photo'] ?>" style="max-height: 250px;">
                <?php else: ?>
                    <div style="padding: 40px; color: #cbd5e1;">Document Not Available</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="footer-note">
            <span>Loan #<?= htmlspecialchars($l['loan_number']) ?></span>
            <span>Shop: <strong><?= htmlspecialchars($l['shop_name']) ?></strong></span>
            <span>Page 2 of 2</span>
        </div>
    </div>
</div>

<script>
    document.getElementById('download-pdf').addEventListener('click', function () {
        const btn = this;
        const originalText = btn.innerText;
        btn.innerText = 'Generating PDF... Please wait';
        btn.disabled = true;
        btn.style.opacity = '0.7';

        const element = document.getElementById('invoice-content');
        const opt = {
            margin:       0,
            filename:     'Invoice_<?= str_replace(' ', '_', $l['loan_number']) ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { 
                scale: 2, 
                useCORS: true,
                logging: false,
                letterRendering: true
            },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
            pagebreak:    { mode: ['css', 'legacy'], after: '.invoice-page' }
        };

        html2pdf().set(opt).from(element).save().then(() => {
            btn.innerText = originalText;
            btn.disabled = false;
            btn.style.opacity = '1';
        }).catch(err => {
            console.error('PDF Generation Error:', err);
            alert('Error generating PDF. Please try again.');
            btn.innerText = originalText;
            btn.disabled = false;
        });
    });
</script>

</body>
</html>
