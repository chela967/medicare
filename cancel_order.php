<?php
session_start();
require_once __DIR__ . '/config.php';

// Check if user is logged in as patient
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'patient') {
    header("Location: login.php");
    exit;
}

// Validate order ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Invalid order ID'];
    header("Location: my_orders.php");
    exit;
}

$order_id = (int) $_GET['id'];
$patient_id = (int) ($_SESSION['user']['id'] ?? 0);

// Initialize variables
$order = [];
$error = '';
$conn = null;

try {
    // Create database connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Verify order belongs to patient and is cancellable
    $sql = "SELECT o.* 
            FROM orders o
            WHERE o.id = ? AND o.user_id = ? 
            AND o.status IN ('pending', 'processing')";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ii", $order_id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();

    if (!$order) {
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'text' => 'Order cannot be cancelled or does not exist'
        ];
        header("Location: my_orders.php");
        exit;
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cancellation_reason = trim($_POST['cancellation_reason'] ?? '');

        if (empty($cancellation_reason)) {
            $error = "Please provide a cancellation reason";
        } else {
            // Start transaction
            $conn->begin_transaction();

            try {
                // 1. Update order status
                $update_sql = "UPDATE orders 
                               SET status = 'cancelled', 
                                   cancellation_reason = ?, 
                                   cancelled_at = NOW() 
                               WHERE id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("si", $cancellation_reason, $order_id);
                $stmt->execute();
                $stmt->close();

                // 2. Restore medicine quantities
                $restore_sql = "UPDATE medicines m
                                JOIN order_items oi ON m.id = oi.medicine_id
                                SET m.stock = m.stock + oi.quantity
                                WHERE oi.order_id = ?";
                $stmt = $conn->prepare($restore_sql);
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $stmt->close();

                // 3. Create notification
                $notification_sql = "INSERT INTO notifications 
                                    (user_id, title, message, type) 
                                    VALUES (1, 'Order Cancelled', 
                                    CONCAT('Order #', ?, ' was cancelled by customer'), 'order')";
                $stmt = $conn->prepare($notification_sql);
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $stmt->close();

                // Commit transaction
                $conn->commit();

                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'text' => 'Order cancelled successfully'
                ];
                header("Location: my_orders.php");
                exit;

            } catch (Exception $e) {
                $conn->rollback();
                error_log("Order Cancellation Error: " . $e->getMessage());
                $error = "Failed to cancel order. Please try again.";
            }
        }
    }

} catch (Exception $e) {
    error_log("System Error: " . $e->getMessage());
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'text' => 'A system error occurred. Please try again later.'
    ];
    header("Location: my_orders.php");
    exit;
}

$page_title = "Cancel Order";
require_once __DIR__ . '/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-times-circle me-2"></i> Cancel Order</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <div class="alert alert-warning">
                        <h5 class="alert-heading">Order Details</h5>
                        <p>
                            <strong>Order #:</strong> <?= htmlspecialchars($order['id'] ?? '') ?><br>
                            <strong>Date:</strong> <?= date('M j, Y', strtotime($order['created_at'] ?? 'now')) ?><br>
                            <strong>Total Amount:</strong> UGX <?= number_format($order['total_amount'] ?? 0, 2) ?>
                        </p>
                    </div>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="cancellation_reason" class="form-label">
                                <strong>Reason for Cancellation</strong>
                            </label>
                            <textarea class="form-control" id="cancellation_reason" name="cancellation_reason" rows="4"
                                required placeholder="Please explain why you're cancelling this order"></textarea>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="confirm_cancel" required>
                                <label class="form-check-label" for="confirm_cancel">
                                    I understand that cancelling this order will refund my payment according to the
                                    refund policy
                                </label>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="order_details.php?id=<?= $order_id ?>" class="btn btn-secondary me-md-2">
                                <i class="fas fa-arrow-left me-1"></i> Go Back
                            </a>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-times me-1"></i> Confirm Cancellation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Close connection if it exists
if ($conn instanceof mysqli) {
    $conn->close();
}
require_once __DIR__ . '/footer.php';
?>