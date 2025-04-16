<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JS -->
<script src="js/script.js"></script>

<!-- Appointment Date Picker Restrictions -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Set minimum date to today
        const dateField = document.getElementById('date');
        if (dateField) {
            dateField.min = new Date().toISOString().split('T')[0];

            // Disable weekends
            dateField.addEventListener('input', function () {
                const selectedDate = new Date(this.value);
                const day = selectedDate.getDay();

                if (day === 0 || day === 6) { // Sunday or Saturday
                    alert('We are closed on weekends. Please select a weekday.');
                    this.value = '';
                }
            });
        }
    });
</script>
</body>

</html>