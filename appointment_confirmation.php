<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Verify valid appointment ID and user session
if (!isset($_GET['id']) || !isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$appointment_id = (int) $_GET['id'];
$user_id = $_SESSION['user']['id'];

// Get appointment details with doctor info using MySQLi
$stmt = $conn->prepare("
    SELECT a.*, 
           u.name AS doctor_name, 
           s.name AS specialty,
           u.email AS doctor_email,
           p.name AS patient_name,
           p.email AS patient_email
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    JOIN specialties s ON d.specialty_id = s.id
    JOIN users p ON a.patient_id = p.id
    WHERE a.id = ? AND a.patient_id = ?
");
$stmt->bind_param("ii", $appointment_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();

// Verify appointment exists and belongs to this user
if (!$appointment) {
    header("Location: index.php");
    exit();
}

// Email confirmation function
function sendAppointmentConfirmation($appointment)
{
    $to = $appointment['patient_email'];
    $subject = "Appointment Confirmation #AP-" . str_pad($appointment['id'], 6, '0', STR_PAD_LEFT);

    $message = "
        <html>
        <head>
            <title>Appointment Confirmation</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .header { background-color: #f8f9fa; padding: 20px; text-align: center; }
                .details { margin: 20px 0; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px; }
                .footer { margin-top: 20px; font-size: 0.9em; color: #6c757d; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>Your Appointment is Confirmed</h2>
            </div>
            
            <div class='details'>
                <p><strong>Doctor:</strong> Dr. {$appointment['doctor_name']} ({$appointment['specialty']})</p>
                <p><strong>Date:</strong> " . date('l, F j, Y', strtotime($appointment['appointment_date'])) . "</p>
                <p><strong>Time:</strong> " . date('g:i A', strtotime($appointment['appointment_time'])) . "</p>
                <p><strong>Reference #:</strong> AP-" . str_pad($appointment['id'], 6, '0', STR_PAD_LEFT) . "</p>
                <p><strong>Consultation Fee:</strong> UGX " . number_format($appointment['consultation_fee'], 0) . "</p>
            </div>
            
            <p>You'll receive a reminder 24 hours before your appointment.</p>
            
            <div class='footer'>
                <p>Thank you for choosing our services.</p>
            </div>
        </body>
        </html>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Medicare <no-reply@medicare.com>" . "\r\n";

    @mail($to, $subject, $message, $headers);
}

// Send confirmation email
sendAppointmentConfirmation($appointment);

$page_title = "Appointment Confirmed - Medicare";
include 'header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-success">
                <div class="card-header bg-success text-white">
                    <h3 class="mb-0 text-center"><i class="fas fa-check-circle me-2"></i>Appointment Confirmed</h3>
                </div>
                <div class="card-body">
                    <!-- Appointment Summary Card -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="text-primary">Appointment Details</h5>
                                    <p><strong>Reference #:</strong>
                                        AP-<?= str_pad($appointment['id'], 6, '0', STR_PAD_LEFT) ?></p>
                                    <p><strong>Date:</strong>
                                        <?= date('l, F j, Y', strtotime($appointment['appointment_date'])) ?></p>
                                    <p><strong>Time:</strong>
                                        <?= date('g:i A', strtotime($appointment['appointment_time'])) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h5 class="text-primary">Doctor Information</h5>
                                    <p><strong>Name:</strong> Dr. <?= htmlspecialchars($appointment['doctor_name']) ?>
                                    </p>
                                    <p><strong>Specialty:</strong> <?= htmlspecialchars($appointment['specialty']) ?>
                                    </p>
                                    <p><strong>Fee:</strong> UGX
                                        <?= number_format($appointment['consultation_fee'], 0) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Next Steps -->
                    <div class="alert alert-info">
                        <h5><i class="fas fa-info-circle me-2"></i>What happens next?</h5>
                        <ol class="mb-0">
                            <li>We've sent a confirmation to your email</li>
                            <li>Doctor will review your appointment</li>
                            <li>You'll receive a reminder 24 hours before</li>
                        </ol>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-4">
                        <a href="patient_dashboard.php" class="btn btn-outline-primary btn-lg me-md-3">
                            <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                        </a>
                        <a href="#" class="btn btn-success btn-lg" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print Confirmation
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>