<?php
session_start();
require_once __DIR__ . '/../config.php'; // Provides $conn and potentially BASE_URL
// require_once __DIR__ . '/functions.php'; // Include if you have helper functions

// --- Authentication & Authorization ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Access denied. Please log in as an admin.'];
    header("Location: auth.php"); // Adjust path if needed
    exit();
}

$admin_id = $_SESSION['user']['id'];
$db_error = null;
$success_message = null;
$error_message = null;

// --- Define Upload Path ---
// Adjust this path relative to your script's location or use an absolute path
// Ensure this directory exists and is writable by the web server
define('UPLOAD_DIR', __DIR__ . '/assets/medicines/');
define('UPLOAD_URL', (defined('BASE_URL') ? BASE_URL : '.') . '/assets/medicines/'); // For displaying images

// --- Helper Function for Image Upload ---
function handleImageUpload($fileInputName, &$error_message)
{
    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES[$fileInputName]['tmp_name'];
        $fileName = $_FILES[$fileInputName]['name'];
        $fileSize = $_FILES[$fileInputName]['size'];
        $fileType = $_FILES[$fileInputName]['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        // Sanitize filename
        $newFileName = preg_replace('/[^A-Za-z0-9\-_\.]/', '', basename($fileNameCmps[0]));
        $newFileName = $newFileName . '_' . time() . '.' . $fileExtension;

        // Check file extension and size
        $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        $maxFileSize = 5 * 1024 * 1024; // 5 MB

        if (in_array($fileExtension, $allowedfileExtensions)) {
            if ($fileSize <= $maxFileSize) {
                // Check if upload directory exists and is writable
                if (!is_dir(UPLOAD_DIR)) {
                    mkdir(UPLOAD_DIR, 0775, true); // Create directory recursively
                }
                if (!is_writable(UPLOAD_DIR)) {
                    $error_message = "Upload directory is not writable.";
                    error_log("Upload Error: Directory " . UPLOAD_DIR . " is not writable.");
                    return null;
                }

                $dest_path = UPLOAD_DIR . $newFileName;

                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    return $newFileName; // Return the new filename on success
                } else {
                    $error_message = "There was an error moving the uploaded file.";
                    error_log("Upload Error: Failed to move file to " . $dest_path);
                    return null;
                }
            } else {
                $error_message = "File exceeds maximum size limit (5MB).";
                return null;
            }
        } else {
            $error_message = "Upload failed. Allowed file types: " . implode(',', $allowedfileExtensions);
            return null;
        }
    } elseif (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] !== UPLOAD_ERR_NO_FILE) {
        // Handle other upload errors
        $error_message = "File upload error code: " . $_FILES[$fileInputName]['error'];
        return null;
    }
    return null; // No file uploaded or error occurred
}


// --- Action Handling (POST Requests) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check DB connection first
    if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
        $error_message = "Database connection error before processing action.";
    } else {
        // --- Add New Medicine ---
        if (isset($_POST['add_medicine'])) {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $manufacturer = trim($_POST['manufacturer'] ?? '');
            $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
            $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
            $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);
            $dosage = trim($_POST['dosage'] ?? '');
            $side_effects = trim($_POST['side_effects'] ?? '');
            $requires_prescription = isset($_POST['requires_prescription']) ? 1 : 0;

            // Basic Validation
            if (empty($name) || $category_id === false || $price === false || $stock === false || $category_id <= 0 || $price < 0 || $stock < 0) {
                $error_message = "Please fill in all required fields (Name, Category, Price, Stock) with valid values.";
            } else {
                // Handle Image Upload
                $imageFileName = handleImageUpload('image', $error_message); // Pass error message by reference

                if ($error_message === null) { // Proceed only if upload was successful or no file was uploaded
                    try {
                        $sql_insert_med = "INSERT INTO medicines (name, description, manufacturer, category_id, price, stock, dosage, side_effects, requires_prescription, image, created_at)
                                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                        $stmt_insert_med = $conn->prepare($sql_insert_med);
                        if (!$stmt_insert_med)
                            throw new mysqli_sql_exception("Prepare failed (insert med): " . $conn->error);

                        $stmt_insert_med->bind_param("sssidissis", $name, $description, $manufacturer, $category_id, $price, $stock, $dosage, $side_effects, $requires_prescription, $imageFileName);

                        if ($stmt_insert_med->execute()) {
                            $success_message = "Medicine '" . htmlspecialchars($name) . "' added successfully.";
                        } else {
                            throw new mysqli_sql_exception("Execute failed (insert med): " . $stmt_insert_med->error);
                        }
                        $stmt_insert_med->close();
                    } catch (mysqli_sql_exception $e) {
                        error_log("Add Medicine DB Error: " . $e->getMessage());
                        $error_message = "Database error adding medicine. Please check logs.";
                        // Optionally delete uploaded file if DB insert fails
                        if ($imageFileName && file_exists(UPLOAD_DIR . $imageFileName)) {
                            unlink(UPLOAD_DIR . $imageFileName);
                        }
                    }
                }
                // Error message from handleImageUpload() will be displayed if set
            }
        }

        // --- Update Order Status ---
        elseif (isset($_POST['update_order_status'])) {
            $order_id_to_update = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
            $new_status = $_POST['new_status'] ?? '';
            $tracking_number = trim($_POST['tracking_number'] ?? ''); // Get tracking number

            // Validate status and ID
            $allowed_statuses = ['pending', 'processing', 'shipped', 'delivered', 'completed', 'cancelled', 'failed']; // Adjust as needed
            if ($order_id_to_update && in_array($new_status, $allowed_statuses)) {
                try {
                    // Check if tracking number column exists before trying to update it
                    $tracking_sql_part = "";
                    // A simple way to check (might be better ways depending on DB structure knowledge)
                    $result = $conn->query("SHOW COLUMNS FROM `orders` LIKE 'tracking_number'");
                    if ($result && $result->num_rows > 0) {
                        $tracking_sql_part = ", tracking_number = ?";
                    }

                    $sql_update_order = "UPDATE orders SET status = ? {$tracking_sql_part} WHERE id = ?";
                    $stmt_update_order = $conn->prepare($sql_update_order);
                    if (!$stmt_update_order)
                        throw new mysqli_sql_exception("Prepare failed (update order): " . $conn->error);

                    // Bind parameters dynamically based on whether tracking number is being updated
                    if (!empty($tracking_sql_part)) {
                        $stmt_update_order->bind_param("ssi", $new_status, $tracking_number, $order_id_to_update);
                    } else {
                        $stmt_update_order->bind_param("si", $new_status, $order_id_to_update);
                    }


                    if ($stmt_update_order->execute()) {
                        $success_message = "Order #" . $order_id_to_update . " status updated to '" . htmlspecialchars($new_status) . "'.";
                        // Optional: Log admin action
                        // Optional: Send notification to patient
                    } else {
                        throw new mysqli_sql_exception("Execute failed (update order): " . $stmt_update_order->error);
                    }
                    $stmt_update_order->close();
                } catch (mysqli_sql_exception $e) {
                    error_log("Update Order Status DB Error: " . $e->getMessage());
                    $error_message = "Database error updating order status. Please check logs.";
                }
            } else {
                $error_message = "Invalid order ID or status provided for update.";
            }
        }
    } // End DB connection check for POST
} // End POST handling

// --- Data Fetching for Display ---
$medicines = [];
$orders = [];
$categories = [];

if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    try {
        // Fetch Medicines
        $sql_medicines = "SELECT m.*, c.name as category_name FROM medicines m LEFT JOIN categories c ON m.category_id = c.id ORDER BY m.name ASC";
        $result_medicines = $conn->query($sql_medicines);
        if ($result_medicines) {
            $medicines = $result_medicines->fetch_all(MYSQLI_ASSOC);
        } else {
            throw new mysqli_sql_exception("Query failed (fetch medicines): " . $conn->error);
        }

        // Fetch Categories (for add form)
        $sql_categories = "SELECT id, name FROM categories ORDER BY name ASC";
        $result_categories = $conn->query($sql_categories);
        if ($result_categories) {
            $categories = $result_categories->fetch_all(MYSQLI_ASSOC);
        } else {
            throw new mysqli_sql_exception("Query failed (fetch categories): " . $conn->error);
        }


        // Fetch Orders (e.g., Pending or Processing - adjust statuses as needed)
        $sql_orders = "SELECT o.id, o.user_id, o.total_amount, o.status, o.created_at, o.tracking_number,
                              u.name as patient_name, pm.name as payment_method_name
                       FROM orders o
                       JOIN users u ON o.user_id = u.id
                       LEFT JOIN payment_methods pm ON o.payment_method_id = pm.id
                       WHERE o.status IN ('pending', 'processing', 'paid') -- Adjust statuses to show
                       ORDER BY o.created_at DESC";
        $result_orders = $conn->query($sql_orders);
        if ($result_orders) {
            $orders = $result_orders->fetch_all(MYSQLI_ASSOC);
        } else {
            throw new mysqli_sql_exception("Query failed (fetch orders): " . $conn->error);
        }

    } catch (mysqli_sql_exception $e) {
        error_log("Admin Meds/Orders Data Fetch Error: " . $e->getMessage());
        $db_error = "Error loading data: " . $e->getMessage(); // Show specific error for admin? Be cautious.
    }
} else {
    $db_error = $db_error ?: "Database connection error. Cannot load data."; // Set only if not already set by POST handling
}


$page_title = "Manage Medicines & Orders";
// Include admin header (adjust path if necessary)
// Assuming you have a specific header for the admin area
require_once __DIR__ . '/../header.php';
?>

<div class="container-fluid py-4">
    <h1>Manage Medicines & Pharmacy Orders</h1>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($db_error): ?>
        <div class="alert alert-danger" role="alert">
            Database Error: <?= htmlspecialchars($db_error) ?>
        </div>
    <?php endif; ?>


    <ul class="nav nav-tabs mb-3" id="adminTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="medicines-tab" data-bs-toggle="tab" data-bs-target="#medicines-panel"
                type="button" role="tab" aria-controls="medicines-panel" aria-selected="true">
                <i class="fas fa-pills me-1"></i> Medicines List
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="add-medicine-tab" data-bs-toggle="tab" data-bs-target="#add-medicine-panel"
                type="button" role="tab" aria-controls="add-medicine-panel" aria-selected="false">
                <i class="fas fa-plus-circle me-1"></i> Add New Medicine
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders-panel" type="button"
                role="tab" aria-controls="orders-panel" aria-selected="false">
                <i class="fas fa-receipt me-1"></i> Process Orders <span
                    class="badge bg-warning ms-1"><?= count($orders) ?></span>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="adminTabContent">

        <div class="tab-pane fade show active" id="medicines-panel" role="tabpanel" aria-labelledby="medicines-tab">
            <h2>Medicines Inventory</h2>
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Price (UGX)</th>
                                    <th>Stock</th>
                                    <th>Dosage</th>
                                    <th>Prescription?</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($medicines)): ?>
                                    <?php foreach ($medicines as $med): ?>
                                        <tr>
                                            <td><?= $med['id'] ?></td>
                                            <td>
                                                <?php if (!empty($med['image'])): ?>
                                                    <img src="<?= UPLOAD_URL . htmlspecialchars($med['image']) ?>"
                                                        alt="<?= htmlspecialchars($med['name']) ?>" width="40" height="40"
                                                        style="object-fit: contain;">
                                                <?php else: ?>
                                                    <i class="fas fa-pills text-muted fa-2x" style="width:40px;"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($med['name']) ?></td>
                                            <td><?= htmlspecialchars($med['category_name'] ?? 'N/A') ?></td>
                                            <td class="text-end"><?= number_format($med['price'], 2) ?></td>
                                            <td class="text-center"><?= $med['stock'] ?></td>
                                            <td><?= htmlspecialchars($med['dosage'] ?? '-') ?></td>
                                            <td><?= $med['requires_prescription'] ? '<span class="badge bg-warning">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary disabled"
                                                    title="Edit (Not Implemented)"><i class="fas fa-edit"></i></button>
                                                <button class="btn btn-sm btn-outline-danger disabled"
                                                    title="Delete (Not Implemented)"><i class="fas fa-trash"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">No medicines found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="add-medicine-panel" role="tabpanel" aria-labelledby="add-medicine-tab">
            <h2>Add New Medicine</h2>
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" action="admin_manage_medicines_orders.php" enctype="multipart/form-data">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Medicine Name <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="category_id" class="form-label">Category <span
                                        class="text-danger">*</span></label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select Category...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="price" class="form-label">Price (UGX) <span
                                        class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" class="form-control" id="price" name="price"
                                    required>
                            </div>
                            <div class="col-md-4">
                                <label for="stock" class="form-label">Stock Quantity <span
                                        class="text-danger">*</span></label>
                                <input type="number" min="0" class="form-control" id="stock" name="stock" required>
                            </div>
                            <div class="col-md-4">
                                <label for="dosage" class="form-label">Dosage (e.g., 500mg)</label>
                                <input type="text" class="form-control" id="dosage" name="dosage">
                            </div>
                            <div class="col-md-12">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label for="manufacturer" class="form-label">Manufacturer</label>
                                <input type="text" class="form-control" id="manufacturer" name="manufacturer">
                            </div>
                            <div class="col-md-6">
                                <label for="side_effects" class="form-label">Side Effects</label>
                                <input type="text" class="form-control" id="side_effects" name="side_effects">
                            </div>
                            <div class="col-md-6">
                                <label for="image" class="form-label">Image (Optional, Max 5MB)</label>
                                <input class="form-control" type="file" id="image" name="image"
                                    accept="image/png, image/jpeg, image/gif">
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="requires_prescription"
                                        name="requires_prescription">
                                    <label class="form-check-label" for="requires_prescription">
                                        Requires Prescription
                                    </label>
                                </div>
                            </div>

                            <div class="col-12 text-end">
                                <button type="submit" name="add_medicine" class="btn btn-success">
                                    <i class="fas fa-plus me-1"></i> Add Medicine
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="orders-panel" role="tabpanel" aria-labelledby="orders-tab">
            <h2>Process Orders</h2>
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Patient</th>
                                    <th>Date</th>
                                    <th>Amount (UGX)</th>
                                    <th>Payment</th>
                                    <th>Current Status</th>
                                    <th>Tracking #</th>
                                    <th>Update Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($orders)): ?>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>#<?= $order['id'] ?></td>
                                            <td><?= htmlspecialchars($order['patient_name']) ?></td>
                                            <td><?= date('M j, Y H:i', strtotime($order['created_at'])) ?></td>
                                            <td class="text-end"><?= number_format($order['total_amount'], 2) ?></td>
                                            <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $order['payment_method_name'] ?? 'N/A'))) ?>
                                            </td>
                                            <td>
                                                <?php /* Status Badge Logic */
                                                $o_status_color = 'secondary';
                                                switch (strtolower($order['status'])) {
                                                    case 'paid':
                                                    case 'processing':
                                                        $o_status_color = 'info';
                                                        break;
                                                    case 'shipped':
                                                        $o_status_color = 'primary';
                                                        break;
                                                    case 'delivered':
                                                    case 'completed':
                                                        $o_status_color = 'success';
                                                        break;
                                                    case 'cancelled':
                                                    case 'failed':
                                                        $o_status_color = 'danger';
                                                        break;
                                                    case 'pending':
                                                        $o_status_color = 'warning';
                                                        break;
                                                }
                                                ?>
                                                <span
                                                    class="badge bg-<?= $o_status_color ?>"><?= ucfirst(htmlspecialchars($order['status'])) ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($order['tracking_number'] ?? '-') ?></td>
                                            <td>
                                                <form method="POST" action="admin_manage_medicines_orders.php"
                                                    class="d-flex gap-1">
                                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                    <select name="new_status" class="form-select form-select-sm"
                                                        style="min-width: 120px;" required>
                                                        <option value="processing" <?= $order['status'] == 'processing' ? 'selected' : '' ?>>Processing</option>
                                                        <option value="shipped" <?= $order['status'] == 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                                        <option value="delivered" <?= $order['status'] == 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                                        <option value="completed" <?= $order['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                                                        <option value="cancelled" <?= $order['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                        <option value="failed" <?= $order['status'] == 'failed' ? 'selected' : '' ?>>Failed</option>
                                                        <option value="pending" <?= $order['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                                    </select>
                                                    <input type="text" name="tracking_number"
                                                        class="form-control form-control-sm" placeholder="Tracking #"
                                                        value="<?= htmlspecialchars($order['tracking_number'] ?? '') ?>"
                                                        style="min-width: 100px;">
                                                    <button type="submit" name="update_order_status"
                                                        class="btn btn-sm btn-success" title="Update Status"><i
                                                            class="fas fa-check"></i></button>
                                                </form>
                                            </td>
                                            <td>
                                                <a href="admin_order_details.php?id=<?= $order['id'] ?>"
                                                    class="btn btn-sm btn-outline-primary" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">No orders found matching the current
                                            criteria.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
<?php
// Include admin footer (adjust path if necessary)
require_once __DIR__ . '/../footer.php';
?>