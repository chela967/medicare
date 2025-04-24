<?php
// patient_dashboard.php - Enhanced with Doctor Communication Features

// 1. Secure Session Start & Configuration
// --------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

// Check authentication and role
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'patient') {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Please log in as a patient to access the dashboard.'];
    header('Location: auth.php');
    exit;
}

// 2. Include Configurations & Set Page Title
// -----------------------------------------
require_once __DIR__ . '/config.php';
$page_title = 'Patient Dashboard';
require_once __DIR__ . '/header.php';

// 3. Database Fetching with Error Handling
// ----------------------------------------
$user_id = $_SESSION['user']['id'];
$user_name = $_SESSION['user']['name'];
$db_error_message = null;

// Initialize data arrays
$upcoming_appointments = [];
$recent_orders = [];
$recent_consultations = [];
$unread_count = 0;

if (isset($mysqli) && $mysqli instanceof mysqli) {
    try {
        // Fetch upcoming appointments (next 3)
        $appt_sql = "SELECT a.id, a.appointment_date, a.appointment_time, 
                             d.name as doctor_name, s.name as specialty, a.status
                      FROM appointments a
                      JOIN doctors doc ON a.doctor_id = doc.id
                      JOIN users d ON doc.user_id = d.id
                      JOIN specialties s ON doc.specialty_id = s.id
                      WHERE a.patient_id = ? AND a.appointment_date >= CURDATE()
                      ORDER BY a.appointment_date ASC, a.appointment_time ASC
                      LIMIT 3";

        $stmt = $mysqli->prepare($appt_sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $upcoming_appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Fetch recent orders (last 3)
        $order_sql = "SELECT id, total_amount, status, created_at 
                      FROM orders 
                      WHERE user_id = ? 
                      ORDER BY created_at DESC 
                      LIMIT 3";

        $stmt = $mysqli->prepare($order_sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Fetch unread consultation notes count
        $unread_sql = "SELECT COUNT(*) as unread_count 
                       FROM appointments 
                       WHERE patient_id = ? AND notify_patient = 1 
                       AND consultation_notes IS NOT NULL";

        $stmt = $mysqli->prepare($unread_sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $unread_count = $stmt->get_result()->fetch_assoc()['unread_count'] ?? 0;
        $stmt->close();

        // Fetch recent consultation notes (last 3)
        $consult_sql = "SELECT a.id, a.appointment_date, a.appointment_time, 
                               a.consultation_notes, a.notify_patient,
                               d.name as doctor_name, s.name as specialty
                        FROM appointments a
                        JOIN doctors doc ON a.doctor_id = doc.id
                        JOIN users d ON doc.user_id = d.id
                        JOIN specialties s ON doc.specialty_id = s.id
                        WHERE a.patient_id = ? AND a.consultation_notes IS NOT NULL
                        ORDER BY a.appointment_date DESC
                        LIMIT 3";

        $stmt = $mysqli->prepare($consult_sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $recent_consultations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Mark messages as read when fetched
        if (!empty($recent_consultations)) {
            $mysqli->query("UPDATE appointments SET notify_patient = 0, last_notified = NOW() 
                            WHERE patient_id = $user_id AND notify_patient = 1");
        }

    } catch (mysqli_sql_exception $e) {
        error_log("Database Error: " . $e->getMessage());
        $db_error_message = "Could not load dashboard data. Please try again later.";
    }
} else {
    $db_error_message = "Database connection error. Please contact support.";
}
?>

<!-- 4. HTML Dashboard Interface -->
<!-- ---------------------------- -->
<div class="container py-4">
    <!-- Welcome Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="display-5">Welcome, <?= htmlspecialchars($user_name) ?>!</h1>
            <p class="lead">Your personal health dashboard</p>

            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?= $_SESSION['flash_message']['type'] ?> alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['flash_message']['text']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_message']); ?>
            <?php endif; ?>

            <?php if ($db_error_message): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($db_error_message) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Dashboard Content -->
    <div class="row g-4">
        <!-- Left Column - Notifications & Quick Actions -->
        <div class="col-lg-4">
            <!-- Notification Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-bell me-2"></i> Notifications</h5>
                    <?php if ($unread_count > 0): ?>
                        <span class="badge bg-danger rounded-pill"><?= $unread_count ?> new</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($unread_count > 0): ?>
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-envelope me-2"></i>
                            You have <?= $unread_count ?> new message(s) from your doctor(s).
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-3">No new notifications</p>
                    <?php endif; ?>

                    <div class="d-grid gap-2">
                        <a href="my_consultations.php" class="btn btn-outline-primary">
                            <i class="fas fa-comments me-2"></i> View All Messages
                        </a>
                        <a href="appointment.php" class="btn btn-outline-success">
                            <i class="fas fa-calendar-plus me-2"></i> Book Appointment
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quick Health Summary -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-heartbeat me-2"></i> Health Summary</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_consultations)): ?>
                        <div class="list-group">
                            <?php foreach ($recent_consultations as $consult): ?>
                                <a href="consultation_details.php?id=<?= $consult['id'] ?>"
                                    class="list-group-item list-group-item-action">
                                    <div class="d-flex justify-content-between">
                                        <strong>Dr. <?= htmlspecialchars($consult['doctor_name']) ?></strong>
                                        <small><?= date('M j', strtotime($consult['appointment_date'])) ?></small>
                                    </div>
                                    <small class="text-muted"><?= htmlspecialchars($consult['specialty']) ?></small>
                                    <div class="text-truncate mt-1">
                                        <?= htmlspecialchars(substr($consult['consultation_notes'], 0, 60)) ?>...
                                    </div>
                                    <?php if ($consult['notify_patient']): ?>
                                        <span class="badge bg-danger float-end mt-1">New</span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No recent health updates</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-link me-2"></i> Quick Links</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="epharmacy.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-pills me-2 text-primary"></i> E-Pharmacy
                    </a>
                    <a href="medical_records.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-file-medical me-2 text-success"></i> Medical Records
                    </a>
                    <a href="profile.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-user-cog me-2 text-info"></i> Profile Settings
                    </a>
                    <a href="billing.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-receipt me-2 text-warning"></i> Billing
                    </a>
                </div>
            </div>
        </div>

        <!-- Right Column - Main Content -->
        <div class="col-lg-8">
            <!-- Upcoming Appointments -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i> Upcoming Appointments</h5>
                    <a href="appointment.php" class="btn btn-sm btn-light">
                        <i class="fas fa-plus"></i> New
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($upcoming_appointments)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Doctor</th>
                                        <th>Specialty</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcoming_appointments as $appt): ?>
                                        <tr>
                                            <td>
                                                <?= date('M j', strtotime($appt['appointment_date'])) ?><br>
                                                <small
                                                    class="text-muted"><?= date('g:i A', strtotime($appt['appointment_time'])) ?></small>
                                            </td>
                                            <td>Dr. <?= htmlspecialchars($appt['doctor_name']) ?></td>
                                            <td><?= htmlspecialchars($appt['specialty']) ?></td>
                                            <td>
                                                <span class="badge bg-<?=
                                                    $appt['status'] === 'confirmed' ? 'success' :
                                                    ($appt['status'] === 'pending' ? 'warning' : 'secondary')
                                                    ?>">
                                                    <?= ucfirst($appt['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="appointment_details.php?id=<?= $appt['id'] ?>"
                                                    class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="my_appointments.php" class="btn btn-success">
                                View All Appointments
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h5>No Upcoming Appointments</h5>
                            <p class="text-muted">You don't have any scheduled appointments</p>
                            <a href="appointment.php" class="btn btn-success">
                                <i class="fas fa-plus me-2"></i> Book Appointment
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Doctor Communications -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-comment-medical me-2"></i> Recent Doctor Communications</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_consultations)): ?>
                        <div class="accordion" id="communicationsAccordion">
                            <?php foreach ($recent_consultations as $index => $consult): ?>
                                <div class="accordion-item border-0 mb-2">
                                    <h2 class="accordion-header" id="heading<?= $index ?>">
                                        <button class="accordion-button collapsed rounded shadow-sm" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#collapse<?= $index ?>"
                                            aria-expanded="false">
                                            <div class="d-flex w-100 align-items-center">
                                                <div class="flex-grow-1">
                                                    <strong>Dr. <?= htmlspecialchars($consult['doctor_name']) ?></strong>
                                                    <small class="d-block text-muted">
                                                        <?= date('M j, Y', strtotime($consult['appointment_date'])) ?>
                                                    </small>
                                                </div>
                                                <?php if ($consult['notify_patient']): ?>
                                                    <span class="badge bg-danger ms-2">New</span>
                                                <?php endif; ?>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="collapse<?= $index ?>" class="accordion-collapse collapse"
                                        aria-labelledby="heading<?= $index ?>">
                                        <div class="accordion-body pt-3">
                                            <div class="d-flex justify-content-between mb-3">
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?= date('g:i A', strtotime($consult['appointment_time'])) ?>
                                                </small>
                                                <small class="text-muted">
                                                    <i class="fas fa-stethoscope me-1"></i>
                                                    <?= htmlspecialchars($consult['specialty']) ?>
                                                </small>
                                            </div>

                                            <div class="consultation-notes bg-light p-3 rounded mb-3">
                                                <?= nl2br(htmlspecialchars($consult['consultation_notes'])) ?>
                                            </div>

                                            <div class="d-flex justify-content-between">
                                                <a href="message_doctor.php?appointment_id=<?= $consult['id'] ?>"
                                                    class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-reply me-1"></i> Reply
                                                </a>
                                                <button class="btn btn-sm btn-outline-secondary print-notes"
                                                    data-notes="<?= htmlspecialchars($consult['consultation_notes']) ?>"
                                                    data-doctor="Dr. <?= htmlspecialchars($consult['doctor_name']) ?>"
                                                    data-date="<?= date('M j, Y', strtotime($consult['appointment_date'])) ?>">
                                                    <i class="fas fa-print me-1"></i> Print
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-4">
                            <a href="my_consultations.php" class="btn btn-warning">
                                <i class="fas fa-comments me-1"></i> View All Communications
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                            <h5>No Recent Communications</h5>
                            <p class="text-muted">You don't have any messages from doctors yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-pills me-2"></i> Recent Pharmacy Orders</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_orders)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td>#<?= $order['id'] ?></td>
                                            <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                                            <td>UGX <?= number_format($order['total_amount'], 2) ?></td>
                                            <td>
                                                <span class="badge bg-<?=
                                                    $order['status'] === 'delivered' ? 'success' :
                                                    ($order['status'] === 'shipped' ? 'info' :
                                                        ($order['status'] === 'processing' ? 'warning' : 'secondary'))
                                                    ?>">
                                                    <?= ucfirst($order['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="order_details.php?id=<?= $order['id'] ?>"
                                                    class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="my_orders.php" class="btn btn-danger">
                                <i class="fas fa-list me-1"></i> View All Orders
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-shopping-basket fa-3x text-muted mb-3"></i>
                            <h5>No Recent Orders</h5>
                            <p class="text-muted">You haven't placed any pharmacy orders recently</p>
                            <a href="epharmacy.php" class="btn btn-danger">
                                <i class="fas fa-shopping-cart me-1"></i> Visit Pharmacy
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print Modal -->
<div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Print Consultation Notes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="printContent">
                <!-- Content will be inserted here by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Interactive Features -->
<script>
    // Print functionality
    document.querySelectorAll('.print-notes').forEach(btn => {
        btn.addEventListener('click', function () {
            const notes = this.getAttribute('data-notes');
            const doctor = this.getAttribute('data-doctor');
            const date = this.getAttribute('data-date');

            const printContent = `
            <div class="container">
                <div class="text-center mb-4">
                    <h3>Consultation Notes</h3>
                    <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="Clinic Logo" style="height: 50px;" class="mb-2">
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <p><strong>Patient:</strong> <?= htmlspecialchars($user_name) ?></p>
                        <p><strong>Date of Birth:</strong> <?= !empty($_SESSION['user']['dob']) ? date('M j, Y', strtotime($_SESSION['user']['dob'])) : 'Not provided' ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Doctor:</strong> ${doctor}</p>
                        <p><strong>Date:</strong> ${date}</p>
                    </div>
                </div>
                
                <hr>
                
                <h5 class="mb-3">Consultation Summary:</h5>
                <div class="border p-3">${notes.replace(/\n/g, '<br>')}</div>
                
                <div class="mt-4 text-muted small">
                    <p>This document was generated on ${new Date().toLocaleDateString()}.</p>
                </div>
            </div>
        `;

            document.getElementById('printContent').innerHTML = printContent;
            const printModal = new bootstrap.Modal(document.getElementById('printModal'));
            printModal.show();
        });
    });

    // Mark messages as read when viewed
    document.querySelectorAll('.accordion-button').forEach(btn => {
        btn.addEventListener('click', function () {
            const isNew = this.querySelector('.badge');
            if (isNew) {
                const appointmentId = this.closest('.accordion-item').id.replace('heading', '');

                fetch('mark_as_read.php?id=' + appointmentId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            isNew.remove();

                            // Update notification count
                            const notificationBadge = document.querySelector('.card-header .badge');
                            if (notificationBadge) {
                                const currentCount = parseInt(notificationBadge.textContent);
                                if (currentCount > 1) {
                                    notificationBadge.textContent = currentCount - 1;
                                } else {
                                    notificationBadge.remove();

                                    // Update the notification message
                                    const notificationAlert = document.querySelector('.alert-info');
                                    if (notificationAlert) {
                                        notificationAlert.innerHTML = `
                                        <i class="fas fa-check-circle me-2"></i>
                                        All messages have been viewed
                                    `;
                                    }
                                }
                            }
                        }
                    });
            }
        });
    });
</script>

<?php
// 5. Include Footer
// -----------------
require_once __DIR__ . '/footer.php';
?>