<?php
// FOR PRESENTATION DEMO - FAKES MTN MOMO SUCCESS
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require 'config.php'; // Ensure this path is correct and $conn is initialized

// 1. SECURITY AND SESSION CHECK
if (!isset($_SESSION['user']['id'])) {
    header('Content-Type: application/json');
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized access']));
}

$user_id = (int) $_SESSION['user']['id'];

// Initialize $item and $payment_methods for GET request context
$item = null;
$payment_methods = [];
$payment_type = null; // Initialize payment_type
$related_id = null; // Initialize related_id


// 2. PARAMETER VALIDATION & DATA FETCHING (for GET request to load the page)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['type']) || !isset($_GET['id'])) {
        die("<div class='alert alert-danger container mt-5'>Error: Required parameters (type and id) missing in URL. Example: payment_process.php?type=appointment&id=123</div>");
    }

    $valid_types = ['appointment', 'pharmacy'];
    $payment_type = in_array($_GET['type'], $valid_types) ? $_GET['type'] : null; // Assign here
    $related_id = (int) $_GET['id']; // Assign here

    if (!$payment_type || $related_id < 1) {
        die("<div class='alert alert-danger container mt-5'>Error: Invalid parameters in URL.</div>");
    }

    try {
        if ($payment_type === 'appointment') {
            $stmt_item = $conn->prepare("
                SELECT a.*, d.consultation_fee AS amount, u.name AS doctor_name
                FROM appointments a
                JOIN doctors d ON a.doctor_id = d.id
                JOIN users u ON d.user_id = u.id
                WHERE a.id = ? AND a.patient_id = ?
            ");
            if (!$stmt_item)
                throw new Exception("DB Prepare Error (Appointment Item): " . $conn->error);
            $stmt_item->bind_param("ii", $related_id, $user_id);
            $stmt_item->execute();
            $result_item = $stmt_item->get_result();
            $item = $result_item->fetch_assoc();
            $stmt_item->close();

            if (!$item)
                throw new Exception("Appointment not found, may have been paid, or access denied.");
            // Re-check payment status robustly
            $check_paid_stmt = $conn->prepare("SELECT payment_status FROM appointments WHERE id = ?");
            $check_paid_stmt->bind_param("i", $related_id);
            $check_paid_stmt->execute();
            $paid_status_res = $check_paid_stmt->get_result()->fetch_assoc();
            $check_paid_stmt->close();
            if ($paid_status_res && $paid_status_res['payment_status'] === 'paid') {
                throw new Exception("This appointment (ID: $related_id) is already paid.");
            }


        } else { // Pharmacy order
            $stmt_item = $conn->prepare("
                SELECT o.*, SUM(oi.price * oi.quantity) AS amount,
                       COUNT(oi.id) AS item_count, GROUP_CONCAT(m.name SEPARATOR ', ') AS items
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN medicines m ON oi.medicine_id = m.id
                WHERE o.id = ? AND o.user_id = ?
                GROUP BY o.id
            ");
            if (!$stmt_item)
                throw new Exception("DB Prepare Error (Order Item): " . $conn->error);
            $stmt_item->bind_param("ii", $related_id, $user_id);
            $stmt_item->execute();
            $result_item = $stmt_item->get_result();
            $item = $result_item->fetch_assoc();
            $stmt_item->close();

            if (!$item || !isset($item['amount']))
                throw new Exception("Order not found or is empty.");
            // Re-check status robustly
            $check_status_stmt = $conn->prepare("SELECT status FROM orders WHERE id = ?");
            $check_status_stmt->bind_param("i", $related_id);
            $check_status_stmt->execute();
            $order_status_res = $check_status_stmt->get_result()->fetch_assoc();
            $check_status_stmt->close();
            if ($order_status_res && $order_status_res['status'] !== 'pending') {
                throw new Exception("This order (ID: $related_id) is not awaiting payment or is already being processed (Status: " . $order_status_res['status'] . ").");
            }
        }
        if (!$item || !isset($item['amount'])) { // Final check after fetching
            throw new Exception("Could not load item details or item is not eligible for payment.");
        }
    } catch (Exception $e) {
        error_log("Error loading item details for GET: " . $e->getMessage());
        die("<div class='alert alert-danger container mt-5'>Error loading item details: " . htmlspecialchars($e->getMessage()) . "</div>");
    }

    $payment_methods_query = $conn->query("
        SELECT id, name, icon
        FROM payment_methods
        WHERE is_active = 1 AND id IN (5, 6) /* Only MTN (id=5) and Cash (id=6) */
        ORDER BY FIELD(id, 5, 6) /* Ensure MTN is first if available */
    ");
    if (!$payment_methods_query) {
        error_log("DB Query Error (Payment Methods): " . $conn->error);
        die("<div class='alert alert-danger container mt-5'>Error: Could not retrieve payment methods.</div>");
    }
    $payment_methods = $payment_methods_query->fetch_all(MYSQLI_ASSOC);
    if (empty($payment_methods)) {
        die("<div class='alert alert-warning container mt-5'>No payment methods are currently available. Please contact support.</div>");
    }
}


// ===== (DEMO FAKE) MTN MOMO HELPER FUNCTIONS =====
function getMomoToken()
{ /* This function is not strictly needed by faked MoMo calls below */
    error_log("DEMO MODE: getMomoToken() called, but faked functions will bypass real token needs.");
    return "DEMO_ACCESS_TOKEN_" . uniqid();
}

function initiateMomoPayment($phone, $amount, $externalId)
{
    error_log("DEMO MODE: Faking MoMo payment initiation success for phone: $phone, amount: $amount, externalId: $externalId");
    return 'DEMO_REF_' . strtoupper(uniqid()); // Return a unique-looking fake reference ID
}

function checkMomoPaymentStatus($transactionId)
{ // $transactionId here is the faked $momo_reference_id
    if (strpos($transactionId, 'DEMO_REF_') === 0) {
        error_log("DEMO MODE: Faking MoMo payment status check for $transactionId as SUCCESSFUL.");
        return [
            "status" => "SUCCESSFUL",
            "financialTransactionId" => "DEMO_FTXN_" . strtoupper(substr(md5($transactionId), 0, 10)),
            "externalId" => "MED-DEMO-" . strtoupper(substr(md5($transactionId), 0, 6)),
            "amount" => "500", // Example amount, adjust if needed for demo consistency
            "currency" => "UGX", // Presenting with UGX
            "payer" => ["partyIdType" => "MSISDN", "partyId" => "256772123456"],
            "payerMessage" => "payment processed successfully.",
            "payeeNote" => "Thank you for your payment ."
        ];
    }
    error_log("DEMO MODE: Real status check attempted for non-demo ID: $transactionId - this should not happen in pure demo flow.");
    return ["status" => "PENDING", "reason" => "Demo mode: Only  references are processed."];
}

// 5. HANDLE PAYMENT SUBMISSION (AJAX POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // These should be passed in the AJAX action URL or as hidden form fields if not in URL
    $payment_type_post = isset($_GET['type']) ? (in_array($_GET['type'], ['appointment', 'pharmacy']) ? $_GET['type'] : null) : null;
    $related_id_post = isset($_GET['id']) ? (int) $_GET['id'] : null;

    if (!$payment_type_post || !$related_id_post) {
        die(json_encode(['status' => 'error', 'message' => 'Critical: Type/ID parameters missing in POST request.']));
    }

    try {
        // Re-fetch item amount to ensure it's current and hasn't been paid.
        if ($payment_type_post === 'appointment') {
            $stmt_item_val = $conn->prepare("SELECT d.consultation_fee AS amount, a.payment_status FROM appointments a JOIN doctors d ON a.doctor_id = d.id WHERE a.id = ? AND a.patient_id = ?");
        } else { // pharmacy
            $stmt_item_val = $conn->prepare("SELECT SUM(oi.price * oi.quantity) AS amount, o.status AS payment_status FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE o.id = ? AND o.user_id = ? GROUP BY o.id");
        }
        if (!$stmt_item_val)
            throw new Exception("DB Prepare Error (Item Validation): " . $conn->error);
        $stmt_item_val->bind_param("ii", $related_id_post, $user_id);
        $stmt_item_val->execute();
        $result_item_val = $stmt_item_val->get_result();
        $item_validation = $result_item_val->fetch_assoc();
        $stmt_item_val->close();

        if (!$item_validation || !isset($item_validation['amount'])) {
            throw new Exception("Item for payment not found or invalid.");
        }
        $item_current_payment_status_field = ($payment_type_post === 'appointment') ? 'payment_status' : 'status';
        if ($item_validation[$item_current_payment_status_field] === 'paid' || ($payment_type_post === 'pharmacy' && $item_validation[$item_current_payment_status_field] !== 'pending')) {
            throw new Exception("This " . $payment_type_post . " has already been paid or is not eligible for payment.");
        }
        $item_amount_post = $item_validation['amount'];

        // Fetch selected payment method details
        $pm_stmt = $conn->prepare("SELECT id, name FROM payment_methods WHERE id = ? AND is_active = 1");
        if (!$pm_stmt)
            throw new Exception("DB Prepare Error (Payment Method): " . $conn->error);
        $posted_pm_id = (int) $_POST['payment_method_id'];
        $pm_stmt->bind_param("i", $posted_pm_id);
        $pm_stmt->execute();
        $selected_method = $pm_stmt->get_result()->fetch_assoc();
        $pm_stmt->close();

        if (!$selected_method)
            throw new Exception("Invalid or inactive payment method selected.");

        $system_transaction_id = 'MED-' . time() . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        $phone_number_posted = isset($_POST['phone_number']) ? trim($_POST['phone_number']) : null;
        $momo_reference_id_for_response = null;

        $conn->begin_transaction();

        if ($selected_method['id'] == 5) { // MTN MoMo (ID 5 from your SQL dump)
            if (empty($phone_number_posted) || !preg_match('/^256\d{9}$/', $phone_number_posted)) {
                throw new Exception("Valid MTN MoMo number required for payment(format: 256XXXXXXXXX).");
            }

            $momo_reference_id = initiateMomoPayment($phone_number_posted, $item_amount_post, $system_transaction_id);
            $momo_reference_id_for_response = $momo_reference_id;

            // DEMO SQL: Insert into 'payments' - omits MoMo-specific columns not in your SQL dump.
            // For a real system, ensure your 'payments' table has momo_reference_id, financial_transaction_id, momo_response, completed_at columns.
            $stmt_insert = $conn->prepare("
                INSERT INTO payments (user_id, payment_type, related_id, amount, payment_method, transaction_id, phone_number, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            if (!$stmt_insert)
                throw new Exception("DB Prepare Error (Insert Payment): " . $conn->error);
            $stmt_insert->bind_param("isidsss", $user_id, $payment_type_post, $related_id_post, $item_amount_post, $selected_method['name'], $system_transaction_id, $phone_number_posted);
            $stmt_insert->execute();
            $payment_id = $conn->insert_id;
            if (!$payment_id)
                throw new Exception("DB Error: Failed to create payment record.");
            $stmt_insert->close();

            $max_retries = 1; // Demo mode, 1 try is enough for faked success
            $payment_status_from_momo = null;

            for ($i = 0; $i < $max_retries; $i++) {
                if ($i > 0)
                    sleep(1); // Minimal delay for demo
                $momo_api_response_data = checkMomoPaymentStatus($momo_reference_id); // Uses faked version

                if (isset($momo_api_response_data['status'])) {
                    $payment_status_from_momo = $momo_api_response_data['status'];
                    if ($payment_status_from_momo === 'SUCCESSFUL')
                        break;
                }
            }

            if ($payment_status_from_momo !== 'SUCCESSFUL') {
                throw new Exception("DEMO: MoMo payment faked as not successful. Status: " . ($payment_status_from_momo ?? 'UNKNOWN'));
            }

            // DEMO SQL: Update 'payments' - simplified to only update status.
            // For a real system, you'd update with financial_transaction_id, momo_response, completed_at.
            $stmt_update = $conn->prepare("UPDATE payments SET status = 'completed' WHERE id = ?");
            if (!$stmt_update)
                throw new Exception("DB Prepare Error (Update Payment): " . $conn->error);
            $stmt_update->bind_param("i", $payment_id);
            $stmt_update->execute();
            $stmt_update->close();

        } else if ($selected_method['id'] == 6) { // Cash (ID 6)
            $stmt_insert_cash = $conn->prepare("
                INSERT INTO payments (user_id, payment_type, related_id, amount, payment_method, transaction_id, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW())
            "); // phone_number can be omitted for cash if not applicable/collected
            if (!$stmt_insert_cash)
                throw new Exception("DB Prepare Error (Insert Cash Payment): " . $conn->error);
            $stmt_insert_cash->bind_param("isidss", $user_id, $payment_type_post, $related_id_post, $item_amount_post, $selected_method['name'], $system_transaction_id);
            $stmt_insert_cash->execute();
            if (!$conn->insert_id)
                throw new Exception("DB Error: Failed to create cash payment record.");
            $stmt_insert_cash->close();
        } else {
            throw new Exception("Unsupported payment method ID encountered.");
        }

        // Update the original item (appointment or order)
        if ($payment_type_post === 'appointment') {
            $stmt_update_item = $conn->prepare("UPDATE appointments SET payment_status='paid' WHERE id = ? AND patient_id = ?");
        } else { // pharmacy
            $stmt_update_item = $conn->prepare("UPDATE orders SET status='processing' WHERE id = ? AND user_id = ?");
            // Consider other pharmacy updates like stock, cart clearing here
        }
        if (!$stmt_update_item)
            throw new Exception("DB Prepare Error (Update Item): " . $conn->error);
        $stmt_update_item->bind_param("ii", $related_id_post, $user_id);
        $stmt_update_item->execute();
        $stmt_update_item->close();

        $conn->commit();

        echo json_encode([
            'status' => 'success',
            'transaction_id' => $system_transaction_id,
            'momo_reference_id' => $momo_reference_id_for_response,
            'message' => 'Payment successfully processed (DEMO MODE).',
            'redirect_url' => "payment_success.php?type=$payment_type_post&id=$related_id_post&txn=$system_transaction_id"
        ]);
        exit();

    } catch (Exception $e) {
        if ($conn && $conn->connect_errno === 0 && method_exists($conn, 'rollback')) { // Check if $conn is a valid mysqli object and connection is open
            $conn->rollback();
        }
        error_log("DEMO Payment Processing Exception: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
        echo json_encode(['status' => 'error', 'message' => "DEMO Error: " . htmlspecialchars($e->getMessage())]);
        exit();
    }
}


// This check should be for GET request context after all potential `die()` calls.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (empty($item) || empty($payment_methods))) {
    // This can happen if parameters were valid but item fetch failed or no payment methods.
    // The try-catch for item fetching above should handle specific DB errors.
    // This is a final fallback.
    die("<div class='alert alert-danger container mt-5'>Error: Essential payment information could not be loaded. Please ensure the item ID is correct and payment methods are configured.</div>");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Payment </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .payment-container {
            max-width: 600px;
            margin: 2rem auto;
        }

        .payment-header {
            background-color: #0d6efd;
            color: white;
        }

        .summary-card {
            border-left: 4px solid #0d6efd;
        }

        .payment-method {
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid #dee2e6;
            border-radius: .375rem;
        }

        .payment-method:hover {
            background-color: #f8f9fa;
        }

        .payment-method.selected {
            background-color: #e7f1ff;
            border-color: #0d6efd;
        }

        .method-icon {
            font-size: 1.5rem;
            width: 40px;
        }

        #paymentStatusDisplay {
            display: none;
            /* Managed by JS */
        }

        .status-processing {
            color: #ffc107;
        }

        .status-success {
            color: #198754;
        }

        .status-failed {
            color: #dc3545;
        }

        .form-check-input.payment-method-radio {
            opacity: 0.5;
            position: relative;
            z-index: 1;
            margin-top: 0.3em;
            margin-left: 5px;
        }

        .payment-method .form-check-label {
            width: 100%;
            display: flex;
            align-items: center;
            padding: 0.75rem;
        }

        /* Ensure label is clickable */
    </style>
</head>

<body>
    <div class="container payment-container">
        <div class="card shadow">
            <div class="card-header payment-header">
                <h4 class="mb-0"><i class="fas fa-credit-card me-2"></i> Complete Payment </h4>
            </div>

            <div class="card-body">
                <div id="paymentStatusDisplay" class="alert mb-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-spinner fa-spin me-2 status-icon"></i>
                        <span class="status-message fw-bold"></span>
                    </div>
                    <div class="mt-2 status-details small"></div>
                </div>

                <?php if ($item && !empty($payment_methods)): // Only show form if item and methods loaded ?>
                    <div class="card mb-4 summary-card">
                        <div class="card-body">
                            <h5><?= htmlspecialchars($payment_type === 'appointment' ? 'Appointment Details' : 'Order Summary') ?>
                            </h5>
                            <hr>
                            <?php if ($payment_type === 'appointment'): ?>
                                <p><strong>Doctor:</strong> Dr. <?= htmlspecialchars($item['doctor_name']) ?></p>
                                <p><strong>Date:</strong>
                                    <?= htmlspecialchars(date('F j, Y', strtotime($item['appointment_date']))) ?></p>
                                <p><strong>Time:</strong>
                                    <?= htmlspecialchars(date('g:i A', strtotime($item['appointment_time']))) ?></p>
                            <?php else: ?>
                                <p><strong>Order #:</strong> ORD-<?= htmlspecialchars($related_id) ?></p>
                                <p><strong>Items:</strong> <?= htmlspecialchars($item['item_count']) ?>
                                    (<?= htmlspecialchars($item['items'] ?? 'N/A') ?>)</p>
                            <?php endif; ?>
                            <hr>
                            <h4 class="text-end">
                                <strong>Total Amount:</strong>
                                UGX <?= htmlspecialchars(number_format($item['amount'] ?? 0, 0)) ?>
                            </h4>
                        </div>
                    </div>

                    <form id="paymentForm">
                        <div class="mb-4">
                            <h5 class="mb-3"><i class="fas fa-wallet me-2"></i> Select Payment Method</h5>
                            <?php foreach ($payment_methods as $index_html => $method_html): ?>
                                <?php
                                $is_mtn_html = ($method_html['id'] == 5); // Assuming ID 5 is MTN
                                $is_cash_html = ($method_html['id'] == 6); // Assuming ID 6 is Cash
                                $method_label_id_html = "method_" . htmlspecialchars($method_html['id']);
                                $is_first_method_mtn = ($index_html === 0 && $is_mtn_html);
                                ?>
                                <div class="payment-method card mb-2 p-0">
                                    <label class="form-check-label" for="<?= $method_label_id_html ?>">
                                        <input class="form-check-input payment-method-radio" type="radio"
                                            name="payment_method_id" id="<?= $method_label_id_html ?>"
                                            value="<?= htmlspecialchars($method_html['id']) ?>" required
                                            <?= ($is_first_method_mtn || ($index_html === 0 && !$is_first_method_mtn)) ? 'checked' : '' ?>>
                                        <span
                                            class="method-icon <?= $is_mtn_html ? 'text-warning' : ($is_cash_html ? 'text-success' : 'text-primary') ?> me-3">
                                            <i
                                                class="fas <?= $is_mtn_html ? 'fa-mobile-alt' : ($is_cash_html ? 'fa-money-bill-wave' : 'fa-credit-card') ?>"></i>
                                        </span>
                                        <div>
                                            <strong><?= htmlspecialchars($method_html['name']) ?></strong>
                                            <div class="text-muted small">
                                                <?= $is_mtn_html ? 'Pay instantly via MTN Mobile Money (DEMO)' : ($is_cash_html ? 'Pay cash upon delivery/service' : 'Select this payment method') ?>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div id="mobileMoneySection" class="mb-4" style="display: none;">
                            <div class="card p-3">
                                <label for="phoneNumber" class="form-label fw-bold">
                                    <i class="fas fa-mobile-alt me-2"></i> MTN Mobile Money Number
                                </label>
                                <input type="tel" class="form-control" id="phoneNumber" name="phone_number"
                                    placeholder="256772123456" pattern="^256\d{9}$">
                                <small class="text-muted">Format: 256 followed by 9 digits (e.g., 256772123456 )</small>
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-success btn-lg py-3" id="submitBtn">
                                <i class="fas fa-lock me-2"></i> Complete Payment 
                            </button>
                            <a href="<?= htmlspecialchars($payment_type === 'appointment' ? 'appointments.php' : 'cart.php') ?>"
                                class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Cancel Payment
                            </a>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info">Payment form could not be displayed because item details or payment
                        methods are missing.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const paymentMethodRadios = document.querySelectorAll('.payment-method-radio');
            const mobileMoneySection = document.getElementById('mobileMoneySection');
            const phoneNumberInput = document.getElementById('phoneNumber');
            const paymentForm = document.getElementById('paymentForm');
            const submitBtn = document.getElementById('submitBtn');

            const statusAlert = document.getElementById('paymentStatusDisplay');
            const statusIcon = statusAlert.querySelector('.status-icon');
            const statusMessage = statusAlert.querySelector('.status-message');
            const statusDetails = statusAlert.querySelector('.status-details');

            function toggleMobileMoneyField() {
                const checkedRadio = document.querySelector('.payment-method-radio:checked');
                let isMobileMoneySelected = false;
                if (checkedRadio && checkedRadio.value == '5') { // Assuming 5 is MTN MoMo ID from your DB
                    isMobileMoneySelected = true;
                }

                if (mobileMoneySection && phoneNumberInput) { // Ensure elements exist
                    if (isMobileMoneySelected) {
                        mobileMoneySection.style.display = 'block';
                        phoneNumberInput.required = true;
                    } else {
                        mobileMoneySection.style.display = 'none';
                        phoneNumberInput.required = false;
                    }
                }
            }

            paymentMethodRadios.forEach(radio => {
                radio.addEventListener('change', function () {
                    document.querySelectorAll('.payment-method').forEach(el => el.classList.remove('selected'));
                    if (this.checked) {
                        const parentCard = this.closest('.payment-method');
                        if (parentCard) parentCard.classList.add('selected');
                    }
                    toggleMobileMoneyField();
                });
            });

            const initiallyCheckedRadio = document.querySelector('.payment-method-radio:checked');
            if (initiallyCheckedRadio) {
                const parentCard = initiallyCheckedRadio.closest('.payment-method');
                if (parentCard) parentCard.classList.add('selected');
            }
            toggleMobileMoneyField();


            if (paymentForm) {
                paymentForm.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing Demo...';
                    }

                    if (statusAlert && statusIcon && statusMessage && statusDetails) {
                        statusAlert.style.display = 'block';
                        statusAlert.className = 'alert alert-warning mb-4';
                        statusIcon.className = 'fas fa-spinner fa-spin me-2 status-icon status-processing';
                        statusMessage.textContent = 'Processing  payment...';
                        statusDetails.innerHTML = 'Please wait for faked confirmation.';
                    }

                    try {
                        const formData = new FormData(paymentForm);
                        const actionUrl = '<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?type=<?= htmlspecialchars($payment_type ?? '') ?>&id=<?= htmlspecialchars($related_id ?? '') ?>';

                        const response = await fetch(actionUrl, {
                            method: 'POST',
                            body: formData,
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });

                        if (!response.ok) {
                            const errorText = await response.text();
                            throw new Error(`HTTP error! Status: ${response.status} - ${response.statusText}. Response: ${errorText}`);
                        }

                        const data = await response.json();

                        if (data.status === 'success') {
                            if (statusAlert && statusIcon && statusMessage && statusDetails) {
                                statusAlert.className = 'alert alert-success mb-4';
                                statusIcon.className = 'fas fa-check-circle me-2 status-icon status-success';
                                statusMessage.textContent = 'Payment Successful!';
                                statusDetails.innerHTML = `Txn ID: ${data.transaction_id || 'N/A'}. ${data.message || ''} Redirecting...`;
                            }

                            setTimeout(() => {
                                if (data.redirect_url) window.location.href = data.redirect_url;
                            }, 2500);
                        } else {
                            if (statusAlert && statusIcon && statusMessage && statusDetails) {
                                statusAlert.className = 'alert alert-danger mb-4';
                                statusIcon.className = 'fas fa-times-circle me-2 status-icon status-failed';
                                statusMessage.textContent = 'DEMO Payment Failed';
                                statusDetails.innerHTML = data.message || 'An unexpected error occurred in demo.';
                            }
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = '<i class="fas fa-lock me-2"></i> Complete Payment ';
                            }
                        }
                    } catch (error) {
                        if (statusAlert && statusIcon && statusMessage && statusDetails) {
                            statusAlert.className = 'alert alert-danger mb-4';
                            statusIcon.className = 'fas fa-times-circle me-2 status-icon status-failed';
                            statusMessage.textContent = 'DEMO Network/Script Error';
                            statusDetails.innerHTML = String(error.message || 'Please check console for details.');
                        }
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fas fa-lock me-2"></i> Complete Payment ';
                        }
                        console.error('Payment Form Submission Error:', error);
                    }
                });
            }
        });
    </script>
</body>

</html>