<div class="modal-overlay" id="paymentModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Record EMI Payment</h3>
            <button class="modal-close" onclick="closeModal('paymentModal')">&times;</button>
        </div>
        <form action="/emi/emis/mark_payment.php" method="POST">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <input type="hidden" name="emi_id" id="emi_id" value="">
            <input type="hidden" name="return_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
            
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group full">
                        <label>Received Amount (₹)</label>
                        <input type="number" step="0.01" name="amount" id="amount" required>
                        <div class="form-hint">Enter the actual amount received. If less than total due, it will be marked as Partial.</div>
                    </div>
                    <div class="form-group">
                        <label>Payment Mode</label>
                        <select name="payment_mode" required>
                            <option value="cash">Cash</option>
                            <option value="upi">UPI / Online</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Payment Date</label>
                        <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group full">
                        <label>Reference Number (Txn ID, Cheque No)</label>
                        <input type="text" name="reference_number">
                    </div>
                    <div class="form-group full">
                        <label>Internal Note (Optional)</label>
                        <textarea name="notes" placeholder="e.g. Paid in shop by relative"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('paymentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Payment</button>
            </div>
        </form>
    </div>
</div>
