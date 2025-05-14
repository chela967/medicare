<?php
// order_confirmation.php

// Start session BEFORE any output
session_start();

// Include configuration and functions
require_once 'config.php';
require_once 'functions.php'; // Should define getOrderDetails(), getOrderItems()

// --- Security & Initialization ---

// Set security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Redirect if not logged in
if (!isset($_SESSION['user'])) {
    header("Location: auth.php?redirect=order_confirmation");
    exit();
}

// Check if order ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: cart.php?error=invalid_order");
    exit();
}

$order_id = (int)$_GET['id'];
$user_id = (int)$_SESSION['user']['id'];

// --- Get Order Data ---
$order = [];
$order_items = [];
$error_message = '';

// Get order details (ensure user owns this order)
if (function_exists('getOrderDetails')) {
    $order = getOrderDetails($order_id, $user_id);
    
    if (!$order) {
        $error_message = "Order not found or you don't have permission to view it.";
    } else {
        // Get order items if order exists
        if (function_exists('getOrderItems')) {
            $order_items = getOrderItems($order_id);
        } else {
            $error_message = "Error: Required function 'getOrderItems' is missing.";
        }
    }
} else {
    $error_message = "Error: Required function 'getOrderDetails' is missing.";
}

// Calculate delivery fee (should match what was used in cart.php)
$delivery_fee = 5.00;
$total = ($order['total_amount'] ?? 0) + $delivery_fee;

// Set page title
$page_title = "Order Confirmation - Medicare";

// --- Include Header ---
include 'header.php';
?>

<main class="order-confirmation py-5 bg-light">
    <div class="container">
        <?php if ($error_message): ?>
            <div class="alert alert-danger text-center"><?= htmlspecialchars($error_message) ?></div>
            <div class="text-center mt-4">
                <a href="epharmacy.php" class="btn btn-primary">Browse Medicines</a>
                <a href="cart.php" class="btn btn-outline-secondary ms-2">View Cart</a>
            </div>
        <?php else: ?>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow-sm mb-4">
                        <div class="card-body text-center">
                            <div class="mb-4">
                                <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                            </div>
                            <h1 class="card-title mb-3">Thank You for Your Order!</h1>
                            <p class="lead">Your order has been placed successfully.</p>
                            <p>Order Number: <strong>#<?= htmlspecialchars($order_id) ?></strong></p>
                            <p>Order Date: <strong><?= date('F j, Y \a\t g:i A', strtotime($order['created_at'])) ?></strong></p>
                            
                            <?php if ($order['status'] === 'pending'): ?>
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle me-2"></i> Your order is being processed.
                                </div>
                            <?php elseif ($order['status'] === 'completed'): ?>
                                <div class="alert alert-success mt-3">
                                    <i class="fas fa-check-circle me-2"></i> Your order has been completed.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <h5 class="card-title mb-4">Order Details</h5>
                            
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Medicine</th>
                                            <th class="text-end">Price</th>
                                            <th class="text-center">Quantity</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order_items as $item): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="assets/medicines/<?= htmlspecialchars($item['image'] ?? 'default.jpg') ?>"
                                                            class="img-thumbnail me-3" width="60"
                                                            alt="<?= htmlspecialchars($item['name'] ?? 'N/A') ?>"
                                                            onerror="this.src='assets/medicines/default.png'">
                                                        <div>
                                                            <h6 class="mb-0"><?= htmlspecialchars($item['name'] ?? 'N/A') ?></h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-end">$<?= number_format($item['price'] ?? 0, 2) ?></td>
                                                <td class="text-center"><?= (int)$item['quantity'] ?></td>
                                                <td class="text-end">$<?= number_format(($item['price'] ?? 0) * ($item['quantity'] ?? 0), 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="3" class="text-end">Subtotal:</th>
                                            <td class="text-end">$<?= number_format($order['total_amount'] ?? 0, 2) ?></td>
                                        </tr>
                                        <tr>
                                            <th colspan="3" class="text-end">Delivery Fee:</th>
                                            <td class="text-end">$<?= number_format($delivery_fee, 2) ?></td>
                                        </tr>
                                        <tr>
                                            <th colspan="3" class="text-end">Total:</th>
                                            <td class="text-end">$<?= number_format($total, 2) ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Payment Information</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Payment Method:</strong><br>
                                    <?php 
                                    // Get payment method name from ID
                                    $payment_methods = [
                                        1 => 'Mobile Money',
                                        2 => 'Credit Card',
                                        3 => 'PayPal',
                                        4 => 'Cash on Delivery'
                                    ];
                                    echo htmlspecialchars($payment_methods[$order['payment_method_id'] ?? 'Unknown']);
                                    ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Order Status:</strong><br>
                                    <span class="badge 
                                        <?= $order['status'] === 'completed' ? 'bg-success' : 
                                           ($order['status'] === 'cancelled' ? 'bg-danger' : 'bg-warning') ?>">
                                        <?= ucfirst(htmlspecialchars($order['status'])) ?>
                                    </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <a href="epharmacy.php" class="btn btn-primary">Continue Shopping</a>
                        <a href="order_history.php" class="btn btn-outline-secondary ms-2">View Order History</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php
// --- Include Footer ---
include 'footer.php';
?>