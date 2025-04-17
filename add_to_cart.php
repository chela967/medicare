<?php
// add_to_cart.php - Updated to return full cart

// Strict session configuration
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_httponly' => true,
    'use_strict_mode' => true,
    'cookie_samesite' => 'Lax'
]);

// Include configuration and functions
require_once 'config.php'; // Contains $conn
require_once 'functions.php'; // MUST contain getCart() function now

// Set header to output JSON
header('Content-Type: application/json');

// --- Input Validation & Security ---
$response = ['success' => false, 'message' => 'An error occurred.']; // Default response

// 1. Check if user is logged in
if (!isset($_SESSION['user']['id'])) {
    $response['message'] = 'User not logged in.';
    echo json_encode($response);
    exit();
}
$user_id = (int) $_SESSION['user']['id'];

// 2. Check CSRF Token (If implemented)
// $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
// if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
//      $response['message'] = 'Invalid request (CSRF token mismatch).';
//      echo json_encode($response);
//      exit();
// }

// 3. Get data sent from JavaScript
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['medicine_id']) || !isset($input['quantity'])) {
    $response['message'] = 'Invalid input data.';
    echo json_encode($response);
    exit();
}

$medicine_id = (int) $input['medicine_id'];
$quantity = (int) $input['quantity'];

if ($medicine_id <= 0 || $quantity <= 0) {
    $response['message'] = 'Invalid medicine ID or quantity.';
    echo json_encode($response);
    exit();
}

// --- Database Interaction ---
try {
    // Ensure getCart function exists (needed for response)
    if (!function_exists('getCart')) {
        throw new Exception("Essential function 'getCart' is missing.");
    }

    // Check medicine stock and details
    $stmt_check = $conn->prepare("SELECT stock, price, name FROM medicines WHERE id = ? AND stock >= ?");
    if (!$stmt_check)
        throw new Exception("Prepare failed (check): " . $conn->error);
    $stmt_check->bind_param('ii', $medicine_id, $quantity);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $medicine_data = $result_check->fetch_assoc();
    $stmt_check->close();

    if ($medicine_data) {
        // Medicine exists and has enough stock for the requested quantity initially

        // Check if item is already in cart
        $stmt_cart = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND medicine_id = ?");
        if (!$stmt_cart)
            throw new Exception("Prepare failed (cart check): " . $conn->error);
        $stmt_cart->bind_param('ii', $user_id, $medicine_id);
        $stmt_cart->execute();
        $result_cart = $stmt_cart->get_result();
        $cart_item = $result_cart->fetch_assoc();
        $stmt_cart->close();

        $operation_success = false;

        if ($cart_item) {
            // Item already in cart - Update quantity
            $new_quantity = $cart_item['quantity'] + $quantity;

            // Re-check stock for *total* quantity needed
            if ($new_quantity <= $medicine_data['stock']) {
                $stmt_update = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                if (!$stmt_update)
                    throw new Exception("Prepare failed (update): " . $conn->error);
                $stmt_update->bind_param('ii', $new_quantity, $cart_item['id']);
                $operation_success = $stmt_update->execute();
                $stmt_update->close();
                if (!$operation_success)
                    $response['message'] = 'Failed to update cart quantity.';

            } else {
                $response['message'] = 'Not enough stock to increase quantity to ' . $new_quantity . '.';
                $operation_success = false; // Explicitly set failure
            }

        } else {
            // Item not in cart - Insert new item (already checked stock for initial quantity)
            $stmt_insert = $conn->prepare("INSERT INTO cart (user_id, medicine_id, quantity) VALUES (?, ?, ?)");
            if (!$stmt_insert)
                throw new Exception("Prepare failed (insert): " . $conn->error);
            $stmt_insert->bind_param('iii', $user_id, $medicine_id, $quantity);
            $operation_success = $stmt_insert->execute();
            $stmt_insert->close();
            if (!$operation_success)
                $response['message'] = 'Failed to add item to cart.';
        }

        // *** If operation succeeded, fetch the updated cart ***
        if ($operation_success) {
            $updated_cart_items = getCart($user_id); // Call the existing function
            if (!is_array($updated_cart_items))
                $updated_cart_items = []; // Ensure it's array

            $response = [
                'success' => true,
                'message' => $cart_item ? 'Cart updated.' : 'Item added to cart.',
                'cart_items' => $updated_cart_items, // <-- Send the full cart data
                'cart_count' => count($updated_cart_items) // Send count as well
            ];
        }
        // If operation failed, $response['message'] should have been set already

    } else {
        // Medicine not found or initial quantity requested exceeds stock
        $response['message'] = 'Medicine not found or not enough stock.';
    }

} catch (Exception $e) {
    error_log("Add to Cart Error: " . $e->getMessage());
    $response['message'] = 'A database error occurred. Please try again.'; // Generic message
}

// --- Send JSON Response ---
echo json_encode($response);
exit();
?>