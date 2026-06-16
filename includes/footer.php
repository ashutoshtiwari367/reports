        </div><!-- end page-content -->
    </div><!-- end main-content -->
</div><!-- end app-layout -->

<script src="/assets/js/app.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof flatpickr !== 'undefined') {
        flatpickr("input[type='date']", {
            altInput: true,
            altFormat: "d/m/y",
            dateFormat: "Y-m-d",
            allowInput: true
        });
    }
});
</script>
</body>
</html>

