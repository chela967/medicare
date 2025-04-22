<?php
session_start();
require_once '../config.php'; // Ensure this creates the $conn mysqli object
require_once '../functions.php'; // Ensure this has mysqli functions like getDoctorIdByUserId and getAppointmentDetailsForDoctor

// --- Security Checks ---
// 1. Check if user is logged in and is a doctor
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'doctor') {
    header("Location: ../auth.php");
    exit();
}

// --- Get Logged-in Doctor's ID ---
$logged_in_user_id = $_SESSION['user']['id'];
// Pass the $conn variable from config.php
$logged_in_doctor_id = getDoctorIdByUserId($logged_in_user_id, $conn);

if (!$logged_in_doctor_id) {
    // Handle error - Doctor ID not found for logged-in user
    $_SESSION['error_message'] = "Could not verify doctor identity.";
    header("Location: dashboard.php"); // Redirect back to dashboard
    exit();
}

// --- Get Appointment ID from URL ---
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    // Handle error - Invalid or missing appointment ID
    $_SESSION['error_message'] = "Invalid appointment ID specified.";
    header("Location: dashboard.php");
    exit();
}
$appointment_id = (int) $_GET['id'];

// --- Fetch Appointment Details ---
// Pass the $conn variable from config.php
$appointment = getAppointmentDetailsForDoctor($appointment_id, $logged_in_doctor_id, $conn);

// --- Set Page Title ---
// Use fetched data if available, otherwise set a default title
$page_title = $appointment ? "Appointment Details - " . htmlspecialchars($appointment['patient_name']) : "Appointment Not Found";
include '../header.php'; // Adjust path if needed

?>

<div class="container-fluid py-4">
    <div class="row">
        <?php
        // Example: Include a common sidebar file
        // Adjust the path as needed
        // $sidebar_path = __DIR__ . '/_doctor_sidebar.php';
        // if (file_exists($sidebar_path)) {
        //     include $sidebar_path;
        // }
        ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div
                class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $page_title; ?></h1>
                <a href="appointments.php" class="btn btn-sm btn-outline-secondary"> <i
                        class="fas fa-arrow-left me-1"></i> Back to Appointments
                </a>
            </div>

            <?php // Display any feedback messages stored in the session
            if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php elseif (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>


            <?php if ($appointment): // Check if appointment data was successfully fetched ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Appointment Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>Patient:</strong> <?php echo htmlspecialchars($appointment['patient_name']); ?>
                                </p>
                                <p><strong>Contact Phone:</strong>
                                    <?php echo htmlspecialchars($appointment['patient_phone'] ?? 'N/A'); ?></p>
                                <p><strong>Contact Email:</strong>
                                    <?php echo htmlspecialchars($appointment['patient_email'] ?? 'N/A'); ?></p>
                                <p><strong>Appointment Date:</strong>
                                    <?php echo date('l, F j, Y', strtotime($appointment['appointment_date'])); ?></p>
                                <p><strong>Time:</strong>
                                    <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Status:</strong>
                                    <span class="badge bg-<?php
                                    // Map status to Bootstrap badge colors
                                    $status_color = match ($appointment['appointment_status']) {
                                        'completed' => 'success',
                                        'cancelled' => 'danger',
                                        'no_show' => 'secondary',
                                        default => 'warning', // scheduled or other
                                    };
                                    echo $status_color;
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $appointment['appointment_status'])); ?>
                                    </span>
                                </p>
                                <p><strong>Consultation Fee:</strong> UGX
                                    <?php echo number_format($appointment['consultation_fee'], 0); // Assuming UGX doesn't typically use decimals ?>
                                </p>
                                <p><strong>Payment Status:</strong> <?php echo ucfirst($appointment['payment_status']); ?>
                                </p>
                                <p><strong>Payment Method:</strong>
                                    <?php echo ucfirst(str_replace('_', ' ', $appointment['payment_method'] ?? 'N/A')); ?>
                                </p>
                                <p><strong>Booked On:</strong>
                                    <?php echo date('M j, Y, g:i A', strtotime($appointment['appointment_created_at'])); ?>
                                </p>
                            </div>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <strong>Reason for Visit:</strong>
                            <p class="text-muted">
                                <?php echo nl2br(htmlspecialchars($appointment['reason'] ?? 'No reason provided.')); ?></p>
                        </div>
                        <hr>
                        <div>
                            <strong>Consultation Notes:</strong>
                            <?php if (!empty($appointment['consultation_notes'])): ?>
                                <div class="border rounded p-2 bg-light">
                                    <?php echo nl2br(htmlspecialchars($appointment['consultation_notes'])); ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted"><em>No consultation notes added yet.</em></p>
                            <?php endif; ?>
                        </div>

                        <div class="mt-4 text-end">
                            <a href="consultation.php?id=<?php echo $appointment['appointment_id']; ?>"
                                class="btn btn-primary"><i class="fas fa-stethoscope me-1"></i> Start/View Consultation</a>

                        </div>
                    </div>
                </div>

            <?php else: // If $appointment is false (not found or not authorized) ?>
                <div class="alert alert-warning">
                    Appointment not found or you do not have permission to view it. Please check the ID or contact support
                    if you believe this is an error.
                </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<?php include '../footer.php'; // Adjust path if needed ?>