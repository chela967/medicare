<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Admin authentication
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

// Validate CSRF token and ID
if (!isset($_GET['id']) || !isset($_GET['token']) || $_GET['token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = "Invalid request";
    header("Location: " . ADMIN_BASE . "/pending_doctors.php");
    exit();
}

$doctor_id = (int) $_GET['id'];

try {
    $conn->begin_transaction();

    // 1. Get doctor details before rejection
    $stmt = $conn->prepare("
        SELECT d.id, u.email, u.name, d.verification_docs 
        FROM doctors d
        JOIN users u ON d.user_id = u.id
        WHERE d.id = ? AND d.status = 'pending'
    ");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Doctor not found or already processed");
    }

    $doctor = $result->fetch_assoc();

    // 2. Update doctor status
    $stmt = $conn->prepare("
        UPDATE doctors 
        SET status = 'rejected', 
            approved_at = NOW(), 
            approved_by = ? 
        WHERE id = ?
    ");
    $stmt->bind_param("ii", $_SESSION['user']['id'], $doctor_id);
    $stmt->execute();

    // 3. Log admin action
    logAdminAction($_SESSION['user']['id'], "Rejected doctor ID: $doctor_id");

    // 4. Send rejection email
    if ($doctor) {
        $to = $doctor['email'];
        $subject = "Your Medicare Doctor Registration Status";
        $message = "Dear " . htmlspecialchars($doctor['name']) . ",\n\n";
        $message .= "We regret to inform you that your application to join Medicare as a doctor has not been approved.\n\n";
        $message .= "If you believe this was an error, please contact our support team.\n\n";
        $message .= "Best regards,\nThe Medicare Team";

        $headers = "From: no-reply@" . parse_url(BASE_URL, PHP_URL_HOST) . "\r\n";
        @mail($to, $subject, $message, $headers);
    }

    // 5. Clean up uploaded files
    if (!empty($doctor['verification_docs'])) {
        $file_path = __DIR__ . '/../uploads/doctor_docs/' . $doctor['verification_docs'];
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
    }

    $conn->commit();
    $_SESSION['success_message'] = "Doctor rejected successfully";
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_message'] = "Rejection failed: " . $e->getMessage();
    error_log("Rejection error: " . $e->getMessage());
}

header("Location: " . ADMIN_BASE . "/pending_doctors.php");
exit();