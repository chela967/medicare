<?php
// epharmacy.php - MySQLi Version

// Strict session configuration BEFORE any output
session_start([
    'cookie_lifetime' => 86400, // 1 day
    'cookie_secure' => isset($_SERVER['HTTPS']), // Use true if HTTPS is enabled
    'cookie_httponly' => true, // Prevent JS access to session cookie
    'use_strict_mode' => true, // Prevent session fixation
    'cookie_samesite' => 'Lax' // CSRF protection
]);

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
// Consider adding Content-Security-Policy header as well for more security

// Start output buffering
ob_start();

// Include configuration and functions first
// Ensure these files exist and are correctly set up
require_once 'config.php'; // Should define $conn (MySQLi connection) and BASE_URL
require_once 'functions.php'; // Should define getMedicines(), getCategories(), getCart(), getUserPrescriptions(), checkUserRole(), generateCSRFToken(), shortenText() if used elsewhere

// Redirect if not logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    // Redirect to login page, passing the current page as a redirect target
    header("Location: auth.php?redirect=epharmacy");
    exit();
}

// Verify user has an ID (redundant check, but safe)
if (!isset($_SESSION['user']['id'])) {
    // Maybe log this error?
    die("User ID not found in session. Please login again.");
}

$user_id = (int) $_SESSION['user']['id']; // Ensure user ID is an integer

// Set page title
$page_title = "E-Pharmacy - Medicare";

// --- Database Functions (Should be in functions.php) ---
// Assuming getMedicines, getCategories, getCart, getUserPrescriptions are defined in functions.php

// --- Helper Functions (Should be in functions.php) ---
// Assuming checkUserRole, generateCSRFToken, shortenText are defined in functions.php

// --- Initialize CSRF token ---
if (empty($_SESSION['csrf_token'])) {
    if (function_exists('generateCSRFToken')) {
        $_SESSION['csrf_token'] = generateCSRFToken();
    } else {
        // Fallback or error if function doesn't exist
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Basic fallback
        error_log("Warning: generateCSRFToken function not found in functions.php");
    }
}
$csrf_token = $_SESSION['csrf_token']; // Make it available

// --- Initialize user data ---
// Sanitize user data retrieved from session
$user = [
    'id' => $user_id,
    'name' => isset($_SESSION['user']['name']) ? htmlspecialchars($_SESSION['user']['name'], ENT_QUOTES, 'UTF-8') : 'Guest',
    'email' => isset($_SESSION['user']['email']) ? filter_var($_SESSION['user']['email'], FILTER_SANITIZE_EMAIL) : '',
    'role' => isset($_SESSION['user']['role']) ? htmlspecialchars($_SESSION['user']['role'], ENT_QUOTES, 'UTF-8') : 'patient'
];

// Check user roles (using function if available)
if (function_exists('checkUserRole')) {
    $is_admin = checkUserRole('admin');
    $is_pharmacist = checkUserRole('pharmacist');
} else {
    // Basic fallback check
    $is_admin = ($user['role'] === 'admin');
    $is_pharmacist = ($user['role'] === 'pharmacist');
    error_log("Warning: checkUserRole function not found in functions.php");
}


// --- Get query parameters ---
$category_id = isset($_GET['category']) && ctype_digit($_GET['category']) ? (int) $_GET['category'] : null; // Validate input
$search = isset($_GET['search']) ? trim(htmlspecialchars($_GET['search'], ENT_QUOTES, 'UTF-8')) : null; // Sanitize search

// --- Get data from database ---
$medicines = [];
$categories = [];
$cart = []; // Initialize as empty array
$prescriptions = [];
$cart_count = 0;
$no_medicines = true;

try {
    // Ensure functions exist before calling
    if (function_exists('getMedicines')) {
        $medicines = getMedicines($category_id, $search);
        $no_medicines = empty($medicines);
    } else {
        error_log("Error: getMedicines function not found in functions.php");
    }

    if (function_exists('getCategories')) {
        $categories = getCategories();
    } else {
        error_log("Error: getCategories function not found in functions.php");
    }

    if (function_exists('getCart')) {
        $cart = getCart($user['id']); // Populate the $cart variable
        // Ensure $cart is always an array even if getCart fails or returns null
        if (!is_array($cart)) {
            $cart = [];
            error_log("Warning: getCart() did not return an array for user_id: " . $user['id']);
        }
        $cart_count = count($cart);
    } else {
        error_log("Error: getCart function not found in functions.php");
    }

    if (function_exists('getUserPrescriptions')) {
        $prescriptions = getUserPrescriptions($user['id']);
        if (!is_array($prescriptions)) {
            $prescriptions = []; // Ensure it's an array
        }
    } else {
        error_log("Error: getUserPrescriptions function not found in functions.php");
    }

} catch (Exception $e) {
    // Log the detailed error securely
    error_log("Database error on epharmacy.php: " . $e->getMessage());
    // Display a generic error message to the user
    die("<div class='alert alert-danger'>Error loading pharmacy data. Please check logs or contact support.</div>");
}


// --- Include Header ---
// header.php should contain <!DOCTYPE html>, <html>, <head>, and start of <body>, navbar etc.
require_once 'header.php';
?>

<main class="container-fluid py-5 bg-light">
    <div class="row justify-content-center">
        <div class="col-xxl-10 col-12">
            <div class="e-pharmacy bg-white rounded-4 shadow-sm p-3 p-md-4">

                <div class="row mb-4 align-items-center">
                    <div class="col-md-8">
                        <h1 class="display-5 fw-bold text-primary">Medicare E-Pharmacy</h1>
                        <p class="lead text-muted">Quality medicines, delivered fast</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-outline-primary position-relative dropdown-toggle" type="button"
                                id="cartDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-shopping-cart"></i>
                                <span class="d-none d-sm-inline"> Cart</span>
                                <span
                                    class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger cart-badge">
                                    <?= $cart_count // Display count fetched earlier ?>
                                </span>
                            </button>

                            <?php

                            ?>
                            <ul class="dropdown-menu dropdown-menu-end p-2" style="min-width: 280px;"
                                aria-labelledby="cartDropdown">
                                <div id="miniCartContent">
                                    <?php if (empty($cart)): ?>
                                        <li class="dropdown-item text-muted py-2 text-center" id="miniCartEmptyMsg">Your
                                            cart is empty</li>
                                    <?php else: ?>
                                        <li class="dropdown-header fw-bold mb-1 px-2" id="miniCartHeader">Your Items</li>
                                        <div id="miniCartItemsContainer" style="max-height: 50vh; overflow-y: auto;">
                                            <?php
                                            $cart_subtotal = 0;
                                            foreach ($cart as $item):
                                                $item_name = $item['name'] ?? 'Unknown Item';
                                                $item_quantity = (int) ($item['quantity'] ?? 0);
                                                $item_price = (float) ($item['price'] ?? 0);
                                                $item_total = $item_price * $item_quantity;
                                                $cart_subtotal += $item_total;
                                                ?>
                                                <li>
                                                    <div class="d-flex justify-content-between align-items-center px-2 py-1">
                                                        <div class="me-3 text-truncate" style="max-width: 150px;">
                                                            <span
                                                                class="d-block text-truncate"><?= htmlspecialchars($item_name) ?></span>
                                                            <small class="text-muted">Qty: <?= $item_quantity ?></small>
                                                        </div>
                                                        <span class="text-nowrap">$<?= number_format($item_total, 2) ?></span>
                                                    </div>
                                                </li>
                                                <li class="dropdown-divider my-1 mx-2"></li>
                                            <?php endforeach; ?>
                                        </div>
                                        <li class="d-flex justify-content-between px-2 py-1 fw-bold bg-light"
                                            id="miniCartTotal">
                                            <span>Total:</span>
                                            <span id="miniCartTotalAmount">$<?= number_format($cart_subtotal, 2) ?></span>
                                        </li>
                                        <li class="mt-2 px-2" id="miniCartCheckoutBtn">
                                            <a href="cart.php" class="btn btn-primary w-100">
                                                <i class="fas fa-shopping-bag me-1"></i> View Cart & Checkout
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </div>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-3 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title">Categories</h5>
                                <ul class="nav flex-column">
                                    <li class="nav-item">
                                        <a class="nav-link<?= !$category_id ? ' active fw-bold' : '' ?>" href="?">All
                                            Medicines</a>
                                    </li>
                                    <?php if (!empty($categories)): ?>
                                        <?php foreach ($categories as $category): ?>
                                            <li class="nav-item">
                                                <a class="nav-link<?= (isset($category['id']) && $category_id === (int) $category['id']) ? ' active fw-bold' : '' ?>"
                                                    href="?category=<?= (int) ($category['id'] ?? 0) ?>">
                                                    <?= htmlspecialchars($category['name'] ?? 'Unnamed Category') ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-9">
                        <div class="card mb-4 shadow-sm">
                            <div class="card-body">
                                <form class="row g-3" method="GET" action="epharmacy.php">
                                    <div class="col-md-8">
                                        <div class="input-group">
                                            <input type="text" class="form-control" placeholder="Search medicines..."
                                                name="search"
                                                value="<?= htmlspecialchars($search ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                            <?php if ($category_id): // Keep category filter if set ?>
                                                <input type="hidden" name="category" value="<?= $category_id ?>">
                                            <?php endif; ?>
                                            <button class="btn btn-primary" type="submit">
                                                <i class="fas fa-search"></i> <span
                                                    class="d-none d-md-inline">Search</span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <select class="form-select" name="category" onchange="this.form.submit()">
                                            <option value="">All Categories</option>
                                            <?php if (!empty($categories)): ?>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?= (int) ($category['id'] ?? 0) ?>"
                                                        <?= (isset($category['id']) && $category_id === (int) $category['id']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($category['name'] ?? 'Unnamed Category') ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <input type="hidden" name="search" value="">
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="mb-5">
                            <h3 class="mb-3">
                                <?php
                                if ($search) {
                                    echo 'Search Results for "' . htmlspecialchars($search) . '"';
                                } elseif ($category_id && !empty($categories)) {
                                    $current_category_name = 'Selected Category'; // Default
                                    foreach ($categories as $cat) {
                                        if (isset($cat['id']) && (int) $cat['id'] === $category_id) {
                                            $current_category_name = htmlspecialchars($cat['name']);
                                            break;
                                        }
                                    }
                                    echo 'Medicines in ' . $current_category_name;
                                } else {
                                    echo 'Available Medicines';
                                }
                                ?>
                            </h3>

                            <?php if ($no_medicines): ?>
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <?php
                                        if ($search) {
                                            echo 'No medicines found matching your search criteria.';
                                        } elseif ($category_id) {
                                            echo 'No medicines currently available in this category.';
                                        } else {
                                            echo 'No medicines currently available.';
                                        }
                                        ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
                                    <?php foreach ($medicines as $medicine):
                                        // Ensure basic keys exist before using
                                        $med_id = (int) ($medicine['id'] ?? 0);
                                        $med_name = $medicine['name'] ?? 'Unknown Medicine';
                                        $med_image = $medicine['image'] ?? 'default.jpg';
                                        $med_manufacturer = $medicine['manufacturer'] ?? 'N/A';
                                        $med_price = (float) ($medicine['price'] ?? 0);
                                        ?>
                                        <div class="col">
                                            <div class="card h-100 shadow-sm">
                                                <img src="assets/medicines/<?= htmlspecialchars($med_image) ?>"
                                                    class="card-img-top p-3" style="max-height: 200px; object-fit: contain;"
                                                    alt="<?= htmlspecialchars($med_name) ?>"
                                                    onerror="this.onerror=null; this.src='assets/medicines/drug.jpg';"
                                                    loading="lazy">
                                                <div class="card-body d-flex flex-column">
                                                    <h5 class="card-title"><?= htmlspecialchars($med_name) ?></h5>
                                                    <p class="card-text text-muted mb-2">
                                                        <?= htmlspecialchars($med_manufacturer) ?>
                                                    </p>
                                                    <div class="mt-auto d-flex justify-content-between align-items-center">
                                                        <span class="fw-bold text-primary fs-5">
                                                            $<?= number_format($med_price, 2) ?>
                                                        </span>
                                                        <button class="btn btn-sm btn-outline-primary add-to-cart"
                                                            data-id="<?= $med_id ?>" <?= $med_id <= 0 ? 'disabled' : '' ?>>
                                                            <i class="fas fa-cart-plus"></i> Add
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="card mb-4 shadow-sm">
                            <div class="card-body">
                                <h3 class="mb-3">My Prescriptions</h3>
                                <?php if (empty($prescriptions)): ?>
                                    <div class="alert alert-info">No prescriptions found. <a
                                            href="upload_prescription.php">Upload one?</a></div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Doctor</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($prescriptions as $prescription):
                                                    $presc_id = (int) ($prescription['id'] ?? 0);
                                                    $presc_date = $prescription['created_at'] ?? null;
                                                    $presc_doctor = $prescription['doctor_name'] ?? 'N/A';
                                                    $presc_status = $prescription['status'] ?? 'pending';

                                                    // Determine badge color based on status
                                                    $status_color = 'warning'; // Default for pending
                                                    if ($presc_status === 'approved') {
                                                        $status_color = 'success';
                                                    } elseif ($presc_status === 'rejected') {
                                                        $status_color = 'danger';
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td><?= $presc_date ? date('M d, Y', strtotime($presc_date)) : 'N/A' ?>
                                                        </td>
                                                        <td><?= htmlspecialchars($presc_doctor) ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= $status_color ?>">
                                                                <?= ucfirst(htmlspecialchars($presc_status)) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <a href="view_prescription.php?id=<?= $presc_id ?>"
                                                                class="btn btn-sm btn-outline-info" title="View Prescription"
                                                                <?= $presc_id <= 0 ? 'disabled' : '' ?>>
                                                                <i class="fas fa-eye"></i> View
                                                            </a>
                                                            <?php if ($presc_status === 'approved'): ?>
                                                                <button class="btn btn-sm btn-outline-success ms-1 reorder-btn"
                                                                    title="Reorder Items" data-id="<?= $presc_id ?>" <?= $presc_id <= 0 ? 'disabled' : '' ?>>
                                                                    <i class="fas fa-redo"></i> Reorder
                                                                </button>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
// --- Include Footer ---
require_once 'footer.php'; // Should contain closing </body> and </html> tags

// --- JavaScript for AJAX actions ---
// Placed after footer include in case footer has JS dependencies like jQuery/Bootstrap JS
?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const csrfToken = '<?= htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8') ?>';

        // --- Function to Update Mini Cart Dropdown ---
        function updateMiniCart(cartItems) {
            const contentDiv = document.getElementById('miniCartContent');
            if (!contentDiv) return; // Exit if container not found

            let newHtml = '';
            let cartSubtotal = 0;

            if (!cartItems || cartItems.length === 0) {
                newHtml = '<li class="dropdown-item text-muted py-2 text-center" id="miniCartEmptyMsg">Your cart is empty</li>';
            } else {
                newHtml += '<li class="dropdown-header fw-bold mb-1 px-2" id="miniCartHeader">Your Items</li>';
                newHtml += '<div id="miniCartItemsContainer" style="max-height: 50vh; overflow-y: auto;">';

                cartItems.forEach(item => {
                    const itemName = item.name || 'Unknown Item';
                    const itemQuantity = parseInt(item.quantity || 0, 10);
                    const itemPrice = parseFloat(item.price || 0);
                    const itemTotal = itemPrice * itemQuantity;
                    cartSubtotal += itemTotal;

                    newHtml += `
                    <li>
                        <div class="d-flex justify-content-between align-items-center px-2 py-1">
                             <div class="me-3 text-truncate" style="max-width: 150px;">
                                 <span class="d-block text-truncate">${escapeHtml(itemName)}</span>
                                 <small class="text-muted">Qty: ${itemQuantity}</small>
                             </div>
                             <span class="text-nowrap">$${itemTotal.toFixed(2)}</span>
                        </div>
                    </li>
                    <li class="dropdown-divider my-1 mx-2"></li>`;
                });

                newHtml += '</div>'; // Close miniCartItemsContainer
                newHtml += `
                <li class="d-flex justify-content-between px-2 py-1 fw-bold bg-light" id="miniCartTotal">
                    <span>Total:</span>
                    <span id="miniCartTotalAmount">$${cartSubtotal.toFixed(2)}</span>
                </li>`;
                newHtml += `
                <li class="mt-2 px-2" id="miniCartCheckoutBtn">
                    <a href="cart.php" class="btn btn-primary w-100">
                        <i class="fas fa-shopping-bag me-1"></i> View Cart & Checkout
                    </a>
                </li>`;
            }

            contentDiv.innerHTML = newHtml; // Replace the content
        }

        // --- Helper to escape HTML entities ---
        function escapeHtml(unsafe) {
            if (unsafe === null || unsafe === undefined) return '';
            return unsafe
                .toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }


        // --- Add to Cart Functionality ---
        document.querySelectorAll('.add-to-cart').forEach(button => {
            button.addEventListener('click', async function (event) {
                event.preventDefault();

                const medicineId = this.dataset.id;
                if (!medicineId || medicineId <= 0) {
                    console.error('Add to Cart Error: Invalid medicine ID provided.');
                    alert('Cannot add item: Invalid medicine ID.');
                    return;
                }

                const btn = this;
                const originalHtml = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding...';

                try {
                    const response = await fetch('add_to_cart.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({
                            medicine_id: medicineId,
                            quantity: 1
                        })
                    });

                    const contentType = response.headers.get("content-type");
                    if (!response.ok || !contentType || !contentType.includes("application/json")) {
                        let errorText = `HTTP error ${response.status}. Expected JSON response.`;
                        try { const text = await response.text(); console.error("Add to Cart - Server Response:", text); errorText = `Server error ${response.status}. Check console/logs.`; } catch (e) { }
                        throw new Error(errorText);
                    }

                    const data = await response.json();

                    if (data.success) {
                        // Update cart count badge(s)
                        document.querySelectorAll('.cart-badge').forEach(badge => {
                            badge.textContent = data.cart_count ?? 0;
                        });

                        // *** NEW: Update the mini cart dropdown display ***
                        updateMiniCart(data.cart_items || []); // Pass the received items array

                        // Optional: Show temporary success indication on button
                        btn.innerHTML = '<i class="fas fa-check"></i> Added';
                        setTimeout(() => { btn.innerHTML = originalHtml; btn.disabled = false; }, 1500);
                    } else {
                        throw new Error(data.message || 'Failed to add item to cart.');
                    }

                } catch (error) {
                    console.error('Add to Cart Fetch Error:', error);
                    alert('Error adding item: ' + error.message);
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            });
        }); // End add-to-cart logic

        // --- Reorder Prescription Functionality ---
        // (Keep this code as it was, unless you also want reorder to update the dropdown dynamically)
        // If you want reorder to update the dropdown, you'll need to modify
        // reorder_prescription.php to return the full cart items array as well,
        // and call updateMiniCart(data.cart_items) in the success handler below.
        document.querySelectorAll('.reorder-btn').forEach(button => {
            button.addEventListener('click', async function (event) {
                event.preventDefault();
                const prescriptionId = this.dataset.id;
                if (!prescriptionId || prescriptionId <= 0) { /* ... error handling ... */ return; }
                const btn = this; /* ... handle button state ... */
                try {
                    const response = await fetch('reorder_prescription.php', { /* ... fetch options ... */ });
                    const contentType = response.headers.get("content-type");
                    if (!response.ok || !contentType || !contentType.includes("application/json")) { /* ... error handling ... */ throw new Error(/*...*/); }
                    const data = await response.json();
                    if (data.success) {
                        document.querySelectorAll('.cart-badge').forEach(badge => { badge.textContent = data.cart_count ?? 0; });
                        // *** ADD THIS LINE if reorder_prescription.php returns cart_items: ***
                        // updateMiniCart(data.cart_items || []);
                        alert('Prescription items added to your cart!');
                    } else { throw new Error(data.message || 'Failed to reorder prescription items.'); }
                } catch (error) { /* ... error handling ... */ }
                finally { /* ... restore button state ... */ }
            });
        }); // End reorder-btn logic

    }); // End DOMContentLoaded
</script>

<?php
// Flush the output buffer and send output to browser
ob_end_flush();
?>