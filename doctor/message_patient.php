<?php
session_start();
// Assuming config.php and functions.php are one level up
require_once __DIR__ . '/../config.php'; // Provides $conn, BASE_URL
require_once __DIR__ . '/../functions.php'; // Provides getDoctorIdByUserId

// --- Initialize Variables ---
$page_error = null;
$appointment = null;
$patient_id = null; // User ID of the patient
$patient_name = null;
$messages = [];
$doctor_user_id = $_SESSION['user']['id'] ?? null;
$doctor_name = $_SESSION['user']['name'] ?? null;
$appointment_id = isset($_GET['appointment_id']) && is_numeric($_GET['appointment_id']) ? (int) $_GET['appointment_id'] : null;
$doctor_profile_id = null; // This is the ID from the 'doctors' table

// --- Function to detect AJAX requests ---
function isAjaxRequest()
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// --- Handle AJAX Message Submission ---
// Process POST only if it's an AJAX request with the correct action
if (isAjaxRequest() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {

    header('Content-Type: application/json'); // Set JSON header for AJAX response
    $response = ['success' => false]; // Default response
    ob_start(); // Start output buffering to catch any errors before JSON output

    try {
        // Re-validate session and role inside AJAX handler for security
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'doctor') {
            throw new Exception('Unauthorized access.');
        }
        // Get appointment ID from POST data for AJAX
        $appointment_id_ajax = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
        if (!$appointment_id_ajax)
            throw new Exception('Appointment ID missing in POST request.');

        // Check DB connection
        if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
            throw new Exception('Database connection error.');
        }

        // Get doctor profile ID again within AJAX context
        $current_doctor_user_id = $_SESSION['user']['id'];
        $current_doctor_profile_id = getDoctorIdByUserId($current_doctor_user_id, $conn);
        if (!$current_doctor_profile_id) {
            throw new Exception('Could not verify doctor profile.');
        }

        // Validate message content
        $message_content = trim($_POST['message'] ?? '');
        if (empty($message_content)) {
            throw new Exception('Message cannot be empty.');
        }

        // Verify appointment belongs to this doctor and get patient user ID
        $stmt_verify = $conn->prepare("SELECT a.patient_id FROM appointments a WHERE a.id = ? AND a.doctor_id = ?");
        if (!$stmt_verify)
            throw new Exception("DB Error (verify): " . $conn->error);
        $stmt_verify->bind_param("ii", $appointment_id_ajax, $current_doctor_profile_id);
        $stmt_verify->execute();
        $result_verify = $stmt_verify->get_result();
        $appt_data = $result_verify->fetch_assoc();
        $stmt_verify->close();

        if (!$appt_data) {
            throw new Exception('Appointment not found or access denied.');
        }
        $recipient_patient_id = $appt_data['patient_id']; // This is the users.id of the patient

        // --- Perform Database Operations ---
        $conn->begin_transaction();

        // Insert the message
        $stmt_insert = $conn->prepare("INSERT INTO messages (appointment_id, sender_id, recipient_id, message, created_at) VALUES (?, ?, ?, ?, NOW())");
        if (!$stmt_insert)
            throw new Exception("DB Error (insert msg): " . $conn->error);
        // Sender is doctor (user_id), recipient is patient (user_id)
        $stmt_insert->bind_param("iiis", $appointment_id_ajax, $current_doctor_user_id, $recipient_patient_id, $message_content);
        if (!$stmt_insert->execute())
            throw new Exception("DB Error (exec insert msg): " . $stmt_insert->error);
        $new_message_id = $conn->insert_id; // Get ID of the new message
        $stmt_insert->close();

        // Create notification for the PATIENT
        $notification_title = "New Message from Doctor";
        $notification_message = "You have a new message from Dr. " . htmlspecialchars($doctor_name) . " regarding appointment #" . $appointment_id_ajax;
        $notification_type = 'message';
        $notification_link = null;
        $link_column_exists = false;
        $result_check = $conn->query("SHOW COLUMNS FROM `notifications` LIKE 'link'");
        if ($result_check && $result_check->num_rows > 0) {
            $link_column_exists = true;
            $notification_link = "message_doctor.php?appointment_id=" . $appointment_id_ajax;
        }
        if ($result_check)
            $result_check->free();

        if ($link_column_exists) {
            $sql_notify = "INSERT INTO notifications (user_id, title, message, type, link, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt_notify = $conn->prepare($sql_notify);
            if (!$stmt_notify)
                throw new Exception("DB Error (prep notify w/ link): " . $conn->error);
            $stmt_notify->bind_param("issss", $recipient_patient_id, $notification_title, $notification_message, $notification_type, $notification_link);
        } else {
            $sql_notify = "INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, ?, NOW())";
            $stmt_notify = $conn->prepare($sql_notify);
            if (!$stmt_notify)
                throw new Exception("DB Error (prep notify w/o link): " . $conn->error);
            $stmt_notify->bind_param("isss", $recipient_patient_id, $notification_title, $notification_message, $notification_type);
        }
        if (!$stmt_notify->execute())
            throw new Exception("DB Error (exec notify): " . $stmt_notify->error);
        $stmt_notify->close();

        $conn->commit();
        $response['success'] = true;

        // Fetch the newly inserted message details to return in JSON
        $stmt_get_msg = $conn->prepare("SELECT m.*, u.name as sender_name
                                        FROM messages m JOIN users u ON m.sender_id = u.id
                                        WHERE m.id = ?");
        if ($stmt_get_msg) {
            $stmt_get_msg->bind_param("i", $new_message_id);
            $stmt_get_msg->execute();
            $new_msg_details = $stmt_get_msg->get_result()->fetch_assoc();
            $stmt_get_msg->close();
            if ($new_msg_details) {
                $response['message'] = [
                    'id' => $new_msg_details['id'],
                    'content' => htmlspecialchars($new_msg_details['message']), // Sanitize for JS
                    'sender_name' => htmlspecialchars($new_msg_details['sender_name']),
                    'created_at' => $new_msg_details['created_at'], // Send ISO format
                    'is_doctor' => true // Flag that doctor sent this
                ];
            }
        }

    } catch (Exception $e) {
        // Rollback only if connection seems okay
        if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
            $conn->rollback(); // Attempt rollback
        }
        http_response_code(400); // Indicate error
        $response['error'] = $e->getMessage();
        error_log("Doctor Message Send Error (AJAX): " . $e->getMessage());
    }

    $php_error_output = ob_get_clean(); // Get any stray output
    if (!empty($php_error_output)) {
        error_log("PHP Output detected during Doctor AJAX request: " . $php_error_output);
        // If we already have an error, append; otherwise, set a generic one
        if (!isset($response['error'])) {
            $response['error'] = "Server script produced unexpected output.";
        }
        $response['php_output'] = $php_error_output; // For debugging
        $response['success'] = false; // Ensure success is false
        if (!headers_sent()) {
            header('Content-Type: application/json');
        } // Try setting header again
    }

    echo json_encode($response);
    exit; // Stop script after AJAX response
}

// --- Regular Page Load Logic Below ---

// Authentication check for page load
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'doctor') {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Access denied. Please log in as a doctor.'];
    header("Location: ../auth.php");
    exit();
}
// Validate appointment ID for page load
if (!$appointment_id) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Invalid consultation ID specified.'];
    header("Location: appointments.php");
    exit();
}
// Get doctor profile ID for page load
$doctor_profile_id = getDoctorIdByUserId($doctor_user_id, $conn);
if (!$doctor_profile_id) {
    $page_error = "Could not load your doctor profile.";
    error_log("Doctor Messaging Error: Could not retrieve doctor profile ID for user ID: $doctor_user_id");
}

// Load appointment and messages if no errors so far
if (!$page_error) {
    if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
        $page_error = "Database connection error.";
    } else {
        try {
            // Verify appointment belongs to this doctor & get patient name
            $stmt = $conn->prepare("SELECT a.*, pat_user.id as patient_user_id, pat_user.name as patient_user_name
                                   FROM appointments a
                                   JOIN users pat_user ON a.patient_id = pat_user.id
                                   WHERE a.id = ? AND a.doctor_id = ?");
            if (!$stmt)
                throw new mysqli_sql_exception("Prepare failed (verify appt): " . $conn->error);
            $stmt->bind_param("ii", $appointment_id, $doctor_profile_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $appointment = $result->fetch_assoc();
            $stmt->close();

            if (!$appointment) {
                $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Consultation not found or you do not have access to it.'];
                header("Location: appointments.php");
                exit();
            }
            $patient_id = $appointment['patient_user_id'];
            $patient_name = $appointment['patient_user_name'];

            // Fetch previous messages
            $stmt = $conn->prepare("SELECT m.*, u.name as sender_name, u.role as sender_role
                                    FROM messages m JOIN users u ON m.sender_id = u.id
                                    WHERE m.appointment_id = ? ORDER BY m.created_at ASC");
            if (!$stmt)
                throw new mysqli_sql_exception("Prepare failed (fetch messages): " . $conn->error);
            $stmt->bind_param("i", $appointment_id);
            $stmt->execute();
            $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

        } catch (mysqli_sql_exception $e) {
            $page_error = "Error loading conversation data.";
            error_log("Doctor Messaging Fetch Error: " . $e->getMessage());
            $appointment = null; // Don't render page if data load failed
        }
    }
}

// --- Page Rendering ---
$page_title = "Message Patient";
require_once __DIR__ . '/../header.php'; // Path corrected
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
        /* Styles remain the same */
        .message-container { height: 60vh; overflow-y: auto; background-color: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #dee2e6; }
        .message-bubble { max-width: 75%; padding: 10px 15px; border-radius: 18px; margin-bottom: 10px; position: relative; word-wrap: break-word; }
        .patient-message { background-color: #e9ecef; color: #212529; margin-right: auto; border-bottom-left-radius: 5px; }
        .doctor-message { background-color: #16a085; color: white; margin-left: auto; border-bottom-right-radius: 5px; }
        .message-meta { font-size: 0.75rem; opacity: 0.8; margin-top: 3px; }
        .patient-message .message-meta { text-align: left; color: #6c757d; }
        .doctor-message .message-meta { text-align: right; color: rgba(255,255,255,0.7); }
        #messageForm textarea { resize: none; }
        .loading-spinner { display: none; }
        .sending .loading-spinner { display: inline-block; }
        .sending .send-text { display: none; }
    </style>
</head>
<body>
    <div class="container py-4 my-4">
        <?php if (!empty($page_error)): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars($page_error) ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_message']['type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['flash_message']['text'] ?? 'Notice') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['flash_message']); ?>
        <?php endif; ?>

        <?php if (!empty($appointment)): ?>
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
                    <h1 class="h3 mb-2 mb-md-0"><i class="fas fa-comments me-2"></i>Message Patient: <?= htmlspecialchars($patient_name) ?></h1>
                    <a href="appointments.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back to Appointments</a>
                </div>
                <div class="row justify-content-center">
                    <div class="col-lg-10 col-xl-9">
                        <div class="card shadow-sm">
                            <div class="card-header bg-light text-dark">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Conversation regarding appointment on:</span>
                                    <span class="badge bg-secondary">
                                        <?= date('M j, Y', strtotime($appointment['appointment_date'])) ?> at <?= date('g:i A', strtotime($appointment['appointment_time'])) ?>
                                        (<?= ucfirst(htmlspecialchars($appointment['appointment_type'] ?? 'physical')) ?>)
                                    </span>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="message-container p-3" id="messageContainer">
                                    <?php if (empty($messages)): ?>
                                            <div class="text-center py-5 text-muted"><i class="fas fa-comment-slash fa-3x mb-3"></i><p>No messages yet. Start the conversation below.</p></div>
                                    <?php else: ?>
                                            <?php foreach ($messages as $msg):
                                                $isDoctorSender = ($msg['sender_id'] == $doctor_user_id); ?>
                                                    <div class="d-flex <?= $isDoctorSender ? 'justify-content-end' : 'justify-content-start' ?> mb-2">
                                                        <div class="message-bubble p-2 px-3 <?= $isDoctorSender ? 'doctor-message' : 'patient-message' ?>">
                                                            <p class="mb-1"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                                                            <div class="message-meta"> <?= htmlspecialchars($msg['sender_name']) ?> - <?= date('M j, g:i A', strtotime($msg['created_at'])) ?></div>
                                                        </div>
                                                    </div>
                                            <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-footer">
                                <form id="messageForm" method="POST">
                                    <input type="hidden" name="appointment_id" value="<?= $appointment_id ?>">
                                    <div id="formError" class="alert alert-danger d-none mb-3" role="alert"></div>
                                    <div class="input-group">
                                        <textarea class="form-control" name="message" rows="2" placeholder="Type your message to <?= htmlspecialchars($patient_name) ?>..." required aria-label="Message" id="messageInput"></textarea>
                                        <button type="submit" name="send_message" class="btn btn-primary" id="sendButton">
                                            <span class="loading-spinner"><i class="fas fa-spinner fa-spin"></i></span>
                                            <span class="send-text"><i class="fas fa-paper-plane"></i> Send</span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
        <?php elseif (empty($page_error)): // Show only if no other error occurred ?>
                <div class="alert alert-warning">Consultation details could not be loaded.</div>
                <a href="appointments.php" class="btn btn-primary"><i class="fas fa-arrow-left me-1"></i> Back to Appointments</a>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const messageContainer = document.getElementById('messageContainer');
            const messageForm = document.getElementById('messageForm');
            const messageInput = document.getElementById('messageInput'); // Use ID
            const sendButton = document.getElementById('sendButton');
            const formErrorDiv = document.getElementById('formError');
            const appointmentId = <?= $appointment_id ?>;
            // Assumes BASE_URL is defined globally if needed, otherwise use relative path
            const baseUrl = "<?= defined('BASE_URL') ? rtrim(BASE_URL, '/') : '.' ?>"; // Use '.' for relative path base

            function scrollToBottom() { if (messageContainer) { messageContainer.scrollTop = messageContainer.scrollHeight; } }
            function showFormError(message) { if (formErrorDiv) { formErrorDiv.textContent = message; formErrorDiv.classList.remove('d-none'); } }
            function hideFormError() { if (formErrorDiv) { formErrorDiv.classList.add('d-none'); formErrorDiv.textContent = ''; } }

            // Function to dynamically add a new message bubble
            function addNewMessage(msgData) {
                 if (!messageContainer) return;
                 const isDoctor = msgData.is_doctor; // Assuming response includes this flag

                 const msgDiv = document.createElement('div');
                 msgDiv.className = `d-flex ${isDoctor ? 'justify-content-end' : 'justify-content-start'} mb-2`;

                 const bubbleDiv = document.createElement('div');
                 bubbleDiv.className = `message-bubble p-2 px-3 ${isDoctor ? 'doctor-message' : 'patient-message'}`;

                 const contentP = document.createElement('p');
                 contentP.className = 'mb-1';
                 contentP.innerHTML = msgData.content.replace(/\n/g, '<br>'); // Use innerHTML for line breaks

                 const metaDiv = document.createElement('div');
                 metaDiv.className = 'message-meta';
                 // Format timestamp from ISO string returned by PHP/DB
                 const msgDate = new Date(msgData.created_at);
                 const formattedTime = msgDate.toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
                 metaDiv.textContent = `${msgData.sender_name} - ${formattedTime}`;

                 bubbleDiv.appendChild(contentP);
                 bubbleDiv.appendChild(metaDiv);
                 msgDiv.appendChild(bubbleDiv);

                 // Remove "No messages yet" placeholder if it exists
                 const placeholder = messageContainer.querySelector('.text-center.text-muted');
                 if (placeholder) placeholder.remove();

                 messageContainer.appendChild(msgDiv);
                 scrollToBottom();
            }


            // Initial scroll
            scrollToBottom();

            if (messageForm && sendButton) {
                messageForm.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    hideFormError();
                    const formData = new FormData(messageForm);
                    // Add send_message to formData for PHP check if needed by PHP logic
                    // formData.append('send_message', '1'); // Not strictly needed if checking $_POST['message']

                    sendButton.classList.add('sending');
                    sendButton.disabled = true;

                    try {
                        // Submit to the current page URL (which includes appointment_id)
                        // Ensure the action attribute in the form tag is correct or use window.location.href
                        const response = await fetch(messageForm.action || window.location.href, {
                            method: 'POST',
                            body: formData,
                            headers: { // Important for AJAX detection and response type
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            }
                        });

                        const contentType = response.headers.get('content-type');
                        if (!response.ok || !contentType || !contentType.includes('application/json')) {
                            const text = await response.text();
                            console.error('Non-JSON or Error Response:', text);
                            throw new Error(`Server error (${response.status}) or invalid response format.`);
                        }

                        const data = await response.json();

                        if (data.success) {
                            // Add the new message dynamically instead of reloading
                            if (data.message) { // Check if message data was returned
                                addNewMessage(data.message);
                            } else {
                                // Fallback to reload if message data wasn't returned
                                console.warn("Success response received, but no message data. Reloading page.");
                                window.location.reload();
                            }
                            messageInput.value = ''; // Clear input field
                        } else {
                            throw new Error(data.error || 'Failed to send message');
                        }
                    } catch (error) {
                        console.error('Fetch/Processing Error:', error);
                        showFormError(`Error: ${error.message}. Please try again.`);
                    } finally {
                        sendButton.classList.remove('sending');
                        sendButton.disabled = false;
                    }
                });
            }
            // Optional: Auto-refresh logic could go here
        });
    </script>
</body>
</html>
<?php
require_once __DIR__ . '/../footer.php'; // Assuming footer.php is one level up
ob_end_flush(); // Send output buffer if needed
?>
