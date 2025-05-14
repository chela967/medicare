<?php
// Start output buffering at the VERY TOP
ob_start();

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Helper: Return JSON error if request is AJAX
function respondUnauthorizedIfAjax()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized. Please log in as a patient.']);
        ob_end_flush();
        exit;
    }
}

// Authentication check
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'patient') {
    respondUnauthorizedIfAjax();
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Please log in as a patient'];
    header("Location: ../auth.php");
    exit;
}

// Handle AJAX POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    header('Content-Type: application/json');

    try {
        $patient_id = $_SESSION['user']['id'];
        $patient_name = $_SESSION['user']['name'];
        $appointment_id = (int) ($_POST['appointment_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');

        if (empty($message)) {
            throw new Exception('Message cannot be empty');
        }

        // Verify appointment belongs to patient
        $sql = "SELECT a.*, d.user_id as doctor_user_id 
                FROM appointments a
                JOIN doctors d ON a.doctor_id = d.id
                WHERE a.id = ? AND a.patient_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $appointment_id, $patient_id);
        $stmt->execute();
        $appointment = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$appointment) {
            throw new Exception('Invalid appointment');
        }

        // Insert message and notification
        $conn->begin_transaction();

        $sql = "INSERT INTO messages (appointment_id, sender_id, recipient_id, message, created_at)
                VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiis", $appointment_id, $patient_id, $appointment['doctor_user_id'], $message);
        $stmt->execute();
        $stmt->close();

        $title = "New Message from Patient";
        $content = "Message from $patient_name regarding appointment #$appointment_id";
        $sql = "INSERT INTO notifications (user_id, title, message, type, created_at)
                VALUES (?, ?, ?, 'message', NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $appointment['doctor_user_id'], $title, $content);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if ($conn && $conn->ping())
            $conn->rollback();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

    ob_end_flush();
    exit;
}

// Non-AJAX load
$patient_id = $_SESSION['user']['id'];
$patient_name = $_SESSION['user']['name'];

if (!isset($_GET['appointment_id']) || !is_numeric($_GET['appointment_id'])) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Invalid appointment'];
    header("Location: my_consultations.php");
    exit;
}
$appointment_id = (int) $_GET['appointment_id'];

// Fetch appointment and messages
$appointment = null;
$messages = [];
try {
    $sql = "SELECT a.*, u.name as doctor_name, d.user_id as doctor_user_id
            FROM appointments a
            JOIN doctors d ON a.doctor_id = d.id
            JOIN users u ON d.user_id = u.id
            WHERE a.id = ? AND a.patient_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $appointment_id, $patient_id);
    $stmt->execute();
    $appointment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($appointment) {
        $sql = "SELECT m.*, u.name as sender_name, u.role as sender_role
                FROM messages m
                JOIN users u ON m.sender_id = u.id
                WHERE m.appointment_id = ?
                ORDER BY m.created_at ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (mysqli_sql_exception $e) {
    error_log("Database error: " . $e->getMessage());
    $page_error = "Database error occurred";
}

ob_end_clean();
$page_title = "Message Doctor";
require_once __DIR__ . '/header.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - Medicare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .message-container {
            height: 60vh;
            overflow-y: auto;
        }

        .message-bubble {
            max-width: 75%;
            border-radius: 18px;
        }

        .patient-message {
            background-color: #0d6efd;
            margin-left: auto;
        }

        .doctor-message {
            background-color: #e9ecef;
            margin-right: auto;
        }
    </style>
</head>

<body>
    <div class="container py-4">
        <?php if (isset($page_error)): ?>
            <div class="alert alert-danger"><?= $page_error ?></div>
        <?php elseif ($appointment): ?>
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5><i class="fas fa-comments me-2"></i>Message Dr. <?= htmlspecialchars($appointment['doctor_name']) ?>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="message-container p-3" id="messageContainer">
                        <?php if (empty($messages)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-comment-slash fa-3x mb-3"></i>
                                <p>No messages yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                                <div
                                    class="mb-3 d-flex <?= $msg['sender_id'] == $patient_id ? 'justify-content-end' : 'justify-content-start' ?>">
                                    <div
                                        class="message-bubble p-3 <?= $msg['sender_id'] == $patient_id ? 'patient-message text-white' : 'doctor-message' ?>">
                                        <div><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                                        <div class="small text-muted mt-1">
                                            <?= htmlspecialchars($msg['sender_name']) ?> -
                                            <?= date('M j, g:i A', strtotime($msg['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer">
                    <form id="messageForm" method="POST">
                        <input type="hidden" name="send_message" value="1">
                        <input type="hidden" name="appointment_id" value="<?= $appointment_id ?>">
                        <div id="formError" class="alert alert-danger d-none mb-3"></div>
                        <div class="input-group">
                            <textarea class="form-control" name="message" rows="2" required></textarea>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Send
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">Appointment not found</div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('messageForm');
            const container = document.getElementById('messageContainer');

            container.scrollTop = container.scrollHeight;

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const button = form.querySelector('button[type="submit"]');
                const originalText = button.innerHTML;
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

                fetch('', {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            throw new Error(data.error || 'Failed to send message');
                        }
                    })
                    .catch(error => {
                        const errorDiv = document.getElementById('formError');
                        errorDiv.textContent = error.message;
                        errorDiv.classList.remove('d-none');
                        container.scrollTop = container.scrollHeight;
                    })
                    .finally(() => {
                        button.disabled = false;
                        button.innerHTML = originalText;
                    });
            });
        });
    </script>
</body>

</html>
<?php require_once __DIR__ . '/footer.php'; ?>