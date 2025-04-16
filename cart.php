<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Redirect if not logged in
if (!isset($_SESSION['user'])) {
    header("Location: auth.php?redirect=cart");
    exit();
}

$user_id = $_SESSION['user']['id'];
$page_title = "My Cart - Medicare";

// Process cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_cart'])) {
        // Handle cart updates
        foreach ($_POST['quantity'] as $cart_id => $quantity) {
            updateCartItem($cart_id, $user_id, $quantity);
        }
    } elseif (isset($_POST['remove_item'])) {
        removeCartItem($_POST['cart_id'], $user_id);
    } elseif (isset($_POST['checkout'])) {
        // Process checkout
        $order_id = processCheckout($user_id, $_POST['payment_method']);
        if ($order_id) {
            header("Location: order_confirmation.php?id=$order_id");
            exit();
        }
    }
}

// Get cart data
$cart_items = getCart($user_id);
$subtotal = calculateSubtotal($cart_items);
$total = $subtotal; // Can add taxes/shipping here

include 'header.php';
?>

<div class="cart-page py-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <h2 class="mb-4">Your Shopping Cart</h2>

                <?php if (empty($cart_items)): ?>
                    <div class="alert alert-info">
                        Your cart is empty. <a href="epharmacy.php">Browse medicines</a>
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <div class="card shadow-sm mb-4">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Medicine</th>
                                                <th>Price</th>
                                                <th>Quantity</th>
                                                <th>Total</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($cart_items as $item): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <img src="assets/medicines/<?= htmlspecialchars($item['image']) ?>"
                                                                class="img-thumbnail me-3" width="60"
                                                                alt="<?= htmlspecialchars($item['name']) ?>">
                                                            <div>
                                                                <h6 class="mb-0"><?= htmlspecialchars($item['name']) ?></h6>
                                                                <small
                                                                    class="text-muted"><?= htmlspecialchars($item['manufacturer']) ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>$<?= number_format($item['price'], 2) ?></td>
                                                    <td>
                                                        <input type="number" name="quantity[<?= $item['id'] ?>]"
                                                            value="<?= $item['quantity'] ?>" min="1" max="10"
                                                            class="form-control" style="width: 70px;">
                                                    </td>
                                                    <td>$<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                                                    <td>
                                                        <button type="submit" name="remove_item"
                                                            class="btn btn-sm btn-outline-danger"
                                                            onclick="return confirm('Remove this item?')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                        <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="epharmacy.php" class="btn btn-outline-primary">
                                        <i class="fas fa-chevron-left me-2"></i>Continue Shopping
                                    </a>
                                    <button type="submit" name="update_cart" class="btn btn-primary">
                                        <i class="fas fa-sync-alt me-2"></i>Update Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
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
                            <span>$5.00</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between fw-bold mb-4">
                            <span>Total:</span>
                            <span>$<?= number_format($total + 5, 2) ?></span>
                        </div>

                        <?php if (!empty($cart_items)): ?>
                            <form method="POST">
                                <h5 class="mb-3">Payment Method</h5>

                                <?php
                                // Get payment methods from database
                                $payment_methods = getPaymentMethods();
                                foreach ($payment_methods as $method):
                                    ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="payment_method"
                                            id="method<?= $method['id'] ?>" value="<?= $method['id'] ?>" required>
                                        <label class="form-check-label" for="method<?= $method['id'] ?>">
                                            <i class="<?= $method['icon'] ?> me-2"></i>
                                            <?= htmlspecialchars($method['name']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>

                                <button type="submit" name="checkout" class="btn btn-success w-100 mt-3 py-2">
                                    <i class="fas fa-lock me-2"></i>Proceed to Checkout
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>