<?php
session_start();
require_once __DIR__ . '/config.php'; // Provides $conn

// Authentication Check
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'patient') {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Please log in to view orders.'];
    // Adjust path to auth.php if it's not in the parent directory
    header("Location: auth.php");
    exit;
}

// Validate Order ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Invalid order ID.'];
    header("Location: my_orders.php");
    exit;
}

$order_id = (int) $_GET['id'];
$patient_id = $_SESSION['user']['id'];
$order = null;
$items = [];
$db_error_message = null;

// Check DB connection ($conn should be set by config.php)
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    $db_error_message = "Database connection error. Please check configuration.";
    error_log("Order Details Error: \$conn object not available or invalid after including config.php.");
    // Optionally die or handle gracefully
} else {
    try {
        // --- Fetch order details ---
        // Corrected SQL: Selects columns available in medicare (8).sql orders table
        // Removed join with non-existent delivery_areas
        $sql_order = "SELECT o.id, o.user_id, o.total_amount, o.payment_method_id,
                             o.transaction_id, o.payment_method, o.status,
                             DATE_FORMAT(o.created_at, '%W, %M %e, %Y at %h:%i %p') as formatted_date,
                             pm.name as payment_method_name
                      FROM orders o
                      LEFT JOIN payment_methods pm ON o.payment_method_id = pm.id
                      WHERE o.id = ? AND o.user_id = ?";

        // **** CHANGED: Use $conn ****
        $stmt_order = $conn->prepare($sql_order);
        if (!$stmt_order)
            throw new mysqli_sql_exception("Prepare failed (fetch order): " . $conn->error);

        $stmt_order->bind_param("ii", $order_id, $patient_id);
        $stmt_order->execute();
        $order_result = $stmt_order->get_result();
        $order = $order_result->fetch_assoc();
        $stmt_order->close();

        if (!$order) {
            // Order not found or doesn't belong to user
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Order not found or access denied.'];
            header("Location: my_orders.php");
            exit;
        }

        // --- Fetch order items ---
        // Corrected SQL: Joins with 'medicines' table using 'medicine_id'
        $sql_items = "SELECT oi.quantity, oi.price,
                             m.name as medicine_name, m.image as medicine_image, m.dosage
                      FROM order_items oi
                      JOIN medicines m ON oi.medicine_id = m.id
                      WHERE oi.order_id = ?";

        // **** CHANGED: Use $conn ****
        $stmt_items = $conn->prepare($sql_items);
        if (!$stmt_items)
            throw new mysqli_sql_exception("Prepare failed (fetch items): " . $conn->error);

        $stmt_items->bind_param("i", $order_id);
        $stmt_items->execute();
        $items_result = $stmt_items->get_result();
        $items = $items_result->fetch_all(MYSQLI_ASSOC);
        $stmt_items->close();

    } catch (mysqli_sql_exception $e) {
        error_log("Order Details Database Error: " . $e->getMessage() . " | SQL State: " . $e->getSqlState());
        $db_error_message = "Could not load order details due to a database issue.";
        // Clear potentially incomplete data if error occurred
        $order = null;
        $items = [];
    } catch (Exception $e) {
        error_log("Order Details General Error: " . $e->getMessage());
        $db_error_message = "An unexpected error occurred.";
        $order = null;
        $items = [];
    }
} // End DB connection check

$page_title = "Order Details #" . $order_id;
// Adjust path to header.php if it's not in the parent directory
require_once __DIR__ . '/header.php';
?>

<div class="container py-4">

    <?php if ($db_error_message): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($db_error_message) ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_message']['type']) ?> alert-dismissible fade show"
            role="alert">
            <?= htmlspecialchars($_SESSION['flash_message']['text']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <?php if ($order): // Only display details if order was successfully fetched ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Order #<?= htmlspecialchars($order['id']) ?></h1>
            <div>
                <a href="invoice.php?id=<?= $order['id'] ?>" class="btn btn-outline-secondary me-2" target="_blank">
                    <i class="fas fa-file-invoice me-1"></i> Invoice
                </a>
                <a href="my_orders.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-1"></i> Back to My Orders
                </a>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">Order Date</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($order['formatted_date']) ?></dd>

                            <dt class="col-sm-4">Status</dt>
                            <dd class="col-sm-8">
                                <?php
                                // Map order status to Bootstrap badge color
                                $order_status_color = 'secondary'; // Default
                                switch (strtolower($order['status'])) {
                                    case 'paid':
                                    case 'processing':
                                        $order_status_color = 'info';
                                        break;
                                    case 'shipped':
                                        $order_status_color = 'primary';
                                        break;
                                    case 'delivered':
                                    case 'completed':
                                        $order_status_color = 'success';
                                        break;
                                    case 'cancelled':
                                    case 'failed':
                                        $order_status_color = 'danger';
                                        break;
                                    case 'pending':
                                        $order_status_color = 'warning';
                                        break;
                                }
                                ?>
                                <span class="badge bg-<?= $order_status_color ?>">
                                    <?= ucfirst(htmlspecialchars($order['status'])) ?>
                                </span>
                            </dd>

                            <dt class="col-sm-4">Payment Method</dt>
                            <dd class="col-sm-8">
                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $order['payment_method_name'] ?? $order['payment_method'] ?? 'N/A'))) ?>
                            </dd>

                            <dt class="col-sm-4">Payment Status</dt>
                            <dd class="col-sm-8">
                                <span
                                    class="badge bg-<?= ($order['status'] === 'paid' || $order['status'] === 'processing' || $order['status'] === 'shipped' || $order['status'] === 'completed' || $order['status'] === 'delivered') ? 'success' : 'warning' ?>">
                                    <?= ($order['status'] === 'paid' || $order['status'] === 'processing' || $order['status'] === 'shipped' || $order['status'] === 'completed' || $order['status'] === 'delivered') ? 'Paid' : 'Pending' ?>
                                </span>
                            </dd>

                            <dt class="col-sm-4">Transaction ID</dt>
                            <dd class="col-sm-8"><?= htmlspecialchars($order['transaction_id'] ?? 'N/A') ?></dd>

                            <dt class="col-sm-4">Total Amount</dt>
                            <dd class="col-sm-8 fw-bold">UGX <?= number_format($order['total_amount'], 2) ?></dd>
                        </dl>
                    </div>
                </div>

            </div>

            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-pills me-2"></i> Order Items</h5>
                    </div>
                    <div class="card-body p-0"> <?php if (!empty($items)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 50%;">Item</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-end">Price</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $calculated_subtotal = 0;
                                        foreach ($items as $item):
                                            $item_total = $item['price'] * $item['quantity'];
                                            $calculated_subtotal += $item_total;
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!empty($item['medicine_image'])): ?>
                                                            <img src="uploads/medicines/<?= htmlspecialchars($item['medicine_image']) ?>"
                                                                alt="<?= htmlspecialchars($item['medicine_name']) ?>"
                                                                class="rounded me-2" width="40" height="40" style="object-fit: contain;"
                                                                onerror="this.style.display='none'"> <?php else: ?>
                                                            <div class="me-2"
                                                                style="width:40px; height:40px; background-color:#e9ecef; display:flex; align-items:center; justify-content:center; border-radius: 0.25rem;">
                                                                <i class="fas fa-pills text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <?= htmlspecialchars($item['medicine_name']) ?>
                                                            <?php if (!empty($item['dosage'])): ?>
                                                                <small
                                                                    class="d-block text-muted"><?= htmlspecialchars($item['dosage']) ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center"><?= $item['quantity'] ?></td>
                                                <td class="text-end">UGX <?= number_format($item['price'], 2) ?></td>
                                                <td class="text-end">UGX <?= number_format($item_total, 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="3" class="text-end border-top">Subtotal</th>
                                            <td class="text-end border-top">UGX <?= number_format($calculated_subtotal, 2) ?>
                                            </td>
                                        </tr>
                                        <?php
                                        // Assuming delivery fee is total - calculated subtotal
                                        // This might not be accurate if discounts/taxes apply
                                        $delivery_fee = $order['total_amount'] - $calculated_subtotal;
                                        // Only show if positive, adjust logic if delivery_fee is stored directly
                                        if ($delivery_fee > 0.01):
                                            ?>
                                            <tr>
                                                <th colspan="3" class="text-end">Delivery Fee</th>
                                                <td class="text-end">UGX <?= number_format($delivery_fee, 2) ?></td>
                                            </tr>
                                        <?php endif; ?>
                                        <tr class="table-light">
                                            <th colspan="3" class="text-end fs-5">Total</th>
                                            <td class="text-end fs-5 fw-bold">UGX
                                                <?= number_format($order['total_amount'], 2) ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted p-3">No items found for this order.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($order['status'] === 'pending' || $order['status'] === 'processing'): ?>
                    <div class="card shadow-sm mt-4 border-danger">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i> Cancel Order</h5>
                        </div>
                        <div class="card-body">
                            <p>You may be able to cancel this order if it has not yet been shipped.</p>
                            <a href="cancel_order.php?id=<?= $order['id'] ?>" class="btn btn-danger"
                                onclick="return confirm('Are you sure you want to request cancellation for this order?');">
                                <i class="fas fa-times me-1"></i> Request Cancellation
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <?php if (!$db_error_message): // Show not found only if there wasn't a DB error ?>
            <div class="alert alert-warning">Order details could not be loaded. It might not exist or you may not have
                permission to view it.</div>
            <a href="my_orders.php" class="btn btn-primary">
                <i class="fas fa-arrow-left me-1"></i> Back to My Orders
            </a>
        <?php endif; ?>
    <?php endif; // End if($order) ?>
</div>

<?php
// Adjust path to footer.php if it's not in the parent directory
require_once __DIR__ . '/footer.php';
?>