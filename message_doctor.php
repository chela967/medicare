<?php
// Database Configuration
// ========================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'medicare');

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start session and check authentication
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'patient') {
    header("Location: ../auth.php");
    exit;
}

// Validate appointment ID
if (!isset($_GET['appointment_id']) || !is_numeric($_GET['appointment_id'])) {
    header("Location: my_consultations.php");
    exit;
}

$appointment_id = (int) $_GET['appointment_id'];
$patient_id = $_SESSION['user']['id'];

// Function to execute prepared queries safely
function executeQuery($conn, $sql, $params = [], $types = '')
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Database error: " . $conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        die("Query failed: " . $stmt->error);
    }

    return $stmt;
}

// Verify appointment belongs to patient
$sql = "SELECT a.*, d.name as doctor_name, doc.user_id as doctor_user_id
        FROM appointments a
        JOIN doctors doc ON a.doctor_id = doc.id
        JOIN users d ON doc.user_id = d.id
        WHERE a.id = ? AND a.patient_id = ?";

$stmt = executeQuery($conn, $sql, [$appointment_id, $patient_id], 'ii');
$appointment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$appointment) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Appointment not found'];
    header("Location: my_consultations.php");
    exit;
}

// Fetch previous messages
$messages = [];
$sql = "SELECT m.*, u.name as sender_name, u.role as sender_role
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.appointment_id = ?
        ORDER BY m.created_at ASC";

$stmt = executeQuery($conn, $sql, [$appointment_id], 'i');
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $message_content = trim($_POST['message'] ?? '');

    if (empty($message_content)) {
        $error = "Message cannot be empty";
    } else {
        $conn->begin_transaction();

        try {
            // Insert message
            $insert_sql = "INSERT INTO messages (appointment_id, sender_id, recipient_id, message, created_at)
                           VALUES (?, ?, ?, ?, NOW())";
            $stmt = executeQuery($conn, $insert_sql, [
                $appointment_id,
                $patient_id,
                $appointment['doctor_user_id'],
                $message_content
            ], 'iiis');

            // Create notification
            $notification_sql = "INSERT INTO notifications (user_id, title, message, type, created_at)
                                VALUES (?, 'New Message', CONCAT('Message from ', ?), 'message', NOW())";
            $stmt = executeQuery($conn, $notification_sql, [
                $appointment['doctor_user_id'],
                $_SESSION['user']['name']
            ], 'is');

            $conn->commit();
            $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Message sent successfully'];
            header("Refresh:0");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to send message: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message Doctor - Medicare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .message-container {
            height: 60vh;
            overflow-y: auto;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }

        .message-bubble {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 18px;
            margin-bottom: 10px;
            position: relative;
        }

        .patient-message {
            background-color: #0d6efd;
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 0;
        }

        .doctor-message {
            background-color: #e9ecef;
            margin-right: auto;
            border-bottom-left-radius: 0;
        }

        .message-time {
            font-size: 0.75rem;
            opacity: 0.8;
        }

        #messageForm textarea {
            resize: none;
        }
    </style>
</head>

<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-comments me-2"></i>Message Dr. <?= htmlspecialchars($appointment['doctor_name']) ?>
            </h1>
            <a href="consultation_details.php?id=<?= $appointment_id ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>

        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-<?= $_SESSION['flash_message']['type'] ?> alert-dismissible fade show">
                <?= $_SESSION['flash_message']['text'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['flash_message']); ?>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Conversation</span>
                            <span class="badge bg-light text-dark">
                                <?= date('M j, Y', strtotime($appointment['appointment_date'])) ?> at
                                <?= date('g:i A', strtotime($appointment['appointment_time'])) ?>
                            </span>
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <div class="message-container" id="messageContainer">
                            <?php if (empty($messages)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-comment-slash fa-4x text-muted mb-3"></i>
                                    <h4>No Messages Yet</h4>
                                    <p class="text-muted">Start your conversation with the doctor</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($messages as $message): ?>
                                    <div
                                        class="d-flex <?= $message['sender_role'] === 'patient' ? 'justify-content-end' : 'justify-content-start' ?>">
                                        <div
                                            class="message-bubble <?= $message['sender_role'] === 'patient' ? 'patient-message' : 'doctor-message' ?>">
                                            <div class="d-flex justify-content-between align-items-end mb-1">
                                                <strong><?= htmlspecialchars($message['sender_name']) ?></strong>
                                                <span class="message-time">
                                                    <?= date('g:i A', strtotime($message['created_at'])) ?>
                                                </span>
                                            </div>
                                            <p class="mb-0"><?= nl2br(htmlspecialchars($message['message'])) ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="card-footer">
                            <form method="POST" id="messageForm">
                                <?php if (isset($error)): ?>
                                    <div class="alert alert-danger mb-3"><?= htmlspecialchars($error) ?></div>
                                <?php endif; ?>

                                <div class="input-group">
                                    <textarea class="form-control" name="message" rows="2"
                                        placeholder="Type your message here..." required></textarea>
                                    <button type="submit" name="send_message" class="btn btn-primary" id="sendButton">
                                        <i class="fas fa-paper-plane"></i> Send
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Scroll to bottom of messages
        function scrollToBottom() {
            const container = document.getElementById('messageContainer');
            container.scrollTop = container.scrollHeight;
        }

        // Auto-refresh messages every 15 seconds
        function refreshMessages() {
            fetch(`get_messages.php?appointment_id=<?= $appointment_id ?>`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('messageContainer').innerHTML = html;
                    scrollToBottom();
                });
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function () {
            scrollToBottom();
            setInterval(refreshMessages, 15000);

            // AJAX form submission
            document.getElementById('messageForm').addEventListener('submit', function (e) {
                e.preventDefault();

                const form = this;
                const formData = new FormData(form);
                const sendButton = document.getElementById('sendButton');

                // Disable button during submission
                sendButton.disabled = true;
                sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => {
                        if (response.ok) {
                            form.reset();
                            refreshMessages();
                        } else {
                            alert('Failed to send message');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred');
                    })
                    .finally(() => {
                        sendButton.disabled = false;
                        sendButton.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
                    });
            });
        });
    </script>
</body>

</html>