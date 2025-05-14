<?php
session_start();
// Assuming config.php is one level up from the admin directory
require_once __DIR__ . '/../config.php'; // Provides $conn

// --- Authentication & Authorization ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Access denied. Please log in as an admin.'];
    // Adjust path if auth.php is not in the root
    header("Location: ../auth.php");
    exit();
}

$admin_id = $_SESSION['user']['id']; // ID of the currently logged-in admin
$page_title = "Manage Users";
$error_message = null;
$success_message = null;

// --- Action Handling (POST Requests) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check DB connection first
    if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
        $error_message = "Database connection error before processing action.";
    } else {
        // --- Change User Role ---
        if (isset($_POST['change_role']) && isset($_POST['user_id']) && isset($_POST['new_role'])) {
            $user_id_to_change = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            $new_role = $_POST['new_role'];
            $allowed_roles = ['patient', 'doctor', 'admin']; // Define allowed roles

            // Basic validation and security check (don't let admin change their own role this way)
            if ($user_id_to_change && in_array($new_role, $allowed_roles) && $user_id_to_change != $admin_id) {
                try {
                    $sql_update_role = "UPDATE users SET role = ? WHERE id = ?";
                    $stmt_update_role = $conn->prepare($sql_update_role);
                    if (!$stmt_update_role)
                        throw new mysqli_sql_exception("Prepare failed (update role): " . $conn->error);

                    $stmt_update_role->bind_param("si", $new_role, $user_id_to_change);
                    if ($stmt_update_role->execute()) {
                        $success_message = "User #" . $user_id_to_change . "'s role updated to '" . htmlspecialchars($new_role) . "'.";
                        // Optional: Log admin action
                    } else {
                        throw new mysqli_sql_exception("Execute failed (update role): " . $stmt_update_role->error);
                    }
                    $stmt_update_role->close();
                } catch (mysqli_sql_exception $e) {
                    error_log("Admin Change Role DB Error: " . $e->getMessage());
                    $error_message = "Database error updating user role.";
                }
            } elseif ($user_id_to_change == $admin_id) {
                $error_message = "Admin cannot change their own role via this form.";
            } else {
                $error_message = "Invalid user ID or role provided for role change.";
            }
        }

        // --- Change User Status ---
        elseif (isset($_POST['change_status']) && isset($_POST['user_id']) && isset($_POST['new_status'])) {
            $user_id_to_change = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            $new_status = $_POST['new_status'];
            $allowed_statuses = ['active', 'inactive', 'suspended']; // Define allowed statuses

            // Basic validation and security check (don't let admin deactivate themselves)
            if ($user_id_to_change && in_array($new_status, $allowed_statuses) && ($user_id_to_change != $admin_id || $new_status === 'active')) {
                try {
                    $sql_update_status = "UPDATE users SET status = ? WHERE id = ?";
                    $stmt_update_status = $conn->prepare($sql_update_status);
                    if (!$stmt_update_status)
                        throw new mysqli_sql_exception("Prepare failed (update status): " . $conn->error);

                    $stmt_update_status->bind_param("si", $new_status, $user_id_to_change);
                    if ($stmt_update_status->execute()) {
                        $success_message = "User #" . $user_id_to_change . "'s status updated to '" . htmlspecialchars($new_status) . "'.";
                        // Optional: Log admin action
                    } else {
                        throw new mysqli_sql_exception("Execute failed (update status): " . $stmt_update_status->error);
                    }
                    $stmt_update_status->close();
                } catch (mysqli_sql_exception $e) {
                    error_log("Admin Change Status DB Error: " . $e->getMessage());
                    $error_message = "Database error updating user status.";
                }
            } elseif ($user_id_to_change == $admin_id && $new_status !== 'active') {
                $error_message = "Admin cannot change their own status to inactive/suspended.";
            } else {
                $error_message = "Invalid user ID or status provided for status change.";
            }
        }
        // --- Add Delete User Handling Here (with extreme caution and confirmation) ---
        // elseif (isset($_POST['delete_user']) && isset($_POST['user_id'])) { ... }

    } // End DB connection check for POST
} // End POST handling


// --- Data Fetching for Display ---
$users = [];
$db_fetch_error = null;

if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    try {
        $sql_fetch_users = "SELECT id, name, email, phone, role, status, created_at
                            FROM users
                            ORDER BY role ASC, name ASC";
        $result_users = $conn->query($sql_fetch_users);
        if ($result_users) {
            $users = $result_users->fetch_all(MYSQLI_ASSOC);
        } else {
            throw new mysqli_sql_exception("Query failed (fetch users): " . $conn->error);
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Admin Manage Users Fetch Error: " . $e->getMessage());
        $db_fetch_error = "Error loading user data: " . $e->getMessage();
    }
} else {
    $db_fetch_error = $db_fetch_error ?: "Database connection error. Cannot load user data.";
}


// **** CHANGED: Correct path for header ****
require_once __DIR__ . '/../header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>Manage Users</h1>
    </div>


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
    <?php if ($db_fetch_error): ?>
        <div class="alert alert-danger" role="alert">
            Database Error: <?= htmlspecialchars($db_fetch_error) ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">All System Users (<?= count($users) ?>)</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Registered On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td><?= htmlspecialchars($user['name']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></td>
                                    <td>
                                        <form method="POST" action="manage_users.php"
                                            class="d-inline-flex align-items-center gap-1">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <select name="new_role" class="form-select form-select-sm"
                                                <?= ($user['id'] == $admin_id) ? 'disabled' : '' ?>>
                                                <option value="patient" <?= $user['role'] == 'patient' ? 'selected' : '' ?>>Patient
                                                </option>
                                                <option value="doctor" <?= $user['role'] == 'doctor' ? 'selected' : '' ?>>Doctor
                                                </option>
                                                <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Admin
                                                </option>
                                            </select>
                                            <button type="submit" name="change_role" class="btn btn-sm btn-outline-primary"
                                                title="Change Role" <?= ($user['id'] == $admin_id) ? 'disabled' : '' ?>>
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="POST" action="manage_users.php"
                                            class="d-inline-flex align-items-center gap-1">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <select name="new_status" class="form-select form-select-sm"
                                                <?= ($user['id'] == $admin_id && $user['status'] !== 'active') ? 'disabled' : '' ?>>
                                                <option value="active" <?= $user['status'] == 'active' ? 'selected' : '' ?>>Active
                                                </option>
                                                <option value="inactive" <?= $user['status'] == 'inactive' ? 'selected' : '' ?>>
                                                    Inactive</option>
                                                <option value="suspended" <?= $user['status'] == 'suspended' ? 'selected' : '' ?>>
                                                    Suspended</option>
                                                <?php /* Add other statuses if needed */ ?>
                                            </select>
                                            <button type="submit" name="change_status" class="btn btn-sm btn-outline-secondary"
                                                title="Change Status" <?= ($user['id'] == $admin_id && $user['status'] !== 'active') ? 'disabled' : '' ?>>
                                                <i class="fas fa-toggle-on"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-info disabled"
                                            title="Edit Details (Not Implemented)"><i class="fas fa-edit"></i></button>
                                        <?php if ($user['id'] != $admin_id): // Prevent deleting self ?>
                                            <button class="btn btn-sm btn-outline-danger disabled"
                                                title="Delete User (Not Implemented)"><i class="fas fa-trash"></i></button>
                                            <?php /* Add Delete form/logic here later */ ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No users found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
// **** CHANGED: Correct path for footer ****
require_once __DIR__ . '/../footer.php';
?>