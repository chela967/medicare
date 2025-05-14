<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Redirect if not logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION['user']['id'];
$page_title = "My Cart - Medicare";
$error_message = '';
$success_message = '';

// Process cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_cart'])) {
        if (isset($_POST['quantity']) && is_array($_POST['quantity'])) {
            foreach ($_POST['quantity'] as $cart_id => $quantity) {
                $cart_id = (int) $cart_id;
                $quantity = (int) $quantity;
                if ($cart_id > 0 && $quantity > 0) {
                    $conn->query("UPDATE cart SET quantity = $quantity WHERE id = $cart_id AND user_id = $user_id");
                }
            }
            $success_message = "Cart updated successfully.";
        }
    } elseif (isset($_POST['remove_item'])) {
        $cart_id = (int) $_POST['cart_id'];
        if ($cart_id > 0) {
            $conn->query("DELETE FROM cart WHERE id = $cart_id AND user_id = $user_id");
            $success_message = "Item removed successfully.";
        }
    } elseif (isset($_POST['checkout'])) {
        try {
            $conn->begin_transaction();

            // Calculate total
            $cart_items = $conn->query("
                SELECT c.id, c.medicine_id, c.quantity, m.price, m.stock, m.name
                FROM cart c
                JOIN medicines m ON c.medicine_id = m.id
                WHERE c.user_id = $user_id
            ")->fetch_all(MYSQLI_ASSOC);

            if (empty($cart_items)) {
                throw new Exception("Your cart is empty");
            }

            $subtotal = 0;
            foreach ($cart_items as $item) {
                $subtotal += $item['price'] * $item['quantity'];
            }
            $total = $subtotal + 5.00; // Delivery fee

            // Create order
            $conn->query("
                INSERT INTO orders (user_id, total_amount, status) 
                VALUES ($user_id, $total, 'pending')
            ");
            $order_id = $conn->insert_id;

            // Add order items and update stock
            foreach ($cart_items as $item) {
                if ($item['quantity'] > $item['stock']) {
                    throw new Exception("Not enough stock for {$item['name']}");
                }

                $conn->query("
                    INSERT INTO order_items (order_id, medicine_id, quantity, price)
                    VALUES ($order_id, {$item['medicine_id']}, {$item['quantity']}, {$item['price']})
                ");

                $conn->query("
                    UPDATE medicines 
                    SET stock = stock - {$item['quantity']} 
                    WHERE id = {$item['medicine_id']}
                ");
            }

            // Clear cart
            $conn->query("DELETE FROM cart WHERE user_id = $user_id");

            $conn->commit();

            header("Location: process_payment.php?type=pharmacy&id=$order_id");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Checkout failed: " . $e->getMessage();
        }
    }
}

// Get cart data
$cart_items = $conn->query("
    SELECT c.id, c.medicine_id, c.quantity, m.name, m.price, m.image 
    FROM cart c
    JOIN medicines m ON c.medicine_id = m.id
    WHERE c.user_id = $user_id
")->fetch_all(MYSQLI_ASSOC);

$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$delivery_fee = 5.00;
$total = $subtotal + $delivery_fee;

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
                                                    <td class="text-end">UGX <?= number_format($item['price'] ?? 0, 2) ?></td>
                                                    <td class="text-center">
                                                        <input type="number" name="quantity[<?= $item['id'] ?>]"
                                                            value="<?= $item['quantity'] ?>" min="1" max="10"
                                                            class="form-control form-control-sm mx-auto" style="width: 70px;">
                                                    </td>
                                                    <td class="text-end">
                                                        UGX <?= number_format($item['price'] * $item['quantity'], 2) ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <form method="POST" action="cart.php" style="display: inline;">
                                                            <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                                            <button type="submit" name="remove_item"
                                                                class="btn btn-sm btn-outline-danger"
                                                                onclick="return confirm('Remove this item?')">
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
                                        <i class="fas fa-chevron-left me-2"></i> Continue Shopping
                                    </a>
                                    <button type="submit" name="update_cart" class="btn btn-secondary">
                                        <i class="fas fa-sync-alt me-2"></i> Update Cart
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
                            <span>UGX <?= number_format($subtotal, 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Delivery Fee:</span>
                            <span>UGX <?= number_format($delivery_fee, 2) ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between fw-bold h5 mb-4">
                            <span>Total:</span>
                            <span>UGX <?= number_format($total, 2) ?></span>
                        </div>

                        <?php if (!empty($cart_items)): ?>
                            <form method="POST" action="cart.php">
                                <button type="submit" name="checkout" class="btn btn-success w-100 mt-3 py-2">
                                    <i class="fas fa-lock me-2"></i> Proceed to Checkout
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>