<?php
session_start();
require_once __DIR__ . '/config.php'; // Assuming set_flash_message is defined or included here

// Authentication
if (!isset($_SESSION['user'])) {
    // Assuming set_flash_message is a function you have for session-based messages
    if (function_exists('set_flash_message')) {
        set_flash_message('Please log in to view notifications.', 'warning');
    } else {
        // Fallback if function doesn't exist, store directly in session
        $_SESSION['flash_messages'][] = ['message' => 'Please log in to view notifications.', 'type' => 'warning'];
    }
    header("Location: auth.php"); // Ensure auth.php is your login page
    exit();
}

$user_id = $_SESSION['user']['id'];
$page_title = "My Notifications";
$notifications = [];
$db_error = null;

// Mark Notifications as Read & Fetch
if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    try {
        // Dynamically determine columns to select (your existing good logic)
        $columns = [];
        $result_cols = $conn->query("SHOW COLUMNS FROM notifications");
        if ($result_cols) {
            while ($row_col = $result_cols->fetch_assoc()) {
                $columns[] = $row_col['Field'];
            }
        } else {
            throw new mysqli_sql_exception("Failed to fetch columns for notifications table: " . $conn->error);
        }

        $select_fields = ['id', 'title', 'message', 'type', 'is_read', 'created_at'];
        if (in_array('link', $columns)) {
            $select_fields[] = 'link';
        }
        if (in_array('appointment_id', $columns)) { // Assuming appointment_id can be in notifications
            $select_fields[] = 'appointment_id';
        }
        // Add other optional fields if they exist e.g. 'related_id', 'icon_class_override' etc.

        $sql_fetch = "SELECT " . implode(', ', $select_fields) . " 
                      FROM notifications 
                      WHERE user_id = ? 
                      ORDER BY created_at DESC";

        $stmt_fetch = $conn->prepare($sql_fetch);
        if (!$stmt_fetch) {
            throw new mysqli_sql_exception("Prepare failed (fetch notifications): " . $conn->error);
        }
        $stmt_fetch->bind_param("i", $user_id);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();
        $notifications = $result_fetch->fetch_all(MYSQLI_ASSOC);
        $stmt_fetch->close();

        // Mark all fetched notifications for this user as read (if they were unread)
        // This is efficient as it only updates those that were previously is_read = 0
        $sql_update = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
        $stmt_update = $conn->prepare($sql_update);
        if (!$stmt_update) {
            throw new mysqli_sql_exception("Prepare failed (update read status): " . $conn->error);
        }
        $stmt_update->bind_param("i", $user_id);
        $stmt_update->execute();
        // We don't need to check affected_rows unless we want to give feedback like "X new notifications were marked read"
        $stmt_update->close();

    } catch (mysqli_sql_exception $e) {
        error_log("Notifications Page DB Error: " . $e->getMessage());
        $db_error = "Error loading notifications. Please try again later."; // User-friendly message
    } catch (Exception $e) { // Catch any other general exceptions
        error_log("Notifications Page General Error: " . $e->getMessage());
        $db_error = "An unexpected error occurred. Please try again later.";
    }
} else {
    $db_error = "Database connection error. Please contact support.";
    error_log("Notifications Page: Database connection not available.");
}

require_once __DIR__ . '/header.php'; // Assuming header.php sets up HTML head, body, and nav
?>

<div class="container py-4 my-4">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-8">
            <h1 class="text-center mb-4"><?= htmlspecialchars($page_title) ?></h1>

            <?php if (isset($_SESSION['flash_messages'])): ?>
                <?php foreach ($_SESSION['flash_messages'] as $flash_message): ?>
                    <div class="alert alert-<?= htmlspecialchars($flash_message['type']) ?> alert-dismissible fade show"
                        role="alert">
                        <?= htmlspecialchars($flash_message['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endforeach; ?>
                <?php unset($_SESSION['flash_messages']); ?>
            <?php endif; ?>

            <?php if ($db_error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($db_error) ?></div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (empty($notifications) && !$db_error): ?>
                            <div class="list-group-item text-center text-muted py-5">
                                <i class="fas fa-bell-slash fa-3x mb-3 d-block"></i>
                                You have no new notifications.
                            </div>
                        <?php elseif (!empty($notifications)): ?>
                            <?php foreach ($notifications as $notification): ?>
                                <?php
                                // Determine icon, icon color, and style based on notification type
                                $icon = 'fa-info-circle';
                                $icon_color_class = 'text-secondary';
                                $base_style_class = $notification['is_read'] ? 'list-group-item-light notification-read' : 'notification-unread fw-bold';
                                $specific_style_class = '';

                                // Customize based on notification type
                                switch ($notification['type']) {
                                    case 'message':
                                        $icon = 'fa-envelope';
                                        $icon_color_class = 'text-info';
                                        if (!$notification['is_read'])
                                            $specific_style_class = 'list-group-item-info-soft';
                                        break;
                                    case 'appointment':
                                        $icon = 'fa-calendar-check';
                                        $icon_color_class = 'text-success';
                                        if (!$notification['is_read'])
                                            $specific_style_class = 'list-group-item-success-soft';
                                        break;
                                    case 'approval': // e.g. doctor approval
                                        $icon = 'fa-user-check';
                                        $icon_color_class = 'text-primary'; // Bootstrap primary
                                        if (!$notification['is_read'])
                                            $specific_style_class = 'list-group-item-primary-soft';
                                        break;
                                    case 'payment_success':
                                        $icon = 'fa-credit-card';
                                        $icon_color_class = 'text-success';
                                        if (!$notification['is_read'])
                                            $specific_style_class = 'list-group-item-success-soft';
                                        break;
                                    case 'payment_failed':
                                        $icon = 'fa-exclamation-triangle';
                                        $icon_color_class = 'text-danger';
                                        if (!$notification['is_read'])
                                            $specific_style_class = 'list-group-item-danger-soft';
                                        break;
                                    // Add more cases as your system grows
                                }
                                $final_style_class = $base_style_class . ($specific_style_class ? ' ' . $specific_style_class : '');

                                // Determine the link
                                $link = '#';
                                if (!empty($notification['link'])) {
                                    $link = htmlspecialchars($notification['link']);
                                } elseif (isset($notification['appointment_id']) && !empty($notification['appointment_id'])) {
                                    if ($notification['type'] === 'appointment') {
                                        $link = "appointment_details.php?id=" . (int) $notification['appointment_id'];
                                    } elseif ($notification['type'] === 'message') {
                                        // This link might depend on user role (patient vs doctor)
                                        // For a patient, it might be:
                                        $link = "messages.php?appointment_id=" . (int) $notification['appointment_id'];
                                    }
                                }
                                ?>
                                <a href="<?= $link ?>"
                                    class="list-group-item list-group-item-action <?= $final_style_class ?> notification-item-container py-3 px-3">

                                    <div class="notification-icon-badge me-2 me-sm-3">
                                        <i class="fas <?= $icon ?> <?= $icon_color_class ?> fa-lg fa-fw"></i>
                                        <?php if (!$notification['is_read']): ?>
                                            <span class="badge bg-primary rounded-pill notification-new-badge">New</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="notification-content flex-grow-1">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1 notification-title"><?= htmlspecialchars($notification['title']) ?>
                                            </h6>
                                            <small class="text-muted notification-timestamp d-none d-sm-inline-block"
                                                style="font-size: 0.8em;">
                                                <?= date('M j, Y, g:i A', strtotime($notification['created_at'])) ?>
                                            </small>
                                        </div>
                                        <p class="mb-1 notification-message small">
                                            <?= nl2br(htmlspecialchars($notification['message'])) ?>
                                        </p>
                                        <small class="text-muted notification-timestamp d-block d-sm-none mt-2"
                                            style="font-size: 0.8em;">
                                            <?= date('M j, Y, g:i A', strtotime($notification['created_at'])) ?>
                                        </small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="text-center mt-4">
                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-sync-alt me-1"></i> Refresh Notifications
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    /* Custom styles for notifications - move to your main CSS file if preferred */
    .notification-item-container {
        display: flex;
        align-items: flex-start;
        /* Align items to the top */
        gap: 0.75rem;
        /* Gap between icon area and content area */
    }

    .notification-icon-badge {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-start;
        padding-top: 0.125rem;
        /* Align icon better with title */
    }

    .notification-icon-badge .fas {
        font-size: 1.25rem;
        /* Slightly larger icon */
    }

    .notification-new-badge {
        font-size: 0.65em;
        padding: 0.2em 0.4em;
        margin-top: 0.25rem;
    }

    .notification-content {
        min-width: 0;
        /* Allow content to shrink and wrap */
    }

    .notification-title {
        /* fw-bold is applied dynamically for unread items */
    }

    .notification-read .notification-title,
    .notification-read .notification-message,
    .notification-read .notification-timestamp {
        color: #6c757d;
        /* Bootstrap's muted color */
    }

    .notification-read .fas {
        /* Mute icon color for read items if not specifically colored by type */
        /* color: #6c757d !important; */
        /* Optional: Mute generic icons too */
    }

    .notification-message {
        color: #495057;
        line-height: 1.4;
    }

    .notification-unread {
        /* For unread items if no specific type class is applied */
        /* background-color: #f8f9fa; /* Example: slightly off-white for unread */
    }

    /* Soft background colors for unread notifications by type */
    .list-group-item-info-soft.notification-unread {
        background-color: #cff4fc;
        border-left: 3px solid #0dcaf0;
    }

    .list-group-item-success-soft.notification-unread {
        background-color: #d1e7dd;
        border-left: 3px solid #198754;
    }

    .list-group-item-primary-soft.notification-unread {
        background-color: #cfe2ff;
        border-left: 3px solid #0d6efd;
    }

    .list-group-item-danger-soft.notification-unread {
        background-color: #f8d7da;
        border-left: 3px solid #dc3545;
    }

    .list-group-item-purple-soft.notification-unread {
        background-color: #e9d5ff;
        border-left: 3px solid #6f42c1;
    }

    /* Custom purple */

    .text-purple .fas {
        color: #6f42c1;
    }

    /* Custom purple for icons */


    /* For the timestamp positioning */
    /* On small screens (xs), the d-sm-none timestamp will show below the message. */
    /* On sm screens and up, the d-none d-sm-inline-block timestamp will show next to the title. */

    /* Adjustments for the refresh button if needed */
    .btn-outline-secondary:hover {
        /* Custom hover if needed */
    }
</style>

<?php require_once __DIR__ . '/footer.php'; // Assuming footer.php closes HTML tags ?>