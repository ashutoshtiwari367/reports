// ============================================
// EMI Tracker – App JS
// ============================================

document.addEventListener('DOMContentLoaded', () => {

    // ── Table Search ──
    const searchInputs = document.querySelectorAll('.search-input');
    searchInputs.forEach(input => {
        input.addEventListener('input', function() {
            const tableId = this.dataset.table;
            const term = this.value.toLowerCase();
            const rows = document.querySelectorAll(`#${tableId} tbody tr`);

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(term) ? '' : 'none';
            });
        });
    });

    // ── Flash Messages ──
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 4000);
    });

});

// ── Modals ──
function openModal(id) {
    document.getElementById(id).classList.add('open');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}

// Close modal on outside click
window.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
    }
});

// ── EMI Payment Modal ──
function openPaymentModal(emiId, amountDue) {
    document.getElementById('emi_id').value = emiId;
    document.getElementById('amount').value = amountDue;
    document.getElementById('amount').max = amountDue;
    openModal('paymentModal');
}
