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

    // 1. Get doctor details before approval
    $stmt = $conn->prepare("
        SELECT d.id, u.email, u.name 
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
        SET status = 'approved', 
            approved_at = NOW(), 
            approved_by = ? 
        WHERE id = ?
    ");
    $stmt->bind_param("ii", $_SESSION['user']['id'], $doctor_id);
    $stmt->execute();

    // 3. Log admin action
    logAdminAction($_SESSION['user']['id'], "Approved doctor ID: $doctor_id");

    // 4. Send approval email
    if ($doctor) {
        $to = $doctor['email'];
        $subject = "Your Medicare Doctor Account Approval";
        $message = "Dear Dr. " . htmlspecialchars($doctor['name']) . ",\n\n";
        $message .= "We are pleased to inform you that your Medicare doctor account has been approved!\n\n";
        $message .= "You can now log in to your account at:\n";
        $message .= BASE_URL . "/login.php\n\n";
        $message .= "Best regards,\nThe Medicare Team";

        $headers = "From: no-reply@" . parse_url(BASE_URL, PHP_URL_HOST) . "\r\n";
        @mail($to, $subject, $message, $headers);
    }

    $conn->commit();
    $_SESSION['success_message'] = "Doctor approved successfully";
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_message'] = "Approval failed: " . $e->getMessage();
    error_log("Approval error: " . $e->getMessage());
}

header("Location: " . ADMIN_BASE . "/pending_doctors.php");
exit();