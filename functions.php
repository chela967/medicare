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

function getDoctorPatientCount($doctor_id)
{
    global $conn;
    $query = "SELECT COUNT(DISTINCT patient_id) as count FROM appointments WHERE doctor_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['count'];
}
function getDoctorIdByUserId($user_id)
{
    global $conn;
    $stmt = $conn->prepare("SELECT id FROM doctors WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return ($result->num_rows > 0) ? $result->fetch_assoc()['id'] : null;
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
?>