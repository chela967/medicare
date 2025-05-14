<?php
session_start();
require 'header.php';

// Validate inputs
$type = isset($_GET['type']) ? htmlspecialchars($_GET['type']) : '';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Verify valid success type
if (!in_array($type, ['appointment', 'pharmacy'])) {
    header("Location: index.php");
    exit();
}

// Get details from database
require 'config.php';
$details = [];

if ($type === 'appointment') {
    $query = "SELECT a.*, u.name AS doctor_name, s.name AS specialty 
              FROM appointments a
              JOIN doctors d ON a.doctor_id = d.id
              JOIN users u ON d.user_id = u.id
              JOIN specialties s ON d.specialty_id = s.id
              WHERE a.id = ? AND a.patient_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $id, $_SESSION['user']['id']);
    $stmt->execute();
    $details = $stmt->get_result()->fetch_assoc();
} else {
    $query = "SELECT o.*, SUM(oi.price * oi.quantity) AS total 
              FROM orders o
              JOIN order_items oi ON o.id = oi.order_id
              WHERE o.id = ? AND o.user_id = ?
              GROUP BY o.id";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $id, $_SESSION['user']['id']);
    $stmt->execute();
    $details = $stmt->get_result()->fetch_assoc();
}
?>
<style>
    .success-container {
        max-width: 800px;
        margin: 2rem auto;
        padding: 2rem;
        background: white;
        border-radius: 10px;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
    }

    .success-header {
        color: #28a745;
        text-align: center;
        margin-bottom: 2rem;
    }

    .success-icon {
        font-size: 5rem;
        color: #28a745;
        margin-bottom: 1rem;
    }

    .details-card {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .detail-row {
        display: flex;
        margin-bottom: 0.8rem;
        padding-bottom: 0.8rem;
        border-bottom: 1px solid #eee;
    }

    .detail-label {
        font-weight: 600;
        min-width: 150px;
        color: #495057;
    }

    .detail-value {
        color: #212529;
    }

    .action-buttons {
        display: flex;
        justify-content: center;
        gap: 1rem;
        margin-top: 2rem;
    }

    .btn-download {
        background: #17a2b8;
        border-color: #17a2b8;
    }
</style>

<div class="success-container">
    <div class="text-center">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h1 class="success-header">
            <?= $type === 'appointment' ? 'Appointment Booked Successfully!' : 'Order Placed Successfully!' ?>
        </h1>
    </div>

    <div class="details-card">
        <h4 class="mb-4"><i class="fas fa-info-circle me-2"></i><?= $type === 'appointment' ? 'Appointment' : 'Order' ?>
            Details</h4>

        <?php if ($type === 'appointment' && $details): ?>
            <div class="detail-row">
                <div class="detail-label">Doctor:</div>
                <div class="detail-value">Dr. <?= htmlspecialchars($details['doctor_name']) ?>
                    (<?= htmlspecialchars($details['specialty']) ?>)</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Date:</div>
                <div class="detail-value"><?= date('F j, Y', strtotime($details['appointment_date'])) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Time:</div>
                <div class="detail-value"><?= date('g:i A', strtotime($details['appointment_time'])) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Reference ID:</div>
                <div class="detail-value">MED-APP-<?= $details['id'] ?></div>
            </div>
        <?php elseif ($details): ?>
            <div class="detail-row">
                <div class="detail-label">Order Number:</div>
                <div class="detail-value">MED-ORD-<?= $details['id'] ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Total Amount:</div>
                <div class="detail-value">UGX <?= number_format($details['total'], 2) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Status:</div>
                <div class="detail-value"><span class="badge bg-success">Paid</span></div>
            </div>
        <?php endif; ?>
    </div>

    <div class="text-center">
        <p class="text-muted mb-4">
            <i class="fas fa-envelope me-2"></i>
            A confirmation has been sent to your email address
        </p>
    </div>

    <div class="action-buttons">
        <a href="<?= $type === 'appointment' ? 'my_appointments.php' : 'my_orders.php' ?>" class="btn btn-primary">
            <i class="fas fa-list me-2"></i> View My <?= $type === 'appointment' ? 'Appointments' : 'Orders' ?>
        </a>
        <button class="btn btn-download text-white">
            <i class="fas fa-download me-2"></i> Download Receipt
        </button>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-home me-2"></i> Back to Home
        </a>
    </div>
</div>

<?php include 'footer.php'; ?>