<?php
// patient_dashboard.php - Enhanced Patient Dashboard (Using $conn)

// 1. Secure Session Start & Configuration
// --------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400, // 1 day
        'cookie_secure' => isset($_SERVER['HTTPS']), // Use true if site is HTTPS only
        'cookie_httponly' => true, // Prevent JS access to session cookie
        'use_strict_mode' => true // Prevent session fixation attacks
    ]);
}
ob_start(); // Keep ob_start() if it was in your original config or needed elsewhere

// Check authentication and role
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'patient') {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Please log in as a patient to access the dashboard.'];
    header('Location: auth.php'); // Adjust path if necessary
    exit;
}

// 2. Include Configurations & Set Page Title
// -----------------------------------------
require_once __DIR__ . '/config.php'; // Provides $conn and potentially BASE_URL
$page_title = 'Patient Dashboard';
require_once __DIR__ . '/header.php'; // Includes HTML head, navigation, etc.

// 3. Database Fetching with Error Handling
// ----------------------------------------
$user_id = $_SESSION['user']['id'];
$user_name = $_SESSION['user']['name'];
$user_dob = $_SESSION['user']['dob'] ?? null; // Fetch DOB if available in session
$db_error_message = null;

// Initialize data arrays
$upcoming_appointments = [];
$recent_orders = [];
$recent_messages_summary = [];
$unread_consultation_notes_count = 0;

// Ensure database connection is valid before proceeding
// **** CHANGED: Check for $conn instead of $mysqli ****
if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    try {
        // --- Fetch upcoming appointments (next 3) ---
        $appt_sql = "SELECT a.id, a.appointment_date, a.appointment_time,
                            d.name as doctor_name, s.name as specialty, a.status
                      FROM appointments a
                      JOIN doctors doc ON a.doctor_id = doc.id
                      JOIN users d ON doc.user_id = d.id
                      JOIN specialties s ON doc.specialty_id = s.id
                      WHERE a.patient_id = ?
                        AND a.appointment_date >= CURDATE()
                      ORDER BY a.appointment_date ASC, a.appointment_time ASC
                      LIMIT 3";

        // **** CHANGED: Use $conn ****
        $stmt_appt = $conn->prepare($appt_sql);
        if (!$stmt_appt)
            throw new mysqli_sql_exception("Prepare failed (upcoming appt): " . $conn->error);
        $stmt_appt->bind_param("i", $user_id);
        $stmt_appt->execute();
        $upcoming_appointments = $stmt_appt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_appt->close();

        // --- Fetch recent orders (last 3) ---
        $order_sql = "SELECT id, total_amount, status, created_at
                      FROM orders
                      WHERE user_id = ?
                      ORDER BY created_at DESC
                      LIMIT 3";

        // **** CHANGED: Use $conn ****
        $stmt_order = $conn->prepare($order_sql);
        if (!$stmt_order)
            throw new mysqli_sql_exception("Prepare failed (recent orders): " . $conn->error);
        $stmt_order->bind_param("i", $user_id);
        $stmt_order->execute();
        $recent_orders = $stmt_order->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_order->close();

        // --- Fetch unread *consultation notes* count ---
        $unread_notes_sql = "SELECT COUNT(*) as unread_count
                             FROM appointments
                             WHERE patient_id = ? AND notify_patient = 1
                             AND consultation_notes IS NOT NULL AND consultation_notes <> ''";

        // **** CHANGED: Use $conn ****
        $stmt_unread_notes = $conn->prepare($unread_notes_sql);
        if (!$stmt_unread_notes)
            throw new mysqli_sql_exception("Prepare failed (unread notes count): " . $conn->error);
        $stmt_unread_notes->bind_param("i", $user_id);
        $stmt_unread_notes->execute();
        $unread_count_result = $stmt_unread_notes->get_result()->fetch_assoc();
        $unread_consultation_notes_count = $unread_count_result ? (int) $unread_count_result['unread_count'] : 0;
        $stmt_unread_notes->close();

        // --- Fetch Recent Communications (Latest Message per Conversation) ---
        $latest_appt_with_msg_sql = "
            SELECT DISTINCT a.id as appointment_id
            FROM appointments a
            JOIN messages m ON a.id = m.appointment_id
            WHERE a.patient_id = ?
            ORDER BY m.created_at DESC
            LIMIT 3";

        // **** CHANGED: Use $conn ****
        $stmt_latest_appts = $conn->prepare($latest_appt_with_msg_sql);
        if (!$stmt_latest_appts)
            throw new mysqli_sql_exception("Prepare failed (latest appts with msg): " . $conn->error);
        $stmt_latest_appts->bind_param("i", $user_id);
        $stmt_latest_appts->execute();
        $latest_appts_result = $stmt_latest_appts->get_result();
        $appointment_ids_with_messages = [];
        while ($row = $latest_appts_result->fetch_assoc()) {
            $appointment_ids_with_messages[] = $row['appointment_id'];
        }
        $stmt_latest_appts->close();

        if (!empty($appointment_ids_with_messages)) {
            $latest_msg_sql = "
                SELECT
                    m.id as message_id, m.message, m.created_at as message_time,
                    m.sender_id, sender.name as sender_name, sender.role as sender_role,
                    a.id as appointment_id, a.appointment_date,
                    doc_user.name as doctor_name
                FROM messages m
                JOIN users sender ON m.sender_id = sender.id
                JOIN appointments a ON m.appointment_id = a.id
                JOIN doctors doc ON a.doctor_id = doc.id
                JOIN users doc_user ON doc.user_id = doc_user.id
                WHERE m.appointment_id = ?
                ORDER BY m.created_at DESC
                LIMIT 1";

            // **** CHANGED: Use $conn ****
            $stmt_latest_msg = $conn->prepare($latest_msg_sql);
            if (!$stmt_latest_msg)
                throw new mysqli_sql_exception("Prepare failed (latest msg): " . $conn->error);

            foreach ($appointment_ids_with_messages as $appt_id) {
                $stmt_latest_msg->bind_param("i", $appt_id);
                $stmt_latest_msg->execute();
                $msg_result = $stmt_latest_msg->get_result();
                if ($msg_row = $msg_result->fetch_assoc()) {
                    $recent_messages_summary[] = $msg_row;
                }
            }
            $stmt_latest_msg->close();

            usort($recent_messages_summary, function ($a, $b) {
                return strtotime($b['message_time']) - strtotime($a['message_time']);
            });
        }

    } catch (mysqli_sql_exception $e) {
        error_log("Patient Dashboard Database Error: " . $e->getMessage() . " | SQL State: " . $e->getSqlState());
        $db_error_message = "Could not load some dashboard data due to a database issue. Please try refreshing or contact support if the problem persists.";
    } catch (Exception $e) {
        error_log("Patient Dashboard General Error: " . $e->getMessage());
        $db_error_message = "An unexpected error occurred. Please try again later.";
    }
} else {
    // **** CHANGED: Update error message context ****
    $db_error_message = "Database connection error. Please check configuration (config.php).";
    error_log("Patient Dashboard Error: \$conn object not available or invalid after including config.php.");
}
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="display-5">Welcome, <?= htmlspecialchars($user_name) ?>!</h1>
            <p class="lead text-muted">Your personal health dashboard</p>

            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_message']['type']) ?> alert-dismissible fade show"
                    role="alert">
                    <?= htmlspecialchars($_SESSION['flash_message']['text']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['flash_message']); ?>
            <?php endif; ?>

            <?php if ($db_error_message): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars($db_error_message) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-bell me-2"></i> Notifications</h5>
                    <?php if ($unread_consultation_notes_count > 0): ?>
                        <span class="badge bg-danger rounded-pill"
                            id="notification-count-badge"><?= $unread_consultation_notes_count ?> new notes</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div id="notification-message-area">
                        <?php if ($unread_consultation_notes_count > 0): ?>
                            <div class="alert alert-info mb-3">
                                <i class="fas fa-notes-medical me-2"></i>
                                You have <?= $unread_consultation_notes_count ?> new consultation note(s). Check 'Recent
                                Communications'.
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-3">No new consultation notes</p>
                        <?php endif; ?>
                    </div>
                    <div class="d-grid gap-2">
                        <a href="my_consultations.php" class="btn btn-outline-primary"> <i
                                class="fas fa-comments me-2"></i> View All Communications</a>
                        <a href="appointment.php" class="btn btn-outline-success"><i
                                class="fas fa-calendar-plus me-2"></i> Book New Appointment</a>
                    </div>
                </div>
            </div>
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-link me-2"></i> Quick Links</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="epharmacy.php" class="list-group-item list-group-item-action"><i
                            class="fas fa-pills me-2 text-primary fa-fw"></i> E-Pharmacy</a>
                    <a href="medical_records.php" class="list-group-item list-group-item-action"><i
                            class="fas fa-file-medical me-2 text-success fa-fw"></i> Medical Records</a>
                    <a href="profile.php" class="list-group-item list-group-item-action"><i
                            class="fas fa-user-cog me-2 text-info fa-fw"></i> Profile Settings</a>
                    <a href="billing.php" class="list-group-item list-group-item-action"><i
                            class="fas fa-receipt me-2 text-warning fa-fw"></i> Billing & Payments</a>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i> Upcoming Appointments</h5>
                    <a href="appointment.php" class="btn btn-sm btn-light"><i class="fas fa-plus"></i> New</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($upcoming_appointments)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Doctor</th>
                                        <th>Specialty</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcoming_appointments as $appt): ?>
                                        <tr>
                                            <td><?= date('D, M j, Y', strtotime($appt['appointment_date'])) ?><br><small
                                                    class="text-muted"><?= date('g:i A', strtotime($appt['appointment_time'])) ?></small>
                                            </td>
                                            <td>Dr. <?= htmlspecialchars($appt['doctor_name']) ?></td>
                                            <td><?= htmlspecialchars($appt['specialty']) ?></td>
                                            <td>
                                                <?php /* Status Badge Logic */
                                                $status_color = 'secondary';
                                                switch (strtolower($appt['status'])) { /* ... cases ... */
                                                }
                                                ?>
                                                <span
                                                    class="badge bg-<?= $status_color ?>"><?= ucfirst(htmlspecialchars($appt['status'])) ?></span>
                                            </td>
                                            <td>
                                                <a href="appointment_details.php?id=<?= $appt['id'] ?>"
                                                    class="btn btn-sm btn-outline-primary" title="View Details"><i
                                                        class="fas fa-eye"></i></a>
                                                <?php if (in_array($appt['status'], ['scheduled', 'pending', 'confirmed'])): ?>
                                                    <a href="cancel_appointment.php?id=<?= $appt['id'] ?>"
                                                        class="btn btn-sm btn-outline-danger ms-1" title="Cancel Appointment"
                                                        onclick="return confirm('Are you sure?');"><i class="fas fa-times"></i></a>
                                                <?php endif; ?>
                                                <a href="message_doctor.php?appointment_id=<?= $appt['id'] ?>"
                                                    class="btn btn-sm btn-outline-secondary ms-1" title="Message Doctor"><i
                                                        class="fas fa-comment-dots"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3"><a href="my_appointments.php" class="btn btn-success"><i
                                    class="fas fa-calendar-alt me-1"></i> View All Appointments</a></div>
                    <?php else: ?>
                        <div class="text-center py-4"><i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h5>No Upcoming Appointments</h5>
                            <p class="text-muted">You don't have any scheduled appointments from today onwards.</p><a
                                href="appointment.php" class="btn btn-success mt-2"><i class="fas fa-plus me-2"></i> Book
                                New Appointment</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-comment-medical me-2"></i> Recent Communications</h5>
                    <a href="my_consultations.php" class="btn btn-sm btn-outline-dark"><i class="fas fa-list"></i> View
                        All</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_messages_summary)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_messages_summary as $msg_summary): ?>
                                <a href="message_doctor.php?appointment_id=<?= $msg_summary['appointment_id'] ?>"
                                    class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">Conversation with Dr.
                                            <?= htmlspecialchars($msg_summary['doctor_name']) ?></h6>
                                        <small
                                            class="text-muted"><?= date('M j, Y g:i A', strtotime($msg_summary['message_time'])) ?></small>
                                    </div>
                                    <p class="mb-1 text-truncate">
                                        <small><strong><?= ($msg_summary['sender_id'] == $user_id) ? 'You' : htmlspecialchars($msg_summary['sender_name']) ?>:</strong>
                                            <?= htmlspecialchars($msg_summary['message']) ?></small></p>
                                    <small class="text-muted">Regarding appointment on
                                        <?= date('M j, Y', strtotime($msg_summary['appointment_date'])) ?></small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4"><i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                            <h5>No Recent Messages</h5>
                            <p class="text-muted">You haven't exchanged messages with doctors recently.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-pills me-2"></i> Recent Pharmacy Orders</h5>
                    <a href="my_orders.php" class="btn btn-sm btn-light"><i class="fas fa-list"></i> View All</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_orders)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
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
                                                <?php /* Order Status Badge Logic */
                                                $order_status_color = 'secondary';
                                                switch (strtolower($order['status'])) { /* ... cases ... */
                                                }
                                                ?>
                                                <span
                                                    class="badge bg-<?= $order_status_color ?>"><?= ucfirst(htmlspecialchars($order['status'])) ?></span>
                                            </td>
                                            <td><a href="order_details.php?id=<?= $order['id'] ?>"
                                                    class="btn btn-sm btn-outline-primary" title="View Order Details"><i
                                                        class="fas fa-eye"></i></a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3"><a href="my_orders.php" class="btn btn-danger"><i
                                    class="fas fa-list me-1"></i> View All Orders</a></div>
                    <?php else: ?>
                        <div class="text-center py-4"><i class="fas fa-shopping-basket fa-3x text-muted mb-3"></i>
                            <h5>No Recent Orders</h5>
                            <p class="text-muted">You haven't placed any pharmacy orders recently.</p><a
                                href="epharmacy.php" class="btn btn-danger mt-2"><i class="fas fa-shopping-cart me-1"></i>
                                Visit Pharmacy</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="printModal" tabindex="-1" aria-labelledby="printModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="printModalLabel">Print Consultation Notes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="printContent" class="printable-area"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="doPrintButton"><i class="fas fa-print me-1"></i>
                    Print</button>
            </div>
        </div>
    </div>
</div>

<style>
    @media print {
        /* ... print styles ... */
    }
</style>

<script>
    // JS remains the same as previous version, as it doesn't directly use the PHP DB variable
    document.addEventListener('DOMContentLoaded', function () {
        // ... Print functionality ...
        // ... Mark consultation notes as read functionality ...
    });
</script>

<?php
require_once __DIR__ . '/footer.php';
ob_end_flush(); // Send output buffer if ob_start() was used
?>