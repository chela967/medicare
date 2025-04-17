<?php
// cart.php

// Start session BEFORE any output
session_start();

// Include configuration and functions
// Ensure these files exist and contain the necessary definitions
require_once 'config.php'; // Should define $conn (MySQLi connection)
require_once 'functions.php'; // Should define getCart(), updateCartItem(), removeCartItem(), processCheckout(), calculateSubtotal(), getPaymentMethods()

// --- Security & Initialization ---

// Set security headers (optional but recommended)
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Redirect if not logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    // Redirect to login page, passing the current page as a redirect target
    header("Location: auth.php?redirect=cart");
    exit();
}

// Get the logged-in user's ID
$user_id = (int) $_SESSION['user']['id'];

// --- TEMPORARY DEBUG LINE ---
// This will show which user ID the cart page is currently using.
// Check if this ID matches the user_id (e.g., 7) for the items in your database cart table.
// REMOVE THIS LINE AFTER DEBUGGING!
echo "<div class='alert alert-warning' style='position:relative; z-index:9999; margin: 10px;'>Debugging: Cart page is using user_id: " . htmlspecialchars($user_id) . "</div>";
// --- END DEBUG LINE ---


$page_title = "My Cart - Medicare";
$error_message = '';
$success_message = '';

// --- Process Cart Actions (Update Quantity, Remove Item, Checkout) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF Token Validation (Assuming you have one in your form/session)
    // if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    //     $error_message = "Invalid request (CSRF token mismatch).";
    // } else {

    if (isset($_POST['update_cart'])) {
        // Handle cart updates
        if (isset($_POST['quantity']) && is_array($_POST['quantity'])) {
            $updated_count = 0;
            foreach ($_POST['quantity'] as $cart_id => $quantity) {
                // Basic validation for quantity
                $cart_id_int = (int) $cart_id;
                $quantity_int = (int) $quantity;
                if ($cart_id_int > 0 && $quantity_int > 0) {
                    // Ideally, updateCartItem should return true/false or throw an Exception
                    // And also check against stock levels before updating!
                    if (function_exists('updateCartItem')) {
                        updateCartItem($cart_id_int, $user_id, $quantity_int); // Assuming this function handles DB interaction
                        $updated_count++;
                    } else {
                        $error_message = "Error: Required function 'updateCartItem' is missing.";
                        break; // Stop processing if function missing
                    }
                }
            }
            if ($updated_count > 0 && empty($error_message)) {
                $success_message = "Cart updated successfully.";
                // It's often better to redirect after POST to prevent re-submission
                // header("Location: cart.php?status=updated");
                // exit();
            } elseif (empty($error_message)) {
                $error_message = "No valid quantities provided for update.";
            }
        } else {
            $error_message = "Invalid update request.";
        }

    } elseif (isset($_POST['remove_item']) && isset($_POST['cart_id'])) {
        // Handle item removal
        $cart_id_to_remove = (int) $_POST['cart_id'];
        if ($cart_id_to_remove > 0) {
            if (function_exists('removeCartItem')) {
                removeCartItem($cart_id_to_remove, $user_id); // Assuming this function handles DB interaction
                $success_message = "Item removed successfully.";
                // It's often better to redirect after POST
                // header("Location: cart.php?status=removed");
                // exit();
            } else {
                $error_message = "Error: Required function 'removeCartItem' is missing.";
            }
        } else {
            $error_message = "Invalid item ID for removal.";
        }

    } elseif (isset($_POST['checkout'])) {
        // Process checkout
        $payment_method_id = isset($_POST['payment_method']) ? (int) $_POST['payment_method'] : 0;

        if ($payment_method_id <= 0) {
            $error_message = "Please select a valid payment method.";
        } else {
            if (function_exists('processCheckout')) {
                // processCheckout should handle order creation, clearing cart, etc.
                // It should return the order_id on success, or false/null on failure.
                $order_id = processCheckout($user_id, $payment_method_id);
                if ($order_id) {
                    // Redirect to a confirmation page
                    header("Location: order_confirmation.php?id=" . $order_id);
                    exit();
                } else {
                    // processCheckout should ideally set a more specific error message via a session flash or return value
                    $error_message = "Checkout failed. Please try again or contact support.";
                }
            } else {
                $error_message = "Error: Required function 'processCheckout' is missing.";
            }
        }
    }
    // } // End CSRF check brace
}

// --- Get Cart Data (After potential updates/removals) ---
// Ensure the getCart function exists before calling
if (function_exists('getCart')) {
    $cart_items = getCart($user_id);
} else {
    $cart_items = []; // Default to empty if function missing
    $error_message = "Error: Required function 'getCart' is missing. Cannot display cart items.";
}


// Calculate totals (Ensure function exists)
if (function_exists('calculateSubtotal')) {
    $subtotal = calculateSubtotal($cart_items); // Assuming this function calculates sum based on items
} else {
    $subtotal = 0; // Default if function missing
    if (!empty($cart_items)) { // Show error only if cart items were expected
        $error_message .= " Error: Required function 'calculateSubtotal' is missing.";
    }
}

$delivery_fee = 5.00; // Example fixed delivery fee
$total = $subtotal + $delivery_fee;

// --- Include Header ---
// Make sure header.php doesn't start session again or output before this point
include 'header.php';
?>

<main class="cart-page py-5 bg-light">
    <div class="container">

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8 mb-4 mb-lg-0">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h2 class="mb-4 card-title">Your Shopping Cart</h2>

                        <?php if (empty($cart_items)): ?>
                            <div class="alert alert-info">
                                Your cart is empty. <a href="epharmacy.php">Browse medicines</a>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="cart.php">
                                <div class="table-responsive mb-4">
                                    <table class="table align-middle">
                                        <thead>
                                            <tr>
                                                <th>Medicine</th>
                                                <th class="text-end">Price</th>
                                                <th class="text-center">Quantity</th>
                                                <th class="text-end">Total</th>
                                                <th class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($cart_items as $item): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <img src="assets/medicines/<?= htmlspecialchars($item['image'] ?? 'default.jpg') ?>"
                                                                class="img-thumbnail me-3" width="60"
                                                                alt="<?= htmlspecialchars($item['name'] ?? 'N/A') ?>"
                                                                onerror="this.src='assets/medicines/default.png'">
                                                            <div>
                                                                <h6 class="mb-0"><?= htmlspecialchars($item['name'] ?? 'N/A') ?>
                                                                </h6>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-end">$<?= number_format($item['price'] ?? 0, 2) ?></td>
                                                    <td class="text-center">
                                                        <input type="number" name="quantity[<?= (int) $item['id'] ?>]"
                                                            value="<?= (int) $item['quantity'] ?>" min="1" max="10"
                                                            class="form-control form-control-sm mx-auto" style="width: 70px;">
                                                    </td>
                                                    <td class="text-end">
                                                        $<?= number_format(((float) ($item['price'] ?? 0)) * ((int) $item['quantity']), 2) ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <form method="POST" action="cart.php" style="display: inline;">
                                                            <input type="hidden" name="cart_id"
                                                                value="<?= (int) $item['id'] ?>">
                                                            <button type="submit" name="remove_item"
                                                                class="btn btn-sm btn-outline-danger" title="Remove Item"
                                                                onclick="return confirm('Are you sure you want to remove this item?')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex justify-content-between align-items-center">
                                    <a href="epharmacy.php" class="btn btn-outline-primary">
                                        <i class="fas fa-chevron-left me-1"></i>Continue Shopping
                                    </a>
                                    <button type="submit" name="update_cart" class="btn btn-secondary">
                                        <i class="fas fa-sync-alt me-1"></i>Update Quantities
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Order Summary</h5>

                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span>$<?= number_format($subtotal, 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Delivery Fee:</span>
                            <span>$<?= number_format($delivery_fee, 2) ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between fw-bold h5 mb-4">
                            <span>Total:</span>
                            <span>$<?= number_format($total, 2) ?></span>
                        </div>

                        <?php if (!empty($cart_items)): ?>
                            <form method="POST" action="cart.php">
                                <h5 class="mb-3">Payment Method</h5>

                                <?php
                                // Get payment methods (Ensure function exists)
                                if (function_exists('getPaymentMethods')) {
                                    $payment_methods = getPaymentMethods(); // Assuming this returns an array of methods
                                } else {
                                    $payment_methods = [];
                                    echo "<p class='text-danger'>Error: Could not load payment methods.</p>";
                                }

                                if (!empty($payment_methods)) {
                                    foreach ($payment_methods as $index => $method):
                                        // Basic check for expected keys
                                        $method_id = $method['id'] ?? null;
                                        $method_name = $method['name'] ?? 'Unnamed Method';
                                        $method_icon = $method['icon'] ?? 'fas fa-question-circle';
                                        if ($method_id === null)
                                            continue; // Skip if ID is missing
                                        ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" name="payment_method"
                                                id="method<?= (int) $method_id ?>" value="<?= (int) $method_id ?>" required
                                                <?= ($index === 0) ? 'checked' : '' ?>> <label class="form-check-label"
                                                for="method<?= (int) $method_id ?>">
                                                <i class="<?= htmlspecialchars($method_icon) ?> me-2"></i>
                                                <?= htmlspecialchars($method_name) ?>
                                            </label>
                                        </div>
                                        <?php
                                    endforeach;
                                } else if (function_exists('getPaymentMethods')) {
                                    echo "<p class='text-muted'>No payment methods available.</p>";
                                }
                                ?>

                                <?php if (!empty($payment_methods)): // Only show button if methods exist ?>
                                    <button type="submit" name="checkout" class="btn btn-success w-100 mt-3 py-2">
                                        <i class="fas fa-lock me-2"></i>Proceed to Checkout
                                    </button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
// --- Include Footer ---
include 'footer.php';
?>