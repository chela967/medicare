<?php
function sanitizeInput($data)
{
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

function saveAppointment($name, $email, $phone, $date, $time, $service, $message)
{
    global $conn;

    $sql = "INSERT INTO appointments (name, email, phone, appointment_date, appointment_time, service, message) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", $name, $email, $phone, $date, $time, $service, $message);

    return $stmt->execute();
}

// Function to get all appointments (for admin view)
// Add these functions to your existing functions.php

/*function emailExists($email)
{
    global $conn;
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    return $stmt->num_rows > 0;
}*/

function registerUser($name, $email, $password, $phone)
{
    global $conn;

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, ?, 'patient')");
    $stmt->bind_param("ssss", $name, $email, $hashed_password, $phone);

    return $stmt->execute();
}

function authenticateUser($email, $password)
{
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            return $user; // Make sure this includes 'role' field
        }
    }
    return false;
}


function getUserByEmail($email)
{
    global $conn;
    $stmt = $conn->prepare("SELECT id, name, email, phone, role, status FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        return $result->fetch_assoc();
    }
    return false;
}

function isLoggedIn()
{
    return isset($_SESSION['user']);
}

function logout()
{
    session_unset();
    session_destroy();
}
function getAppointments()
{
    global $conn;
    $sql = "SELECT * FROM appointments ORDER BY appointment_date, appointment_time";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}
// Add these functions to your functions.php file


/**
 * Get medicines from database
 */
/**
 * Get medicines with optional category filter and search
 */
function getMedicines($category_id = null, $search = null)
{
    global $conn;

    try {
        $query = "SELECT m.*, c.name as category_name FROM medicines m 
                 LEFT JOIN categories c ON m.category_id = c.id 
                 WHERE 1=1";

        $params = [];
        $types = '';

        if ($category_id) {
            $query .= " AND m.category_id = ?";
            $params[] = $category_id;
            $types .= 'i';
        }

        if ($search) {
            $query .= " AND (m.name LIKE ? OR m.description LIKE ? OR m.manufacturer LIKE ?)";
            $search_term = "%$search%";
            $params = array_merge($params, [$search_term, $search_term, $search_term]);
            $types .= str_repeat('s', 3);
        }

        $query .= " ORDER BY m.name";
        $stmt = $conn->prepare($query);

        if ($params) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);

    } catch (Exception $e) {
        error_log("Database error in getMedicines(): " . $e->getMessage());
        return [];
    }
}
/**
 * Get all medicine categories
 */
function getCategories()
{
    global $conn;
    $result = $conn->query("SELECT * FROM categories ORDER BY name");
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getDoctorIdByUserId(int $user_id, mysqli $conn): int|false
{
    $sql = "SELECT id FROM doctors WHERE user_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("MySQLi prepare failed (getDoctorIdByUserId): (" . $conn->errno . ") " . $conn->error);
        return false;
    }

    // Bind the integer user_id
    if (!$stmt->bind_param("i", $user_id)) {
        error_log("MySQLi bind_param failed (getDoctorIdByUserId): (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return false;
    }

    // Execute
    if (!$stmt->execute()) {
        error_log("MySQLi execute failed (getDoctorIdByUserId): (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return false;
    }

    // Get result
    $result = $stmt->get_result();
    if (!$result) {
        error_log("MySQLi get_result failed (getDoctorIdByUserId): (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return false;
    }

    // Fetch data
    $doctor = $result->fetch_assoc();

    // Close statement
    $stmt->close();

    // Return the ID as integer if found, otherwise false
    return $doctor ? (int) $doctor['id'] : false;
}


function getUserPrescriptions($user_id)
{
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM prescriptions WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}


/**
 * Shorten text to specified length
 */
function shortenText($text, $length = 100)
{
    if (strlen($text) > $length) {
        return substr($text, 0, $length) . '...';
    }
    return $text;
}

/**
 * Check user role
 */
function checkUserRole($role)
{
    // Check if user session and role are set
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role'])) {
        return false;
    }
    return $_SESSION['user']['role'] === $role;
}

function getCart($user_id)
{
    global $conn;

    // Validate user_id
    if (empty($user_id)) {
        return [];
    }

    $stmt = $conn->prepare("SELECT c.*, m.name, m.price, m.image 
                           FROM cart c
                           JOIN medicines m ON c.medicine_id = m.id
                           WHERE c.user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}
/**
 * Update cart item quantity
 */
function updateCartItem($cart_id, $user_id, $quantity)
{
    global $conn;

    $quantity = max(1, min(10, (int) $quantity)); // Limit 1-10

    $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("iii", $quantity, $cart_id, $user_id);
    return $stmt->execute();
}

/**
 * Remove item from cart
 */
function removeCartItem($cart_id, $user_id)
{
    global $conn;

    $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $cart_id, $user_id);
    return $stmt->execute();
}

/**
 * Calculate cart subtotal
 */
function calculateSubtotal($cart_items)
{
    $subtotal = 0;
    foreach ($cart_items as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    return $subtotal;
}

/**
 * Get available payment methods
 */
function getPaymentMethods()
{
    global $conn;

    $result = $conn->query("SELECT * FROM payment_methods WHERE is_active = 1");
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Process checkout and create order
 */
function processCheckout($user_id, $payment_method_id)
{
    global $conn;

    try {
        $conn->begin_transaction();

        // 1. Get cart items
        $cart_items = getCart($user_id);
        if (empty($cart_items))
            return false;

        $total = calculateSubtotal($cart_items) + 5; // + delivery fee

        // 2. Create order
        $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, payment_method_id) VALUES (?, ?, ?)");
        $stmt->bind_param("idi", $user_id, $total, $payment_method_id);
        $stmt->execute();
        $order_id = $conn->insert_id;

        // 3. Add order items
        $stmt = $conn->prepare("INSERT INTO order_items (order_id, medicine_id, quantity, price) VALUES (?, ?, ?, ?)");
        foreach ($cart_items as $item) {
            $stmt->bind_param("iiid", $order_id, $item['medicine_id'], $item['quantity'], $item['price']);
            $stmt->execute();

            // Update medicine stock
            $conn->query("UPDATE medicines SET stock = stock - {$item['quantity']} WHERE id = {$item['medicine_id']}");
        }

        // 4. Clear cart
        $conn->query("DELETE FROM cart WHERE user_id = $user_id");

        $conn->commit();
        return $order_id;

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Checkout error: " . $e->getMessage());
        return false;
    }
}
function getConsultationFee($doctor_id, $specialty_id = null)
{
    global $conn;

    // First try to get doctor-specific fee
    $stmt = $conn->prepare("SELECT consultation_fee FROM doctors WHERE id = ?");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        return (float) $result->fetch_assoc()['consultation_fee'];
    }

    // If no doctor fee and specialty_id provided, try specialty default
    if ($specialty_id) {
        $stmt = $conn->prepare("SELECT default_fee FROM specialties WHERE id = ?");
        $stmt->bind_param("i", $specialty_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            return (float) $result->fetch_assoc()['default_fee'];
        }
    }

    return 0.00; // Absolute default
}
function createAppointment($data)
{
    global $conn;

    try {
        $conn->begin_transaction();

        // Insert appointment
        $stmt = $conn->prepare("INSERT INTO appointments 
            (patient_id, doctor_id, appointment_date, appointment_time, 
             reason, consultation_fee, payment_method_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param(
            "iisssdi",
            $data['patient_id'],
            $data['doctor_id'],
            $data['date'],
            $data['time'],
            $data['reason'],
            $data['fee'],
            $data['payment_method_id']
        );

        $stmt->execute();
        $appointment_id = $conn->insert_id;

        // Process payment
        if ($data['fee'] > 0) {
            $payment_success = processPayment([
                'amount' => $data['fee'],
                'method_id' => $data['payment_method_id'],
                'appointment_id' => $appointment_id
            ]);

            if (!$payment_success) {
                throw new Exception("Payment processing failed");
            }

            // Update payment status
            $conn->query("UPDATE appointments SET payment_status = 'paid' WHERE id = $appointment_id");
        }

        $conn->commit();
        return $appointment_id;

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Appointment creation error: " . $e->getMessage());
        return false;
    }
}

function processPayment($payment_data)
{
    // Implement your payment gateway integration here
    // This is a placeholder - integrate with your actual payment processor

    // For demo purposes, we'll just log it
    error_log("Processing payment: " . print_r($payment_data, true));

    // In a real implementation, you would:
    // 1. Call payment gateway API (PayPal, Stripe, etc.)
    // 2. Handle the response
    // 3. Return true if successful

    return true; // Simulate successful payment
}
/**
 * Get appointment details with patient and doctor information
 */
function getAppointmentDetails($appointment_id, $user_id)
{
    global $conn;

    $stmt = $conn->prepare("SELECT 
            a.*,
            u_p.name AS patient_name,
            u_p.email AS patient_email,
            u_p.phone AS patient_phone,
            u_d.name AS doctor_name,
            s.name AS specialty,
            d.consultation_fee
        FROM appointments a
        JOIN users u_p ON a.patient_id = u_p.id
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users u_d ON d.user_id = u_d.id
        JOIN specialties s ON d.specialty_id = s.id
        WHERE a.id = ? AND (a.patient_id = ? OR u_d.id = ?)");

    $stmt->bind_param("iii", $appointment_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->fetch_assoc();
}
/**
 * Get all appointments for a user (patient or doctor)
 */
function getUserAppointments($user_id, $user_role)
{
    global $conn;

    $query = "SELECT a.*, 
              u.name AS patient_name,
              d.name AS doctor_name,
              s.name AS specialty
              FROM appointments a
              JOIN users u ON a.patient_id = u.id
              JOIN doctors d ON a.doctor_id = d.id
              JOIN specialties s ON d.specialty_id = s.id
              WHERE ";

    $query .= ($user_role === 'doctor')
        ? "d.user_id = ?"
        : "a.patient_id = ?";

    $query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Cancel an appointment
 */
function cancelAppointment($appointment_id, $user_id)
{
    global $conn;

    // Verify appointment belongs to user
    $stmt = $conn->prepare("UPDATE appointments 
                           SET status = 'cancelled'
                           WHERE id = ? AND patient_id = ?");
    $stmt->bind_param("ii", $appointment_id, $user_id);
    return $stmt->execute();
}
/**
 * Get doctors with their specialties
 */
function getDoctorsWithSpecialties()
{
    global $conn;

    $query = "SELECT d.id, u.name, s.name AS specialty, d.consultation_fee 
              FROM doctors d
              JOIN users u ON d.user_id = u.id
              JOIN specialties s ON d.specialty_id = s.id
              WHERE d.available = 1
              ORDER BY u.name";

    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get available time slots
 */
function getAvailableTimeSlots()
{
    // In a real app, this would query the database for available slots
    // For demo, we'll return fixed time slots
    return [
        ['time' => '08:00:00'],
        ['time' => '09:00:00'],
        ['time' => '10:00:00'],
        ['time' => '11:00:00'],
        ['time' => '13:00:00'],
        ['time' => '14:00:00'],
        ['time' => '15:00:00']
    ];
}

/**
 * Format phone number for MTN Uganda (converts to 256... format)
 */
function formatPhoneNumber($phone)
{
    $phone = preg_replace('/[^0-9]/', '', $phone);

    if (strlen($phone) === 9 && $phone[0] === '7') {
        return '256' . $phone;
    } elseif (strlen($phone) === 10 && $phone[0] === '0') {
        return '256' . substr($phone, 1);
    }

    return $phone;
}

/**
 * Update appointment payment info
 */
function updateAppointmentPayment($appointment_id, $data)
{
    global $conn;

    $stmt = $conn->prepare("UPDATE appointments SET 
        payment_method = ?,
        payment_status = ?,
        transaction_id = ?,
        patient_phone = ?
        WHERE id = ?");

    $stmt->bind_param(
        "ssssi",
        $data['payment_method'],
        $data['payment_status'],
        $data['transaction_id'],
        $data['patient_phone'],
        $appointment_id
    );

    return $stmt->execute();
}
/**
 * Get available doctors with their specialties and fees
 */
function getAvailableDoctors()
{
    global $conn;

    $query = "SELECT 
                d.id,
                u.name,
                s.name AS specialty,
                d.consultation_fee,
                d.license_number
              FROM doctors d
              JOIN users u ON d.user_id = u.id
              JOIN specialties s ON d.specialty_id = s.id
              WHERE d.available = 1
              AND u.status = 'active'
              ORDER BY u.name";

    $result = $conn->query($query);

    if (!$result) {
        // Log error for debugging
        error_log("Database error: " . $conn->error);
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}/**
 * Check if a doctor with the same license number already exists
 */
function licenseNumberExists($license_number)
{
    global $conn;
    $stmt = $conn->prepare("SELECT id FROM doctors WHERE license_number = ?");
    $stmt->bind_param("s", $license_number);
    $stmt->execute();
    $stmt->store_result();
    return $stmt->num_rows > 0;
}

/**
 * Get all doctors with their details
 */
function getAllDoctors()
{
    global $conn;

    $query = "SELECT d.id, u.name, u.email, u.phone, s.name AS specialty, 
                     d.license_number, d.consultation_fee, d.available
              FROM doctors d
              JOIN users u ON d.user_id = u.id
              JOIN specialties s ON d.specialty_id = s.id
              ORDER BY u.name";

    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get doctor details by ID
 */
function getDoctorById($doctor_id)
{
    global $conn;

    $stmt = $conn->prepare("SELECT d.*, u.name, u.email, u.phone, u.status, 
                                   s.name AS specialty_name
                            FROM doctors d
                            JOIN users u ON d.user_id = u.id
                            JOIN specialties s ON d.specialty_id = s.id
                            WHERE d.id = ?");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}
// ADMIN FUNCTIONS
function getUsersCount()
{
    global $conn;
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    return $result->fetch_assoc()['count'];
}

function getDoctorsCount()
{
    global $conn;
    $result = $conn->query("SELECT COUNT(*) as count FROM doctors WHERE status = 'approved'");
    return $result->fetch_assoc()['count'];
}

function getAppointmentsCount()
{
    global $conn;
    $result = $conn->query("SELECT COUNT(*) as count FROM appointments");
    return $result->fetch_assoc()['count'];
}

function getPendingDoctors()
{
    global $conn;
    $query = "SELECT d.id, u.name, s.name as specialty, d.license_number, d.created_at 
              FROM doctors d
              JOIN users u ON d.user_id = u.id
              JOIN specialties s ON d.specialty_id = s.id
              WHERE d.status = 'pending'";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getRecentAppointments($limit = 5)
{
    global $conn;
    $query = "SELECT a.id, a.appointment_date, a.appointment_time, a.status,
                     u_p.name as patient_name, u_d.name as doctor_name
              FROM appointments a
              JOIN users u_p ON a.patient_id = u_p.id
              JOIN doctors d ON a.doctor_id = d.id
              JOIN users u_d ON d.user_id = u_d.id
              ORDER BY a.appointment_date DESC, a.appointment_time DESC
              LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// DOCTOR FUNCTIONS

function getDoctorDetails($doctor_id)
{
    global $conn;
    $query = "SELECT d.*, s.name as specialty 
              FROM doctors d
              JOIN specialties s ON d.specialty_id = s.id
              WHERE d.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function getDoctorAppointments($doctor_id, $date)
{
    global $conn;
    $query = "SELECT a.id, a.appointment_time, a.status, a.reason,
                     u.id as patient_id, u.name as patient_name
              FROM appointments a
              JOIN users u ON a.patient_id = u.id
              WHERE a.doctor_id = ? AND a.appointment_date = ?
              ORDER BY a.appointment_time";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $doctor_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getUpcomingAppointments($doctor_id, $limit = 5)
{
    global $conn;
    $query = "SELECT a.id, a.appointment_date, a.appointment_time, a.reason,
                     u.name as patient_name
              FROM appointments a
              JOIN users u ON a.patient_id = u.id
              WHERE a.doctor_id = ? AND a.appointment_date >= CURDATE() AND a.status = 'scheduled'
              ORDER BY a.appointment_date, a.appointment_time
              LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $doctor_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}


function isUserApprovedDoctor($user_id)
{
    global $conn;
    $stmt = $conn->prepare("SELECT status FROM doctors WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return ($result->num_rows > 0 && $result->fetch_assoc()['status'] === 'approved');
}
function getDoctorData($user_id)
{
    global $conn;
    $stmt = $conn->prepare("SELECT id, status, specialty_id FROM doctors WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function validatePhoneNumber($phone)
{
    // Validate Uganda phone number format
    return preg_match('/^(0|[+]?256)(7|3)\d{8}$/', $phone);
}

function registerPatient($name, $email, $password, $phone)
{
    global $conn;

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Set default role to 'patient'
    $role = 'patient';
    $status = 'active'; // Default status

    try {
        // Start transaction
        mysqli_begin_transaction($conn);

        // Insert into users table
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, role, status) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $name, $email, $hashed_password, $phone, $role, $status);
        $stmt->execute();
        $user_id = $stmt->insert_id;

        // Insert into patients table (if it exists)
        $patient_inserted = true;
        if (tableExists('patients')) {
            $stmt = $conn->prepare("INSERT INTO patients (user_id) VALUES (?)");
            $stmt->bind_param("i", $user_id);
            $patient_inserted = $stmt->execute();
        }

        // Commit transaction if both inserts succeeded
        if ($user_id && $patient_inserted) {
            mysqli_commit($conn);
            return $user_id;
        } else {
            mysqli_rollback($conn);
            return false;
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Registration error: " . $e->getMessage());
        return false;
    }
}

// Helper function to check if table exists
function tableExists($table)
{
    global $conn;
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    return (mysqli_num_rows($result) > 0);
}

function sendEmail($to, $subject, $body)
{
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Medicare <noreply@medicare.com>\r\n";

    @mail($to, $subject, $body, $headers);
}

function sendWelcomeEmail($email, $name)
{
    $subject = "Welcome to Medicare";
    $message = "
        <html>
        <body>
            <h2>Welcome, $name!</h2>
            <p>Your patient account has been successfully created.</p>
            <p>You can now book appointments with our doctors.</p>
        </body>
        </html>
    ";
    sendEmail($email, $subject, $message);
}

function emailExists($email)
{
    global $conn;
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

/*function sanitizeInput($data)
{
    global $conn;
    return htmlspecialchars(stripslashes(trim($conn->real_escape_string($data))));
}*/
function logAdminAction($admin_id, $action)
{
    global $conn;

    try {
        $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action) VALUES (?, ?)");
        $stmt->bind_param("is", $admin_id, $action);
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Failed to log admin action: " . $e->getMessage());
        return false;
    }
}
if (!function_exists('logAdminAction')) {
    function logAdminAction($admin_id, $action)
    {
        // Default implementation if not defined elsewhere
        error_log("Admin Action: [$admin_id] $action");
        return true;
    }
}
/**
 * Get all patients from the database
 */
function getAllPatients()
{
    global $conn;
    $sql = "SELECT p.*, u.email, u.status 
            FROM patients p 
            JOIN users u ON p.user_id = u.id 
            ORDER BY p.created_at DESC";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

/**
 * Search patients by name, email, or phone
 */
function searchPatients($search)
{
    global $conn;
    $search = "%$search%";
    $stmt = $conn->prepare("SELECT p.*, u.email, u.status 
                          FROM patients p 
                          JOIN users u ON p.user_id = u.id 
                          WHERE p.name LIKE ? OR u.email LIKE ? OR p.phone LIKE ?
                          ORDER BY p.created_at DESC");
    $stmt->bind_param("sss", $search, $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Delete a patient
 */
function deletePatient($patient_id)
{
    global $conn;

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // First get user_id from patient
        $stmt = $conn->prepare("SELECT user_id FROM patients WHERE id = ?");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $patient = $result->fetch_assoc();

        if (!$patient) {
            return false;
        }

        $user_id = $patient['user_id'];

        // Delete patient record
        $stmt = $conn->prepare("DELETE FROM patients WHERE id = ?");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();

        // Delete user record
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        // Commit transaction
        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        return false;
    }
}

// Make sure this is inside your functions.php file
// (You might already have <?php at the top)

/**
 * Fetches details for a specific appointment using MySQLi, ensuring it belongs to the specified doctor.
 * Joins with the users table to get patient information.
 *
 * @param int $appointment_id The ID of the appointment to fetch.
 * @param int $doctor_id The ID of the doctor who should own this appointment.
 * @param mysqli $conn MySQLi database connection object.
 * @return array|false An associative array with appointment details or false if not found/not authorized/error.
 */
function getAppointmentDetailsForDoctor(int $appointment_id, int $doctor_id, mysqli $conn): array|false
{
    // SQL query to select appointment details and join with users table for patient info
    // Ensure the appointment ID and doctor ID match
    $sql = "SELECT
                a.id AS appointment_id,
                a.appointment_date,
                a.appointment_time,
                a.reason,
                a.consultation_fee,
                a.payment_status,
                a.payment_method,
                a.status AS appointment_status,
                a.notes AS consultation_notes,
                a.created_at AS appointment_created_at,
                p.id AS patient_user_id,
                p.name AS patient_name,
                p.email AS patient_email,
                p.phone AS patient_phone
            FROM
                appointments a
            JOIN
                users p ON a.patient_id = p.id -- Join users table for patient info
            WHERE
                a.id = ? -- Placeholder for appointment ID
            AND
                a.doctor_id = ? -- Placeholder for doctor ID (Security check)
            LIMIT 1"; // We only expect one result

    // 1. Prepare statement
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // Log error if prepare fails
        error_log("MySQLi prepare failed in getAppointmentDetailsForDoctor: (" . $conn->errno . ") " . $conn->error);
        return false; // Return false on prepare error
    }

    // 2. Bind parameters (use "ii" for two integers: appointment_id, doctor_id)
    if (!$stmt->bind_param("ii", $appointment_id, $doctor_id)) {
        // Log error if bind_param fails
        error_log("MySQLi bind_param failed in getAppointmentDetailsForDoctor: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close(); // Close statement before returning
        return false;
    }

    // 3. Execute statement
    if (!$stmt->execute()) {
        // Log error if execute fails
        error_log("MySQLi execute failed in getAppointmentDetailsForDoctor: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close(); // Close statement before returning
        return false;
    }

    // 4. Get result set
    $result = $stmt->get_result();
    if (!$result) {
        // Log error if get_result fails
        error_log("MySQLi get_result failed in getAppointmentDetailsForDoctor: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close(); // Close statement before returning
        return false;
    }

    // 5. Fetch data as an associative array
    $appointment = $result->fetch_assoc(); // Returns array or null if not found

    // 6. Clean up: Close the statement
    $stmt->close();

    // 7. Return the fetched array (or false if $appointment is null/empty)
    return $appointment ?: false; // Return false if $appointment is null (not found or no permission)
}

function getDoctorPatientCount(int $doctor_id, mysqli $conn): int
{
    // SQL to count distinct patient IDs from appointments for the given doctor
    $sql = "SELECT COUNT(DISTINCT patient_id) AS total_patients FROM appointments WHERE doctor_id = ?";

    $count = 0; // Default count

    // Prepare statement
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("MySQLi prepare failed in getDoctorPatientCount: (" . $conn->errno . ") " . $conn->error);
        return $count; // Return 0 on error
    }

    // Bind parameter
    if (!$stmt->bind_param("i", $doctor_id)) {
        error_log("MySQLi bind_param failed in getDoctorPatientCount: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return $count;
    }

    // Execute
    if (!$stmt->execute()) {
        error_log("MySQLi execute failed in getDoctorPatientCount: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return $count;
    }

    // Get result
    $result = $stmt->get_result();
    if (!$result) {
        error_log("MySQLi get_result failed in getDoctorPatientCount: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return $count;
    }

    // Fetch the count
    $row = $result->fetch_assoc();
    if ($row) {
        $count = (int) $row['total_patients'];
    }

    // Clean up
    $stmt->close();

    return $count;
}
function getDoctorProfile(int $doctor_id, mysqli $conn): array|false
{
    $sql = "SELECT
                d.id AS doctor_id, d.user_id, d.specialty_id, d.license_number,
                d.qualifications, d.bio, d.consultation_fee, d.available, d.status AS doctor_status,
                u.name, u.email, u.phone,
                s.name AS specialty_name
            FROM doctors d
            JOIN users u ON d.user_id = u.id
            LEFT JOIN specialties s ON d.specialty_id = s.id
            WHERE d.id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("MySQLi prepare failed in getDoctorProfile: (" . $conn->errno . ") " . $conn->error);
        return false;
    }
    if (!$stmt->bind_param("i", $doctor_id)) {
        error_log("MySQLi bind_param failed in getDoctorProfile: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return false;
    }
    if (!$stmt->execute()) {
        error_log("MySQLi execute failed in getDoctorProfile: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return false;
    }
    $result = $stmt->get_result();
    if (!$result) {
        error_log("MySQLi get_result failed in getDoctorProfile: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return false;
    }
    $profile = $result->fetch_assoc();
    $stmt->close();
    return $profile ?: false;
}

/**
 * Fetches all available specialties.
 *
 * @param mysqli $conn MySQLi database connection object.
 * @return array An array of specialties (id, name).
 */
function getAllSpecialties(mysqli $conn): array
{
    $sql = "SELECT id, name FROM specialties ORDER BY name ASC";
    $specialties = [];
    $result = $conn->query($sql); // Simple query, no parameters needed
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $specialties[] = $row;
        }
        $result->free(); // Free result set
    } else {
        error_log("MySQLi query failed in getAllSpecialties: (" . $conn->errno . ") " . $conn->error);
    }
    return $specialties;
}
function updateDoctorProfile(int $doctor_id, int $user_id, array $data, mysqli $conn): bool
{
    // Start transaction
    $conn->begin_transaction();

    try {
        // --- Update 'users' table ---
        $sql_user_update = "UPDATE users SET name = ?, email = ?, phone = ?";
        $params_user = [$data['name'], $data['email'], $data['phone']];
        $types_user = "sss";

        // Add password update if a new hash is provided
        if (!empty($data['new_password_hash'])) {
            $sql_user_update .= ", password = ?";
            $params_user[] = $data['new_password_hash'];
            $types_user .= "s";
        }

        $sql_user_update .= " WHERE id = ?";
        $params_user[] = $user_id;
        $types_user .= "i";

        $stmt_user = $conn->prepare($sql_user_update);
        if (!$stmt_user)
            throw new Exception("User update prepare failed: " . $conn->error);
        if (!$stmt_user->bind_param($types_user, ...$params_user))
            throw new Exception("User update bind failed: " . $stmt_user->error);
        if (!$stmt_user->execute())
            throw new Exception("User update execute failed: " . $stmt_user->error);
        $stmt_user->close();


        // --- Update 'doctors' table ---
        $sql_doctor_update = "UPDATE doctors SET
                                specialty_id = ?, license_number = ?, qualifications = ?,
                                bio = ?, consultation_fee = ?, available = ?
                              WHERE id = ?";
        $types_doctor = "isssdii"; // Check types: i, s, s, s, d, i, i
        $params_doctor = [
            $data['specialty_id'],
            $data['license_number'],
            $data['qualifications'],
            $data['bio'],
            $data['consultation_fee'],
            $data['available'],
            $doctor_id
        ];

        $stmt_doctor = $conn->prepare($sql_doctor_update);
        if (!$stmt_doctor)
            throw new Exception("Doctor update prepare failed: " . $conn->error);
        if (!$stmt_doctor->bind_param($types_doctor, ...$params_doctor))
            throw new Exception("Doctor update bind failed: " . $stmt_doctor->error);
        if (!$stmt_doctor->execute())
            throw new Exception("Doctor update execute failed: " . $stmt_doctor->error);
        $stmt_doctor->close();


        // If both updates were successful, commit the transaction
        $conn->commit();
        return true;

    } catch (Exception $e) {
        // An error occurred, rollback the transaction
        $conn->rollback();
        error_log("Doctor profile update failed: " . $e->getMessage());
        return false;
    }
}
function getDoctorSchedule(int $doctor_id, mysqli $conn): array
{
    // Define the desired order of days
    $day_order = "'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'";

    $sql = "SELECT id, day_of_week, start_time, end_time, is_available
            FROM schedules
            WHERE doctor_id = ?
            ORDER BY FIELD(day_of_week, {$day_order}), start_time ASC";

    $schedule = [];
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("MySQLi prepare failed in getDoctorSchedule: (" . $conn->errno . ") " . $conn->error);
        return $schedule;
    }
    if (!$stmt->bind_param("i", $doctor_id)) {
        error_log("MySQLi bind_param failed in getDoctorSchedule: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return $schedule;
    }
    if (!$stmt->execute()) {
        error_log("MySQLi execute failed in getDoctorSchedule: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return $schedule;
    }
    $result = $stmt->get_result();
    if (!$result) {
        error_log("MySQLi get_result failed in getDoctorSchedule: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return $schedule;
    }
    while ($row = $result->fetch_assoc()) {
        // Format times for easier display
        $row['start_time_formatted'] = date('g:i A', strtotime($row['start_time']));
        $row['end_time_formatted'] = date('g:i A', strtotime($row['end_time']));
        $schedule[] = $row;
    }
    $stmt->close();
    return $schedule;
}

/**
 * Adds a new schedule entry for a doctor.
 *
 * @param int $doctor_id The doctor's ID.
 * @param string $day The day of the week (e.g., 'monday').
 * @param string $start_time Start time (HH:MM:SS or HH:MM format).
 * @param string $end_time End time (HH:MM:SS or HH:MM format).
 * @param mysqli $conn MySQLi database connection object.
 * @return bool True on success, False on failure.
 */
function addDoctorSchedule(int $doctor_id, string $day, string $start_time, string $end_time, mysqli $conn): bool
{
    // Basic validation (more robust validation recommended)
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    if (!in_array($day, $days) || strtotime($end_time) <= strtotime($start_time)) {
        error_log("Invalid data provided to addDoctorSchedule.");
        return false; // Invalid day or end time not after start time
    }

    $sql = "INSERT INTO schedules (doctor_id, day_of_week, start_time, end_time, is_available)
            VALUES (?, ?, ?, ?, 1)"; // Default to available

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("MySQLi prepare failed in addDoctorSchedule: (" . $conn->errno . ") " . $conn->error);
        return false;
    }
    // Bind parameters: i (doctor_id), s (day), s (start_time), s (end_time)
    if (!$stmt->bind_param("isss", $doctor_id, $day, $start_time, $end_time)) {
        error_log("MySQLi bind_param failed in addDoctorSchedule: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return false;
    }
    if (!$stmt->execute()) {
        error_log("MySQLi execute failed in addDoctorSchedule: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return false;
    }
    $stmt->close();
    return true;
}


/**
 * Deletes a specific schedule entry for a doctor.
 *
 * @param int $schedule_id The ID of the schedule entry to delete.
 * @param int $doctor_id The ID of the doctor (for ownership verification).
 * @param mysqli $conn MySQLi database connection object.
 * @return bool True on success, False on failure or if not authorized.
 */
function deleteDoctorSchedule(int $schedule_id, int $doctor_id, mysqli $conn): bool
{
    $sql = "DELETE FROM schedules WHERE id = ? AND doctor_id = ?"; // Verify ownership
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("MySQLi prepare failed in deleteDoctorSchedule: (" . $conn->errno . ") " . $conn->error);
        return false;
    }
    if (!$stmt->bind_param("ii", $schedule_id, $doctor_id)) {
        error_log("MySQLi bind_param failed in deleteDoctorSchedule: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return false;
    }
    if (!$stmt->execute()) {
        error_log("MySQLi execute failed in deleteDoctorSchedule: (" . $stmt->errno . ") " . $stmt->error);
        $stmt->close();
        return false;
    }

    $deleted = $stmt->affected_rows > 0; // Check if a row was actually deleted
    $stmt->close();
    return $deleted;
}
function calculateAge($dob)
{
    if (empty($dob) || $dob === '0000-00-00') {
        return 'N/A';
    }
    try {
        $birthDate = new DateTime($dob);
        $today = new DateTime('today');
        return $today->diff($birthDate)->y;
    } catch (Exception $e) {
        return 'N/A';
    }
}
function getOrderDetails($order_id, $user_id)
{
    global $conn; // Assuming $conn is your database connection from config.php

    $stmt = $conn->prepare("
        SELECT o.*, pm.name AS payment_method_name 
        FROM orders o
        LEFT JOIN payment_methods pm ON o.payment_method_id = pm.id
        WHERE o.id = ? AND o.user_id = ?
    ");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->fetch_assoc();
}

/**
 * Get all items for a specific order
 */
function getOrderItems($order_id)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT oi.*, m.name, m.image, m.description
        FROM order_items oi
        JOIN medicines m ON oi.medicine_id = m.id
        WHERE oi.order_id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }

    return $items;
}
function getOrders($user_id)
{
    global $conn;

    try {
        $query = "SELECT o.*, pm.name as payment_method, 
                 COUNT(oi.id) as item_count, 
                 SUM(oi.price * oi.quantity) as order_total
                 FROM orders o
                 LEFT JOIN payment_methods pm ON o.payment_method_id = pm.id
                 LEFT JOIN order_items oi ON o.id = oi.order_id
                 WHERE o.user_id = ?
                 GROUP BY o.id
                 ORDER BY o.created_at DESC";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return []; // Return empty array if no orders
        }

        return $result->fetch_all(MYSQLI_ASSOC);

    } catch (Exception $e) {
        error_log("Order loading failed: " . $e->getMessage());
        return false;
    }
}
function handlePostRequest()
{
    global $conn, $appointment_id, $patient_id, $patient_name;

    // Set JSON header immediately
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }

    $response = ['success' => false];
    $message_content = trim($_POST['message'] ?? '');

    try {
        // Validate message
        if (empty($message_content)) {
            $response['error'] = "Message cannot be empty";
            echo json_encode($response);
            return;
        }

        // Verify user is authenticated
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'patient') {
            $response['error'] = "Unauthorized - Please log in";
            echo json_encode($response);
            return;
        }

        // Check DB connection
        if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
            $response['error'] = "Database connection error";
            echo json_encode($response);
            return;
        }

        // Verify appointment belongs to patient
        $verify_sql = "SELECT a.id, doc.user_id as doctor_user_id 
                      FROM appointments a
                      JOIN doctors doc ON a.doctor_id = doc.id
                      WHERE a.id = ? AND a.patient_id = ?";
        $stmt = $conn->prepare($verify_sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ii", $appointment_id, $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $appointment = $result->fetch_assoc();
        $stmt->close();

        if (!$appointment) {
            $response['error'] = "Invalid appointment";
            echo json_encode($response);
            return;
        }

        // Start transaction
        $conn->begin_transaction();

        // Insert message
        $insert_sql = "INSERT INTO messages (appointment_id, sender_id, recipient_id, message, created_at) 
                       VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($insert_sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("iiis", $appointment_id, $patient_id, $appointment['doctor_user_id'], $message_content);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $stmt->close();

        // Create notification
        $notification_title = "New Message from Patient";
        $notification_message = "Message from " . htmlspecialchars($patient_name) . " regarding appointment #" . $appointment_id;
        $type = 'message'; // Variable for the type

        // Check if link column exists
        $link_column_exists = false;
        $result = $conn->query("SHOW COLUMNS FROM `notifications` LIKE 'link'");
        if ($result && $result->num_rows > 0) {
            $link_column_exists = true;
        }
        if ($result)
            $result->free();

        if ($link_column_exists) {
            $notification_sql = "INSERT INTO notifications (user_id, title, message, type, link, created_at) 
                               VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($notification_sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $link = "doctor/message_patient.php?appointment_id=" . $appointment_id;
            $stmt->bind_param("issss", $appointment['doctor_user_id'], $notification_title, $notification_message, $type, $link);
        } else {
            $notification_sql = "INSERT INTO notifications (user_id, title, message, type, created_at) 
                               VALUES (?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($notification_sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("isss", $appointment['doctor_user_id'], $notification_title, $notification_message, $type);
        }

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $stmt->close();

        // Commit transaction
        $conn->commit();

        $response['success'] = true;
        echo json_encode($response);

    } catch (Exception $e) {
        // Rollback on error
        if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
            $conn->rollback();
        }

        error_log("Message Doctor Error: " . $e->getMessage());
        $response['error'] = "Failed to send message";
        echo json_encode($response);
    }
}
if (!function_exists('getStatusBadgeClass')) {
    function getStatusBadgeClass($status)
    {
        switch (strtolower($status ?? '')) { // Use ?? for safety if $status might be null
            case 'scheduled':
                return 'primary';
            case 'confirmed': // Example, if you add this status
                return 'info';
            case 'completed':
                return 'success';
            case 'cancelled':
                return 'danger';
            case 'no_show':
                return 'dark';
            case 'pending': // Example for other contexts if needed
                return 'warning';
            default:
                return 'secondary'; // Default fallback color
        }
    }
}
if (!function_exists('getDoctorNotifications')) {
    /**
     * Fetches notifications for a specific user.
     *
     * @param int $user_id The ID of the user (doctor) whose notifications to fetch.
     * @param mysqli $conn The database connection object.
     * @param int $limit The maximum number of notifications to fetch.
     * @return array An array of notifications, or an empty array on failure/no notifications.
     */
    function getDoctorNotifications($user_id, $conn, $limit = 20) {
        if (!$conn || $conn->connect_error) {
            error_log("getDoctorNotifications: DB connection error.");
            return [];
        }

        $notifications = [];
        // The notifications table stores notifications for a specific user_id.
        // We assume the user_id in the notifications table IS the doctor's user_id.
        $sql = "SELECT id, user_id, title, message, type, link, is_read, created_at
                FROM notifications
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("getDoctorNotifications: Prepare failed: " . $conn->error);
            return [];
        }

        $stmt->bind_param("ii", $user_id, $limit);

        if (!$stmt->execute()) {
            error_log("getDoctorNotifications: Execute failed: " . $stmt->error);
            $stmt->close();
            return [];
        }

        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Sanitize data fetched from DB before adding to array (optional, but good practice)
            // Example: $row['title'] = htmlspecialchars($row['title']);
            $notifications[] = $row;
        }
        $stmt->close();

        return $notifications;
    }
}


?>